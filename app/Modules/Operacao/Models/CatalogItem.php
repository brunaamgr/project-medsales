<?php

namespace App\Modules\Operacao\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property string $type
 * @property string $name
 * @property string|null $default_price
 * @property int|null $default_duration
 * @property int|null $user_id
 * @property bool $active
 */
#[Table('operacao_catalog_items')]
#[Fillable(['type', 'name', 'default_price', 'default_duration', 'user_id', 'active'])]
class CatalogItem extends Model
{
    use BelongsToTenant, HasPublicId;

    public const TYPES = [
        'professional' => 'Profissionais',
        'service_type' => 'Tipos de consulta',
        'attendance_type' => 'Tipos de atendimento',
        'location' => 'Locais',
        'insurance' => 'Convênios',
        'procedure' => 'Procedimentos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'default_duration' => 'integer'];
    }
}
