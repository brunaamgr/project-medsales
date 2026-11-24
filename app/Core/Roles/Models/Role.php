<?php

namespace App\Core\Roles\Models;

use App\Core\Permissions\Models\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * tenant_id nulo = papel de sistema. Por isso não usa BelongsToTenant;
 * a checagem de tenant fica em Membership::assignRole e AccessResolver.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $slug
 * @property-read Collection<int, Permission> $permissions
 */
#[Fillable(['name'])]
class Role extends Model
{
    public const PLATFORM = 'platform_admin';

    public function isPlatform(): bool
    {
        return $this->tenant_id === null && $this->slug === self::PLATFORM;
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot('scope');
    }

    public static function system(string $slug): self
    {
        return self::query()->whereNull('tenant_id')->where('slug', $slug)->firstOrFail();
    }
}
