<?php

namespace App\Core\Features\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tenant_id
 * @property int $feature_id
 * @property bool $enabled
 * @property-read Feature $feature
 */
#[Fillable(['feature_id', 'enabled', 'settings'])]
class TenantFeature extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'array'];
    }
}
