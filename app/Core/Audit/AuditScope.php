<?php

namespace App\Core\Audit;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** No host da plataforma, só eventos da plataforma; nos demais, só o próprio tenant. */
final class AuditScope extends TenantScope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app(TenantContext::class)->isPlatform()) {
            $builder->whereNull($model->qualifyColumn('tenant_id'));

            return;
        }

        parent::apply($builder, $model);
    }
}
