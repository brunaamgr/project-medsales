<?php

namespace App\Core\Permissions\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Permissions\AccessResolver;
use App\Core\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsurePermission
{
    public function __construct(private AccessResolver $access, private AuditLogger $audit) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->allows($user, $permission)) {
            $this->audit->record('access.denied', result: 'denied', after: ['permission' => $permission]);
            abort(403);
        }

        return $next($request);
    }
}
