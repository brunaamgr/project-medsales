<?php

namespace App\Core\Employees;

use App\Core\Audit\AuditLogger;
use App\Core\Modules\Entitlements;
use App\Core\Modules\ModuleRegistry;
use App\Core\Permissions\AccessResolver;
use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Scope;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use App\Shared\Support\Transaction;
use Illuminate\Validation\ValidationException;

/** Perfis do tenant atual e quem pode mexer em quem. */
final readonly class AccessProfiles
{
    public function __construct(
        private TenantContext $context,
        private ModuleRegistry $registry,
        private Entitlements $entitlements,
        private AccessResolver $access,
        private AuditLogger $audit,
    ) {}

    /** Super Adm é o papel de sistema; Adm e Comum são papéis do próprio cliente. */
    public function role(Profile $profile): Role
    {
        if ($profile === Profile::SuperAdm) {
            return Role::system('tenant_owner');
        }

        return Role::query()->where('tenant_id', $this->context->requireId())->where('slug', $profile->value)->first()
            ?? $this->createDefault($profile);
    }

    public function of(Membership $membership): ?Profile
    {
        // O maior vale: quem acumula papéis não pode passar por um perfil menor.
        if ($membership->roles->contains(fn (Role $role): bool => $role->tenant_id === null && $role->slug === 'tenant_owner')) {
            return Profile::SuperAdm;
        }

        foreach ($membership->roles->sortByDesc('access_level') as $role) {
            if ($role->tenant_id === $membership->tenant_id && ($profile = Profile::tryFrom($role->slug)) !== null) {
                return $profile;
            }
        }

        return null;
    }

    public function label(Membership $membership): string
    {
        return $this->of($membership)?->label() ?? ($membership->roles->pluck('name')->join(', ') ?: 'Sem perfil');
    }

    public function level(?Membership $membership): int
    {
        return (int) ($membership?->roles->max('access_level') ?? 0);
    }

    /** Super Adm mexe em todos; os demais, só em quem está abaixo. */
    public function canManage(User $actor, Membership $target): bool
    {
        $level = $this->level($this->access->membership($actor));

        return $level >= Profile::SuperAdm->level() || $this->level($target) < $level;
    }

    /** @return list<Profile> */
    public function assignable(User $actor): array
    {
        $level = $this->level($this->access->membership($actor));

        return array_values(array_filter(Profile::cases(), fn (Profile $p): bool => $level >= Profile::SuperAdm->level() || $p->level() < $level));
    }

    /**
     * Permissões que o cliente pode distribuir: contratadas e fora da lista do Super Adm.
     *
     * @return array<string, array<string, string>> módulo => [permissão => descrição]
     */
    public function grantable(Profile $profile): array
    {
        $result = [];
        $superOnly = $this->superOnly();
        $blocked = config()->string('profiles.comum_blocked_prefix');

        foreach ($this->registry->all() as $manifest) {
            foreach ($manifest->permissions as $slug => $permission) {
                $contracted = $permission['feature'] === null
                    ? $this->entitlements->module($manifest->slug)
                    : $this->entitlements->feature($permission['feature']);

                if ($contracted && ! in_array($slug, $superOnly, true) && ! ($profile === Profile::Comum && str_starts_with($slug, $blocked))) {
                    $result[$manifest->name][$slug] = $permission['description'];
                }
            }
        }

        return $result;
    }

    /** @return array{scope: string, permissions: list<string>} */
    public function current(Profile $profile): array
    {
        $permissions = $this->role($profile)->permissions()->get();
        $scope = $permissions->first(fn (Permission $p): bool => $p->module !== ModuleRegistry::CORE)?->getAttribute('pivot')->getAttribute('scope');

        return [
            'scope' => is_string($scope) ? $scope : config()->string("profiles.defaults.{$profile->value}.scope"),
            'permissions' => array_values($permissions->map(fn (Permission $p): string => $p->slug)->all()),
        ];
    }

    /** @param array<string, array{scope: string, permissions: list<string>}> $input por perfil (comum, adm) */
    public function save(array $input): void
    {
        $this->assertAdmCoversComum($input);

        Transaction::run(function () use ($input): void {
            foreach (Profile::editable() as $profile) {
                $allowed = array_keys(array_merge(...array_values($this->grantable($profile))));
                $chosen = array_values(array_intersect($input[$profile->value]['permissions'], $allowed));
                $before = $this->current($profile);
                // O que não está na tela (módulo não contratado agora) fica como estava.
                $kept = array_values(array_diff($before['permissions'], $allowed));

                $this->grant($this->role($profile), [...$chosen, ...$kept], Scope::from($input[$profile->value]['scope']));
                $this->audit->record('perfil.updated', after: [
                    'perfil' => $profile->value, 'alcance' => $input[$profile->value]['scope'],
                    'liberadas' => array_values(array_diff($chosen, $before['permissions'])),
                    'removidas' => array_values(array_diff($before['permissions'], $chosen, $kept)),
                ]);
            }
        });

        $this->access->flush();
    }

    /** @return list<string> */
    private function superOnly(): array
    {
        $slugs = array_values(config()->array('profiles.super_only'));

        foreach ($this->registry->all() as $manifest) {
            foreach ($manifest->permissions as $slug => $permission) {
                if ($permission['super_only']) {
                    $slugs[] = $slug;
                }
            }
        }

        return $slugs;
    }

    private function createDefault(Profile $profile): Role
    {
        return Transaction::run(function () use ($profile): Role {
            $role = (new Role)->forceFill([
                'tenant_id' => $this->context->requireId(),
                'slug' => $profile->value,
                'name' => $profile->label(),
                'access_level' => $profile->level(),
                'is_system' => false,
            ]);
            $role->save();

            $default = config()->array("profiles.defaults.{$profile->value}");
            $permissions = $default['permissions'];

            if (in_array('*', $permissions, true)) {
                $business = Permission::query()->where('module', '!=', ModuleRegistry::CORE)->pluck('slug')->all();
                $permissions = [...array_diff($permissions, ['*']), ...$business];
            }

            $this->grant($role, array_values($permissions), Scope::from($default['scope']));

            return $role;
        });
    }

    /** @param list<string> $slugs */
    private function grant(Role $role, array $slugs, Scope $scope): void
    {
        $grants = [];

        // Última barreira: reservada nunca entra, venha do padrão, da tela ou do que já existia.
        foreach (Permission::query()->whereIn('slug', array_diff($slugs, $this->superOnly()))->get() as $permission) {
            // Administração não tem unidade nem dono: alcance sempre do cliente inteiro.
            $grants[$permission->id] = ['scope' => $permission->module === ModuleRegistry::CORE ? Scope::Tenant->value : $scope->value];
        }

        $role->permissions()->sync($grants);
    }

    /**
     * Sem isto, um Adm criaria uma conta Comum para fazer o que ele mesmo não pode.
     *
     * @param  array<string, array{scope: string, permissions: list<string>}>  $input
     */
    private function assertAdmCoversComum(array $input): void
    {
        $order = [Scope::Own->value => 1, Scope::Unit->value => 2, Scope::Tenant->value => 3];
        $missing = array_diff($input['comum']['permissions'], $input['adm']['permissions']);

        if ($missing !== [] || $order[$input['adm']['scope']] < $order[$input['comum']['scope']]) {
            throw ValidationException::withMessages(['comum' => 'O Adm precisa ter tudo o que o Comum tem, com alcance igual ou maior.']);
        }
    }
}
