<?php

namespace App\Core\Auth;

use App\Core\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A tabela sessions é do framework (identidade global, sem tenant). */
final class SessionRevoker
{
    public function revokeAll(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        // Sem isto, um cookie "lembrar" abriria uma sessão nova.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
