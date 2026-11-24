<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;

final class TenantUrl
{
    /** Esquema e porta vêm de APP_URL; o host, do subdomínio cadastrado. */
    public static function to(Tenant $tenant, string $path = ''): string
    {
        $base = parse_url(config()->string('app.url'));
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return ($base['scheme'] ?? 'https').'://'.$tenant->subdomain.'.'.config()->string('tenancy.base_domain').$port.'/'.ltrim($path, '/');
    }
}
