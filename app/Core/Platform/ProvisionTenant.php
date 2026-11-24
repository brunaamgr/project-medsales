<?php

namespace App\Core\Platform;

use App\Core\Audit\AuditLogger;
use App\Core\Invitations\InvitationService;
use App\Core\Invitations\IssuedInvitation;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use Illuminate\Support\Facades\DB;

/** Cadastro completo do cliente: tenant, domínio, módulos e convite do responsável. */
final readonly class ProvisionTenant
{
    public function __construct(
        private TenantContext $context,
        private SyncEntitlements $entitlements,
        private InvitationService $invitations,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $modules
     * @param  list<string>  $features
     * @return array{Tenant, IssuedInvitation}
     */
    public function handle(string $name, string $subdomain, string $ownerEmail, array $modules, array $features, User $actor): array
    {
        return DB::transaction(function () use ($name, $subdomain, $ownerEmail, $modules, $features, $actor): array {
            $tenant = Tenant::query()->create(['name' => $name, 'slug' => $subdomain, 'subdomain' => $subdomain]);

            TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $subdomain.'.'.config()->string('tenancy.base_domain'),
                'type' => 'subdomain',
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            $this->audit->record('platform.tenant.created', $tenant, after: ['subdomain' => $subdomain]);
            $this->entitlements->handle($tenant, $modules, $features);

            // Papéis com escopo de unidade precisam de ao menos uma.
            $this->context->run($tenant, fn () => Unit::query()->create(['name' => 'Principal']));

            $issued = $this->context->run($tenant, fn (): IssuedInvitation => $this->invitations->issue($ownerEmail, Role::system('tenant_owner'), $actor));

            return [$tenant, $issued];
        });
    }
}
