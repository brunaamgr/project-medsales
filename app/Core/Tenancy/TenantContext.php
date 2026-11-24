<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\MissingTenantContext;
use App\Core\Tenancy\Models\Tenant;

/** Tenant do request/job atual. Única fonte de tenant_id da aplicação. */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $platform = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->platform = false;
    }

    /** Request no host da plataforma: sem tenant e sem acesso a dados de domínio. */
    public function enterPlatform(): void
    {
        $this->tenant = null;
        $this->platform = true;
    }

    /** Falso dentro de run(): ali vale o tenant. */
    public function isPlatform(): bool
    {
        return $this->platform && $this->tenant === null;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function requireId(): int
    {
        return $this->tenant->id ?? throw new MissingTenantContext;
    }

    /** Marca gravada na sessão; prende a sessão ao host em que nasceu. */
    public function sessionKey(): string
    {
        return $this->isPlatform() ? 'platform' : 'tenant:'.$this->requireId();
    }

    /** @return array{Tenant|null, bool} */
    public function snapshot(): array
    {
        return [$this->tenant, $this->platform];
    }

    /** @param array{Tenant|null, bool} $snapshot */
    public function restore(array $snapshot): void
    {
        [$this->tenant, $this->platform] = $snapshot;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
