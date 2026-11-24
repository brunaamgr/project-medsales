<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Permissions\AccessResolver;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

trait Concerns
{
    private function actor(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(403);
    }

    /**
     * Unidades em que a pessoa pode registrar: todas com escopo tenant, as dela nos demais.
     *
     * @return Collection<int, Unit>
     */
    private function units(User $user, string $permission): Collection
    {
        $access = app(AccessResolver::class);
        $query = Unit::query()->where('status', 'active')->orderBy('name');

        if (! $access->hasTenantScope($user, $permission)) {
            $query->whereKey($access->membership($user)?->units->modelKeys() ?? []);
        }

        return $query->get();
    }
}
