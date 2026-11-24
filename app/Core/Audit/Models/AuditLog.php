<?php

namespace App\Core\Audit\Models;

use App\Core\Audit\AppendOnlyBuilder;
use App\Core\Audit\AuditScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only. Leitura sempre no tenant atual; escrita só pelo AuditLogger.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $actor_id
 * @property string $action
 * @property string $result
 * @property string|null $ip
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::addGlobalScope(new AuditScope);

        $deny = static fn () => throw new LogicException('Auditoria é append-only.');
        static::updating($deny);
        static::deleting($deny);
    }

    /**
     * @param  Builder  $query
     * @return AppendOnlyBuilder<*>
     */
    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
