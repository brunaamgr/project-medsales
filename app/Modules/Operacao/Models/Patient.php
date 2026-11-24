<?php

namespace App\Modules\Operacao\Models;

use App\Core\Permissions\ScopedResource;
use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $phone
 * @property int|null $unit_id
 * @property int|null $owner_id
 */
#[Table('operacao_patients')]
#[Fillable(['name', 'phone', 'unit_id', 'owner_id', 'created_by'])]
class Patient extends Model implements ScopedResource
{
    use BelongsToTenant, HasPublicId, SoftDeletes;

    public function unitColumn(): ?string
    {
        return 'unit_id';
    }

    public function ownerColumn(): ?string
    {
        return 'owner_id';
    }
}
