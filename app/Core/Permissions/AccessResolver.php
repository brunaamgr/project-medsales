<?php

namespace App\Core\Permissions;

use App\Core\Modules\Entitlements;
use App\Core\Permissions\Models\ResourceAcl;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decide o acesso no tenant atual: vínculo ativo → papéis → permissão →
 * módulo/funcionalidade contratados → escopo. Negado por padrão.
 */
final class AccessResolver
{
    /** @var array<string, Membership|null> */
    private array $memberships = [];

    /** @var array<string, array<string, array{id: int, scopes: list<Scope>}>> */
    private array $grants = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly Entitlements $entitlements,
    ) {}

    /** Memória só do request; o middleware limpa a cada requisição. */
    public function flush(): void
    {
        $this->memberships = $this->grants = [];
    }

    public function membership(User $user): ?Membership
    {
        if (! $user->isActive() || $this->context->current()?->isActive() !== true) {
            return null;
        }

        $key = $this->key($user);

        if (! array_key_exists($key, $this->memberships)) {
            $this->memberships[$key] = Membership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
        }

        return $this->memberships[$key];
    }

    /** Sem recurso, responde se a permissão existe em algum escopo (rota/menu). */
    public function allows(User $user, string $permission, ?Model $resource = null): bool
    {
        $grant = $this->grants($user)[$permission] ?? null;

        if ($grant === null) {
            return false;
        }

        if ($resource === null) {
            return true;
        }

        if ((int) $resource->getAttribute('tenant_id') !== $this->context->requireId()) {
            return false;
        }

        foreach ($grant['scopes'] as $scope) {
            if ($this->scopeAllows($scope, $user, $resource)) {
                return true;
            }
        }

        return $this->acl($user, $grant['id'], $resource)
            ->where('resource_id', $resource->getKey())
            ->exists();
    }

    /**
     * Restringe uma listagem ao que a pessoa pode ver.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrict(Builder $query, User $user, string $permission): Builder
    {
        $grant = $this->grants($user)[$permission] ?? null;
        $model = $query->getModel();

        if ($grant === null) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array(Scope::Tenant, $grant['scopes'], true)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($grant, $user, $model): void {
            $q->whereIn($model->getQualifiedKeyName(), $this->acl($user, $grant['id'], $model)->select('resource_id'));

            foreach ($grant['scopes'] as $scope) {
                $column = $this->column($scope, $model);

                if ($column !== null) {
                    $scope === Scope::Unit
                        ? $q->orWhereIn($model->qualifyColumn($column), $this->unitIds($user))
                        : $q->orWhere($model->qualifyColumn($column), $user->id);
                }
            }
        });
    }

    public function hasTenantScope(User $user, string $permission): bool
    {
        return in_array(Scope::Tenant, $this->grants($user)[$permission]['scopes'] ?? [], true);
    }

    public function hasAnyIn(?User $user, string $module): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (array_keys($this->grants($user)) as $permission) {
            if (str_starts_with($permission, "{$module}.")) {
                return true;
            }
        }

        return false;
    }

    private function scopeAllows(Scope $scope, User $user, Model $resource): bool
    {
        if ($scope === Scope::Tenant) {
            return true;
        }

        $column = $this->column($scope, $resource);
        $value = $column === null ? null : $resource->getAttribute($column);

        if ($value === null) {
            return false;
        }

        return $scope === Scope::Unit
            ? in_array((int) $value, $this->unitIds($user), true)
            : (int) $value === $user->id;
    }

    private function column(Scope $scope, Model $model): ?string
    {
        if (! $model instanceof ScopedResource) {
            return null;
        }

        return match ($scope) {
            Scope::Unit => $model->unitColumn(),
            Scope::Own => $model->ownerColumn(),
            default => null,
        };
    }

    /** @return list<int> */
    private function unitIds(User $user): array
    {
        return array_values($this->membership($user)?->units->modelKeys() ?? []);
    }

    /** @return Builder<ResourceAcl> */
    private function acl(User $user, int $permissionId, Model $resource): Builder
    {
        return ResourceAcl::query()
            ->where('membership_id', $this->membership($user)?->id)
            ->where('permission_id', $permissionId)
            ->where('resource_type', $resource->getMorphClass())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return array<string, array{id: int, scopes: list<Scope>}> */
    private function grants(User $user): array
    {
        $membership = $this->membership($user);

        if ($membership === null) {
            return [];
        }

        $key = $this->key($user);

        if (isset($this->grants[$key])) {
            return $this->grants[$key];
        }

        $grants = [];

        foreach ($membership->load('roles.permissions')->roles as $role) {
            if ($role->isPlatform() || ($role->tenant_id !== null && $role->tenant_id !== $membership->tenant_id)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $scope = Scope::tryFrom((string) $permission->getAttribute('pivot')->getAttribute('scope'));
                $contracted = $permission->feature === null
                    ? $this->entitlements->module($permission->module)
                    : $this->entitlements->feature($permission->feature);

                if ($scope !== null && $contracted) {
                    $grants[$permission->slug]['id'] = $permission->id;
                    $grants[$permission->slug]['scopes'][] = $scope;
                }
            }
        }

        return $this->grants[$key] = $grants;
    }

    private function key(User $user): string
    {
        return $this->context->requireId().':'.$user->id;
    }
}
