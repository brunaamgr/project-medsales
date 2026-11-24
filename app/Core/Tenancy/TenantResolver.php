<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;

final class TenantResolver
{
    /** Nunca há tenant padrão: host desconhecido devolve null. */
    public function resolve(string $host): ?Tenant
    {
        $host = rtrim(strtolower($host), '.');

        $domain = TenantDomain::query()->where('host', $host)->whereNotNull('verified_at')->with('tenant')->first();
        $tenant = $domain->tenant ?? $this->bySubdomain($host);

        return $tenant?->isActive() === true ? $tenant : null;
    }

    private function bySubdomain(string $host): ?Tenant
    {
        $suffix = '.'.config()->string('tenancy.base_domain');

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        return Subdomain::isValid($label)
            ? Tenant::query()->where('subdomain', $label)->first()
            : null;
    }
}
