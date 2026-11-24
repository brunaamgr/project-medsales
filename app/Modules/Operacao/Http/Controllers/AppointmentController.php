<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Permissions\AccessResolver;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Actions\FindSimilarAppointments;
use App\Modules\Operacao\Actions\SaveAppointment;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\CatalogItem;
use App\Modules\Operacao\Models\Patient;
use App\Shared\Support\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AppointmentController
{
    use Concerns;

    public function __construct(
        private readonly AccessResolver $access,
        private readonly FormSchema $schema,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(array_keys(Appointment::STATUSES))],
            'profissional' => ['nullable', 'integer'],
        ]);
        $from = $filters['de'] ?? $this->today();
        $to = $filters['ate'] ?? $from;

        return view('operacao::appointments.index', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'professionals' => CatalogItem::query()->where('type', 'professional')->orderBy('name')->pluck('name', 'id'),
            'appointments' => $this->access->restrict(Appointment::query(), $this->actor($request), 'operacao.agenda.view')
                ->with(['patient', 'professional'])
                ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->when($filters['profissional'] ?? null, fn ($q, $id) => $q->where('professional_id', $id))
                ->orderBy('date')->orderBy('start_time')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, Appointment $appointment): View
    {
        abort_unless($this->access->allows($this->actor($request), 'operacao.agenda.view', $appointment), 403);
        $this->audit->record('operacao.agenda.viewed', $appointment);

        return $this->form($request, $appointment);
    }

    public function store(Request $request, SaveAppointment $save, FindSimilarAppointments $similar): RedirectResponse
    {
        return $this->persist($request, new Appointment, $save, $similar);
    }

    public function update(Request $request, Appointment $appointment, SaveAppointment $save, FindSimilarAppointments $similar): RedirectResponse
    {
        // Antes de validar ou avisar: quem não pode editar não recebe nenhuma pista.
        abort_unless($this->access->allows($this->actor($request), 'operacao.agenda.update', $appointment), 403);
        abort_if($appointment->status === 'cancelled', 409);

        return $this->persist($request, $appointment, $save, $similar);
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($this->access->allows($this->actor($request), 'operacao.agenda.cancel', $appointment), 403);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        abort_if($appointment->status === 'cancelled', 409);

        // Cancelar nunca apaga o registro.
        $appointment->forceFill(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason']])->save();
        $this->audit->record('operacao.agenda.cancelled', $appointment);

        return redirect()->route('operacao.agenda.index', ['de' => $appointment->date->format('Y-m-d')])->with('status', 'Consulta cancelada.');
    }

    private function form(Request $request, ?Appointment $appointment): View
    {
        $actor = $this->actor($request);

        return view('operacao::appointments.form', [
            'appointment' => $appointment,
            'today' => $this->today(),
            'canSave' => $appointment === null || $this->access->allows($actor, 'operacao.agenda.update', $appointment),
            'canCancel' => $appointment !== null && $this->access->allows($actor, 'operacao.agenda.cancel', $appointment),
            'schema' => $this->schema,
            'fields' => $this->schema->visible(),
            'choices' => $this->schema->choices($appointment),
            'units' => $this->units($actor, $appointment === null ? 'operacao.agenda.create' : 'operacao.agenda.update'),
            'patient' => $appointment->patient ?? $this->patient($actor, old('patient')),
        ]);
    }

    private function persist(Request $request, Appointment $appointment, SaveAppointment $save, FindSimilarAppointments $similar): RedirectResponse
    {
        $actor = $this->actor($request);
        $current = $appointment->exists ? $appointment : null;
        $units = $this->units($actor, $current === null ? 'operacao.agenda.create' : 'operacao.agenda.update');

        if ($units->count() === 1 && ! $request->filled('unit')) {
            $request->merge(['unit' => $units->sole()->public_id]);
        }

        $data = $request->validate([
            'patient' => ['nullable', 'string', 'required_without:new_patient_name'],
            'new_patient_name' => ['nullable', 'string', 'max:255'],
            'new_patient_phone' => ['nullable', 'string', 'max:30'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(array_keys(array_diff_key(Appointment::STATUSES, ['cancelled' => true])))],
            'unit' => [$units->isEmpty() ? 'nullable' : 'required', Rule::in($units->pluck('public_id')->all())],
            ...$this->schema->rules($current),
        ], [], $this->schema->attributes() + ['patient' => 'paciente', 'date' => 'data da consulta', 'unit' => 'unidade']);

        // Na edição, o paciente já vinculado continua válido mesmo fora do escopo de pacientes.
        $patient = $current !== null && ($data['patient'] ?? null) === $current->patient->public_id
            ? $current->patient
            : $this->patient($actor, $data['patient'] ?? null);

        if (filled($data['patient'] ?? null) && $patient === null) {
            throw ValidationException::withMessages(['patient' => 'Paciente não encontrado.']);
        }

        $value = fn (string $key): mixed => $data[$key] ?? ($current === null ? null : $current->getAttribute($key));
        $warnings = $request->boolean('confirm_similar') ? [] : $similar->handle(
            $patient?->id, $data['date'], $value('start_time') === null ? null : substr((string) $value('start_time'), 0, 5),
            $value('duration_minutes') === null ? null : (int) $value('duration_minutes'),
            $value('professional_id') === null ? null : (int) $value('professional_id'), $current?->id,
        );

        if ($warnings !== []) {
            return back()->withInput()->with('similar', $warnings);
        }

        $unit = $units->firstWhere('public_id', $data['unit'] ?? null);

        Transaction::run(function () use (&$appointment, $patient, $unit, $data, $actor, $save): void {
            $patient ??= $this->createPatient($actor, $data, $unit?->id);
            $appointment = $save->handle($appointment, $patient, $unit?->id, $data, $actor);
        });

        return redirect()->route('operacao.agenda.index', ['de' => $appointment->date->format('Y-m-d')])
            ->with('status', $current === null ? "Consulta {$appointment->number} agendada." : "Consulta {$appointment->number} atualizada.");
    }

    private function patient(User $actor, mixed $publicId): ?Patient
    {
        return is_string($publicId) && $publicId !== ''
            ? $this->access->restrict(Patient::query(), $actor, 'operacao.paciente.view')->where('public_id', $publicId)->first()
            : null;
    }

    /** @param array<string, mixed> $data */
    private function createPatient(User $actor, array $data, ?int $unitId): Patient
    {
        abort_unless($this->access->allows($actor, 'operacao.paciente.create'), 403);

        $patient = Patient::query()->create([
            'name' => trim((string) $data['new_patient_name']),
            'phone' => $data['new_patient_phone'] ?? null,
            'unit_id' => $unitId,
            'owner_id' => $this->access->hasTenantScope($actor, 'operacao.paciente.create') ? null : $actor->id,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('operacao.paciente.created', $patient);

        return $patient;
    }

    private function today(): string
    {
        return now(config()->string('app.display_timezone'))->format('Y-m-d');
    }
}
