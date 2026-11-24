<?php

namespace Tests;

use App\Core\Features\Models\Feature;
use App\Core\Features\Models\TenantFeature;
use App\Core\Modules\Entitlements;
use App\Core\Modules\Models\Module;
use App\Core\Modules\Models\TenantModule;
use App\Core\Permissions\AccessResolver;
use App\Core\Permissions\AccessSynchronizer;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Cada request recarrega o usuário da sessão, como em produção. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        app('auth')->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function access(): AccessResolver
    {
        app(AccessResolver::class)->flush();
        app(Entitlements::class)->flush();

        return app(AccessResolver::class);
    }

    protected function syncAccess(): void
    {
        app(AccessSynchronizer::class)->sync();
    }

    protected function tenant(string $slug): Tenant
    {
        return Tenant::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'subdomain' => $slug]);
    }

    protected function member(Tenant $tenant, ?string $role = null, ?User $user = null, string $status = 'active'): User
    {
        $user ??= User::factory()->create();

        $this->context()->run($tenant, function () use ($user, $role, $status): void {
            $membership = Membership::query()->create(['user_id' => $user->id, 'status' => $status]);

            if ($role !== null) {
                $membership->assignRole(Role::system($role));
            }
        });

        return $user;
    }

    /** @param list<string> $features */
    protected function enable(Tenant $tenant, string $module, array $features = []): void
    {
        $this->context()->run($tenant, function () use ($module, $features): void {
            TenantModule::query()->create(['module_id' => Module::query()->where('slug', $module)->sole()->id, 'enabled' => true]);

            foreach ($features as $feature) {
                TenantFeature::query()->create(['feature_id' => Feature::query()->where('slug', "{$module}.{$feature}")->sole()->id, 'enabled' => true]);
            }
        });
    }

    protected function login(Tenant $tenant, User $user): void
    {
        $this->post($this->url($tenant, '/login'), ['login' => $user->email, 'password' => 'senha-de-teste-123'])
            ->assertRedirect($this->url($tenant, '/'));
    }

    protected function url(Tenant $tenant, string $path = '/'): string
    {
        return "http://{$tenant->subdomain}.localhost".($path === '/' ? '' : $path);
    }
}
