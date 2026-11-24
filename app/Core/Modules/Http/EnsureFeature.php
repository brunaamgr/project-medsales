<?php

namespace App\Core\Modules\Http;

use App\Core\Modules\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureFeature
{
    public function __construct(private Entitlements $entitlements) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // 404: não revela funcionalidades que o tenant não contratou.
        abort_unless($this->entitlements->feature($feature), 404);

        return $next($request);
    }
}
