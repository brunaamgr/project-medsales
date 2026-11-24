<?php

namespace App\Core\Audit;

use App\Core\Audit\Models\AuditLog;
use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final readonly class AuditLogger
{
    private const SENSITIVE = [
        'password', 'senha', 'token', 'secret', 'api_key', 'authorization', 'cookie', 'email', 'phone', 'telefone',
        'celular', 'whatsapp', 'cpf', 'cnpj', 'rg', 'name', 'nome', 'birth', 'nascimento', 'address', 'endereco',
    ];

    public function __construct(private TenantContext $context) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $resource = null,
        ?array $before = null,
        ?array $after = null,
        string $result = 'success',
        ?int $actorId = null,
    ): void {
        $request = app()->bound('request') ? request() : null;
        $actor = $actorId ?? Auth::id();

        $log = new AuditLog;
        $log->forceFill([
            'tenant_id' => $this->context->id(),
            'actor_id' => $actor === null ? null : (int) $actor,
            'action' => $action,
            'resource_type' => $resource === null ? null : class_basename($resource),
            'resource_id' => $resource === null ? null : (string) ($resource->getAttribute('public_id') ?? $resource->getKey()),
            'before' => $before === null ? null : self::mask($before),
            'after' => $after === null ? null : self::mask($after),
            'ip' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
            'result' => $result,
        ])->save();
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::mask($value);
            } elseif (is_string($key) && Str::contains(strtolower($key), self::SENSITIVE)) {
                $data[$key] = '[mascarado]';
            }
        }

        return $data;
    }
}
