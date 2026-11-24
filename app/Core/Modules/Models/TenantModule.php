<?php

namespace App\Core\Modules\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tenant_id
 * @property int $module_id
 * @property bool $enabled
 * @property-read Module $module
 */
#[Fillable(['module_id', 'enabled', 'settings'])]
class TenantModule extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'array'];
    }
}
