<?php

namespace App\Core\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sem escopo de tenant de propósito: é consultado para descobrir o tenant.
 *
 * @property int $tenant_id
 * @property string $host
 * @property-read Tenant|null $tenant
 */
#[Fillable(['tenant_id', 'host', 'type', 'is_primary', 'verified_at', 'ssl_status'])]
class TenantDomain extends Model
{
    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }
}
