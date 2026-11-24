<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\CrossTenantAccess;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;

/** Obrigatório em todo model de domínio. */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = app(TenantContext::class)->requireId();
            $given = $model->getAttribute('tenant_id');

            if ($given !== null && (int) $given !== $current) {
                throw new CrossTenantAccess;
            }

            $model->setAttribute('tenant_id', $current);
        });

        // save()/delete() usam só a chave primária; por isso a checagem aqui.
        $guard = function (Model $model): void {
            $current = app(TenantContext::class)->requireId();

            if ($model->isDirty('tenant_id') || (int) $model->getRawOriginal('tenant_id') !== $current) {
                throw new CrossTenantAccess;
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder  $query
     * @return TenantBuilder<*>
     */
    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }
}
