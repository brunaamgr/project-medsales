<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
class TenantScope implements Scope
{
    /** Sem contexto, lança exceção: nunca devolve dados de todos os tenants. */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('tenant_id'), app(TenantContext::class)->requireId());
    }
}
