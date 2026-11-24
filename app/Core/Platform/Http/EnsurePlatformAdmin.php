<?php

namespace App\Core\Platform\Http;

use App\Core\Auth\SessionGuardian;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsurePlatformAdmin
{
    public function __construct(private TenantContext $context, private SessionGuardian $sessions) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->context->isPlatform(), 404);

        $user = $request->user();
        $reason = $this->sessions->problem($request);

        if ($reason === null && (! $user instanceof User || ! PlatformAdmin::allows($user))) {
            $reason = 'inactive_platform_admin';
        }

        return $reason === null ? $next($request) : $this->sessions->reject($request, $reason);
    }
}
