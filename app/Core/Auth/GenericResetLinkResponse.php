<?php

namespace App\Core\Auth;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/** Mesma resposta do sucesso: não revela se o e-mail existe. */
final class GenericResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $message = __(Password::RESET_LINK_SENT);

        return $request->wantsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
