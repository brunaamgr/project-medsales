<?php

namespace App\Modules\Operacao\Models;

use App\Core\Permissions\ScopedResource;
use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sem Fillable: só SaveAppointment preenche, com as chaves liberadas pelo formulário do cliente.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $patient_id
 * @property int|null $unit_id
 * @property int|null $owner_id
 * @property Carbon $date
 * @property string|null $start_time
 * @property int|null $duration_minutes
 * @property string $status
 * @property int|null $professional_id
 * @property string|null $cancel_reason
 * @property array<string, mixed>|null $custom_values
 * @property-read Patient $patient
 * @property-read CatalogItem|null $professional
 */
#[Table('operacao_appointments')]
class Appointment extends Model implements ScopedResource
{
    use BelongsToTenant, HasPublicId;

    public const STATUSES = [
        'scheduled' => 'Agendado',
        'confirmed' => 'Confirmado',
        'done' => 'Realizado',
        'no_show' => 'Faltou',
        'cancelled' => 'Cancelado',
    ];

    protected $attributes = ['status' => 'scheduled'];

    protected $guarded = ['*'];

    public function unitColumn(): ?string
    {
        return 'unit_id';
    }

    public function ownerColumn(): ?string
    {
        return 'owner_id';
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<CatalogItem, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function time(): ?string
    {
        return $this->start_time === null ? null : substr($this->start_time, 0, 5);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'booked_on' => 'date',
            'duration_minutes' => 'integer',
            'notes' => 'encrypted',
            'custom_values' => 'encrypted:array',
        ];
    }
}
