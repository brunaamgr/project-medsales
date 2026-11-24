<?php

namespace App\Core\Audit;

use App\Core\Tenancy\TenantBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @template TModel of Model
 *
 * @extends TenantBuilder<TModel>
 */
final class AppendOnlyBuilder extends TenantBuilder
{
    /** @param array<mixed> $values */
    public function update(array $values)
    {
        throw new LogicException('Auditoria é append-only.');
    }

    public function delete()
    {
        throw new LogicException('Auditoria é append-only.');
    }

    public function forceDelete()
    {
        throw new LogicException('Auditoria é append-only.');
    }

    /** @param array<mixed> $parameters */
    public function __call($method, $parameters)
    {
        // Evento de plataforma (sem tenant) é o único insert fora da checagem de tenant.
        if (strtolower($method) === 'insertgetid' && ($parameters[0]['tenant_id'] ?? null) === null) {
            return $this->toBase()->insertGetId(...$parameters);
        }

        return parent::__call($method, $parameters);
    }
}
