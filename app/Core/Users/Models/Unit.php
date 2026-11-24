<?php

namespace App\Core\Users\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 */
#[Fillable(['name', 'status'])]
class Unit extends Model
{
    use BelongsToTenant, HasPublicId;
}
