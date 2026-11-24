<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Permissions\AccessResolver;
use App\Modules\Operacao\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PatientController
{
    use Concerns;

    public function __construct(private readonly AccessResolver $access, private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('busca')->toString());

        return view('operacao::patients.index', [
            'search' => $search,
            'patients' => $this->search($request, $search)->paginate(30)->withQueryString(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());

        return response()->json(mb_strlen($search) < 2 ? [] : $this->search($request, $search)->limit(10)->get()
            ->map(fn (Patient $p): array => ['id' => $p->public_id, 'name' => $p->name])->all());
    }

    public function edit(Request $request, Patient $patient): View
    {
        abort_unless($this->access->allows($this->actor($request), 'operacao.paciente.update', $patient), 403);
        $this->audit->record('operacao.paciente.viewed', $patient);

        return view('operacao::patients.edit', ['patient' => $patient]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($this->access->allows($this->actor($request), 'operacao.paciente.update', $patient), 403);
        $patient->update($request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30']]));
        $this->audit->record('operacao.paciente.updated', $patient);

        return redirect()->route('operacao.pacientes.index')->with('status', 'Paciente atualizado.');
    }

    /** @return Builder<Patient> */
    private function search(Request $request, string $search): Builder
    {
        $like = '%'.addcslashes($search, '%_\\').'%';

        return $this->access->restrict(Patient::query(), $this->actor($request), 'operacao.paciente.view')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('phone', $like)))
            ->orderBy('name');
    }
}
