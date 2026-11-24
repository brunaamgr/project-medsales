<?php

namespace App\Core\Auth;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Regras de sessão comuns ao tenant e à plataforma. */
final readonly class SessionGuardian
{
    public const CONTEXT = 'auth_context';

    public const STARTED = 'authenticated_at';

    public function __construct(private TenantContext $context, private AuditLogger $audit) {}

    public function start(): void
    {
        session()->put([
            self::CONTEXT => $this->context->sessionKey(),
            self::STARTED => now()->getTimestamp(),
        ]);
    }

    /** Motivo da recusa, ou null se a sessão vale neste host. */
    public function problem(Request $request): ?string
    {
        $limit = now()->subMinutes(config()->integer('tenancy.session_absolute_minutes'))->getTimestamp();

        return match (true) {
            $request->session()->get(self::CONTEXT) !== $this->context->sessionKey() => 'context_mismatch',
            (int) $request->session()->get(self::STARTED, 0) < $limit => 'absolute_timeout',
            default => null,
        };
    }

    public function reject(Request $request, string $reason): RedirectResponse
    {
        $this->audit->record('auth.session.rejected', result: 'denied', after: ['reason' => $reason]);

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.session_ended'));
    }
}
