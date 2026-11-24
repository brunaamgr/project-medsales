<?php

namespace App\Core\Permissions;

use App\Core\Features\Models\Feature;
use App\Core\Features\Models\TenantFeature;
use App\Core\Modules\Models\Module;
use App\Core\Modules\Models\TenantModule;
use App\Core\Modules\ModuleRegistry;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Espelha manifestos e papéis de sistema no banco. Idempotente. */
final readonly class AccessSynchronizer
{
    public function __construct(private ModuleRegistry $registry, private TenantContext $context) {}

    public function sync(): void
    {
        DB::transaction(function (): void {
            $this->catalog();
            $this->prune();
            $this->roles();
        });
    }

    private function catalog(): void
    {
        foreach ($this->registry->all() as $manifest) {
            $module = Module::query()->updateOrCreate(
                ['slug' => $manifest->slug],
                ['name' => $manifest->name, 'version' => $manifest->version],
            );

            foreach ($manifest->features as $slug => $feature) {
                $row = Feature::query()->updateOrCreate(['slug' => $slug], ['module_id' => $module->id, 'name' => $feature['name']]);

                if ($row->wasRecentlyCreated && $feature['essential']) {
                    $this->enableForCurrentTenants($row);
                }
            }

            foreach ($manifest->permissions as $slug => $permission) {
                Permission::query()->updateOrCreate(['slug' => $slug], [
                    'module' => $manifest->slug,
                    'feature' => $permission['feature'],
                    'description' => $permission['description'],
                ]);
            }
        }
    }

    /** Permissão retirada dos manifestos deixa de existir, inclusive nos perfis dos clientes. */
    private function prune(): void
    {
        $known = array_merge(...array_values(array_map(fn ($m): array => array_keys($m->permissions), $this->registry->all())));

        Permission::query()->whereNotIn('slug', $known)->delete();
    }

    private function enableForCurrentTenants(Feature $feature): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            $this->context->run($tenant, function () use ($feature): void {
                if (TenantModule::query()->where('module_id', $feature->module_id)->where('enabled', true)->exists()) {
                    TenantFeature::query()->updateOrCreate(['feature_id' => $feature->id], ['enabled' => true]);
                }
            });
        }
    }

    private function roles(): void
    {
        $ids = Permission::query()->pluck('id', 'slug')->all();

        foreach (config()->array('roles') as $slug => $definition) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $slug)->first() ?? new Role;
            $role->forceFill([
                'slug' => $slug,
                'name' => $definition['name'],
                'access_level' => $definition['level'],
                'is_system' => true,
            ])->save();

            $grants = [];
            if (isset($definition['grants_all_except'])) {
                foreach (array_diff_key($ids, array_flip($definition['grants_all_except'])) as $id) {
                    $grants[$id] = ['scope' => Scope::Tenant->value];
                }
            }

            foreach ($definition['grants'] as $scope => $permissions) {
                Scope::from($scope);

                foreach ($permissions as $permission) {
                    $id = $ids[$permission] ?? throw new InvalidArgumentException("Permissão inexistente: {$permission}.");
                    $grants[$id] = ['scope' => $scope];
                }
            }

            $role->permissions()->sync($grants);
        }
    }
}
