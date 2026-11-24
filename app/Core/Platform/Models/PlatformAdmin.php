<?php

namespace App\Core\Platform\Models;

use App\Core\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Global de propósito: quem administra a plataforma, sem vínculo com tenant. */
#[Fillable(['user_id', 'status'])]
class PlatformAdmin extends Model
{
    public static function allows(User $user): bool
    {
        return $user->isActive()
            && self::query()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
