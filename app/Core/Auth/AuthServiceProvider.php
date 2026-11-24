<?php

namespace App\Core\Auth;

use App\Core\Audit\AuditLogger;
use App\Core\Permissions\AccessResolver;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Fortify;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericResetLinkResponse::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(12));

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::authenticateUsing(fn (Request $request) => $this->authenticate($request));

        $this->rateLimits();
        $this->gate();
        $this->events();
    }

    private function authenticate(Request $request): ?User
    {
        $password = $request->string('password')->toString();
        $login = Str::lower(trim($request->string('login')->toString()));
        $user = str_contains($login, '@') ? User::query()->where('email', $login)->first() : $this->byLogin($login);

        // Hash fictício: o tempo de resposta não revela se a conta existe.
        $valid = Hash::check($password, $user->password ?? $this->dummyHash());

        return $valid && $user !== null && $user->canEnterCurrentContext() ? $user : null;
    }

    /** Login vale só dentro do cliente do host; a plataforma usa e-mail. */
    private function byLogin(string $login): ?User
    {
        return $login === '' || app(TenantContext::class)->isPlatform()
            ? null
            : Membership::query()->where('login', $login)->with('user')->first()?->user;
    }

    private function dummyHash(): string
    {
        return once(fn () => Hash::make(Str::random(32)));
    }

    private function rateLimits(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', Str::lower(trim($request->string('login')->toString()))).'|'.$request->getHost().'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('fortify', fn (Request $request) => Limit::perMinute(60)->by('fortify:'.$request->ip()));
    }

    /** Abilities modulo.recurso.acao passam pelo RBAC: $user->can(), @can, authorize(). */
    private function gate(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (preg_match('/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$/', $ability) !== 1) {
                return null;
            }

            return app(AccessResolver::class)->allows($user, $ability, $arguments[0] ?? null);
        });
    }

    private function events(): void
    {
        $audit = fn (): AuditLogger => app(AuditLogger::class);

        Event::listen(Login::class, function (Login $event) use ($audit): void {
            app(SessionGuardian::class)->start();

            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->save();
            }

            $audit()->record('auth.login', actorId: (int) $event->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, fn (Logout $event) => $event->user === null
            ? null
            : $audit()->record('auth.logout', actorId: (int) $event->user->getAuthIdentifier()));

        // Correlaciona tentativas sem gravar o e-mail.
        Event::listen(Failed::class, fn (Failed $event) => $audit()->record('auth.login', result: 'failed', after: [
            'identifier_hash' => hash_hmac('sha256', Str::lower((string) ($event->credentials['login'] ?? '')), config()->string('app.key')),
        ]));

        Event::listen(PasswordReset::class, function (PasswordReset $event) use ($audit): void {
            if ($event->user instanceof User) {
                app(SessionRevoker::class)->revokeAll($event->user);
                $audit()->record('auth.password_reset', actorId: $event->user->id);
            }
        });
    }
}
