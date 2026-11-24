<?php

namespace App\Modules\Operacao\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Permissions\AccessResolver;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\CatalogItem;
use App\Modules\Operacao\Models\Counter;
use App\Modules\Operacao\Models\Patient;
use App\Shared\Support\Transaction;

final readonly class SaveAppointment
{
    public function __construct(private FormSchema $schema, private AccessResolver $access, private AuditLogger $audit) {}

    /** @param array<string, mixed> $data dados já validados por FormSchema::rules() */
    public function handle(Appointment $appointment, Patient $patient, ?int $unitId, array $data, User $actor): Appointment
    {
        return Transaction::run(function () use ($appointment, $patient, $unitId, $data, $actor): Appointment {
            // O registro precisa estar no escopo da pessoa antes e depois da edição.
            $new = ! $appointment->exists;
            $permission = $new ? 'operacao.agenda.create' : 'operacao.agenda.update';
            abort_unless($new || $this->access->allows($actor, $permission, $appointment), 403);

            $appointment->forceFill(['patient_id' => $patient->id, 'unit_id' => $unitId, 'date' => $data['date'], 'status' => $data['status'] ?? $appointment->status]);
            // Campo oculto mantém o valor que já tinha.
            $custom = $appointment->custom_values ?? [];

            foreach ($this->schema->visible() as $key => $field) {
                if ($field->custom) {
                    $custom[$key] = $field->type === 'checkbox' ? (bool) ($data['custom'][$key] ?? false) : ($data['custom'][$key] ?? null);
                } else {
                    $appointment->forceFill([$key => $data[$key] ?? null]);
                }
            }

            $professional = $appointment->professional_id === null ? null : CatalogItem::query()->find($appointment->professional_id);
            $appointment->forceFill(['custom_values' => $custom === [] ? null : $custom, 'owner_id' => $professional?->user_id]);

            if ($new) {
                $appointment->forceFill([
                    'tenant_id' => $patient->getAttribute('tenant_id'),
                    'created_by' => $actor->id,
                    'number' => sprintf('CS%04d', Counter::next('appointment')),
                ]);
            }

            abort_unless($this->access->allows($actor, $permission, $appointment), 403);

            $changed = array_keys($appointment->getDirty());
            $appointment->save();
            // Só os nomes dos campos; nenhum valor vai para a auditoria.
            $this->audit->record($new ? 'operacao.agenda.created' : 'operacao.agenda.updated', $appointment, after: ['campos' => array_values(array_diff($changed, ['tenant_id', 'public_id']))]);

            return $appointment;
        });
    }
}
