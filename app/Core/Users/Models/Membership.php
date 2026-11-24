<?php

namespace App\Core\Users\Models;

use App\Core\Employees\Models\Employee;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\BelongsToTenant;
use App\Core\Tenancy\Exceptions\CrossTenantAccess;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $status
 * @property string|null $login
 * @property-read User|null $user
 * @property-read Employee|null $employee
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Unit> $units
 */
#[Fillable(['user_id', 'status', 'invited_by', 'joined_at'])]
class Membership extends Model
{
    use BelongsToTenant, HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles');
    }

    /** @return BelongsToMany<Unit, $this> */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'membership_units');
    }

    /** Único caminho para conceder papel: valida tenant e papel de plataforma. */
    public function assignRole(Role $role): void
    {
        if (($role->tenant_id !== null && $role->tenant_id !== $this->tenant_id) || $role->isPlatform()) {
            throw new CrossTenantAccess;
        }

        $this->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $this->tenant_id]]);
    }

    /** Troca todos os papéis por um só. */
    public function replaceRole(Role $role): void
    {
        $this->roles()->detach();
        $this->assignRole($role);
        $this->unsetRelation('roles');
    }

    /** @param Collection<int, Unit> $units */
    public function replaceUnits(Collection $units): void
    {
        $this->units()->detach();
        $units->each(fn (Unit $unit) => $this->assignUnit($unit));
        $this->unsetRelation('units');
    }

    public function assignUnit(Unit $unit): void
    {
        if ($unit->tenant_id !== $this->tenant_id) {
            throw new CrossTenantAccess;
        }

        $this->units()->syncWithoutDetaching([$unit->id => ['tenant_id' => $this->tenant_id]]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }
}
