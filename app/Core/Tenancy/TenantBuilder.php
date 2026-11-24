<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\CrossTenantAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fecha, em tempo de execução, os atalhos do Eloquent que pulam o escopo
 * (remoção de escopo) ou os eventos (insert/upsert/update em massa).
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class TenantBuilder extends Builder
{
    private const CHECKED_INSERTS = ['insert', 'insertgetid', 'insertorignore'];

    private const BLOCKED = ['insertusing', 'insertorignoreusing', 'insertorignorereturning', 'updatefrom', 'truncate'];

    /** @param Scope<Model>|string $scope */
    public function withoutGlobalScope($scope)
    {
        if (is_a($scope, TenantScope::class, true)) {
            throw new CrossTenantAccess;
        }

        return parent::withoutGlobalScope($scope);
    }

    /** @param array<mixed>|null $scopes */
    public function withoutGlobalScopes(?array $scopes = null)
    {
        if ($scopes === null) {
            throw new CrossTenantAccess;
        }

        return parent::withoutGlobalScopes($scopes);
    }

    /** @param array<mixed> $scopes */
    public function withoutGlobalScopesExcept(array $scopes = [])
    {
        throw new CrossTenantAccess;
    }

    /** @param array<mixed> $values */
    public function update(array $values)
    {
        foreach (array_keys($values) as $column) {
            if (is_string($column) && str_ends_with($column, 'tenant_id')) {
                throw new CrossTenantAccess;
            }
        }

        return parent::update($values);
    }

    /**
     * @param  array<mixed>  $values
     * @param  array<mixed>|string  $uniqueBy
     * @param  array<mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        // ON DUPLICATE KEY pode atualizar linha de outro tenant.
        throw new CrossTenantAccess;
    }

    /** @param array<mixed> $parameters */
    public function __call($method, $parameters)
    {
        $name = strtolower($method);

        if (in_array($name, self::BLOCKED, true)) {
            throw new CrossTenantAccess;
        }

        if (in_array($name, self::CHECKED_INSERTS, true)) {
            $this->assertRows($parameters[0] ?? []);
        }

        return parent::__call($method, $parameters);
    }

    /** @param array<mixed> $values */
    private function assertRows(array $values): void
    {
        $current = app(TenantContext::class)->requireId();
        $rows = is_array(reset($values)) ? $values : [$values];

        foreach ($rows as $row) {
            if ($row !== [] && (int) ($row['tenant_id'] ?? 0) !== $current) {
                throw new CrossTenantAccess;
            }
        }
    }
}
