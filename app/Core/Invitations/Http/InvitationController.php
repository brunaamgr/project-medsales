<?php

namespace App\Core\Invitations\Http;

use App\Core\Invitations\InvitationService;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final readonly class InvitationController
{
    public function __construct(private InvitationService $invitations, private TenantContext $context) {}

    public function show(string $token): View
    {
        abort_if($this->context->isPlatform(), 404);
        $invitation = $this->invitations->find($token) ?? abort(404);

        return view('auth.invitation', [
            'token' => $token,
            'email' => $invitation->email,
            'existing' => User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        abort_if($this->context->isPlatform(), 404);
        $invitation = $this->invitations->find($token) ?? abort(404);
        $existing = User::query()->where('email', $invitation->email)->exists();

        $data = $request->validate($existing
            ? ['password' => ['required', 'string', 'max:200']]
            : ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:200', Password::default(), 'confirmed']]);

        $this->invitations->accept($token, $data['name'] ?? null, $data['password']);

        return redirect()->route('login')->with('status', 'Convite aceito. Entre com seu e-mail e senha.');
    }
}
