<?php

namespace App\Core\Tenancy\Http;

use App\Core\Auth\SessionGuardian;
use App\Core\Permissions\AccessResolver;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Revalida a cada request: sessão deste tenant, dentro do prazo e vínculo ativo. */
final readonly class EnsureActiveMembership
{
    public function __construct(
        private TenantContext $context,
        private AccessResolver $access,
        private SessionGuardian $sessions,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->context->isPlatform(), 404);

        $user = $request->user();
        $reason = $this->sessions->problem($request);

        if ($reason === null && (! $user instanceof User || $this->access->membership($user) === null)) {
            $reason = 'inactive_membership';
        }

        return $reason === null ? $next($request) : $this->sessions->reject($request, $reason);
    }
}
