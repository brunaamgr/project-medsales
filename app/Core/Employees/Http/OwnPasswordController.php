<?php

namespace App\Core\Employees\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Auth\SessionRevoker;
use App\Core\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class OwnPasswordController
{
    public function edit(): View
    {
        return view('employees.own-password');
    }

    public function update(Request $request, SessionRevoker $sessions, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'max:200', Password::default(), 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Senha atual incorreta.']);
        }

        $user->update(['password' => $data['password']]);
        $audit->record('auth.password_changed');
        $sessions->revokeAll($user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha alterada. Entre novamente.');
    }
}
