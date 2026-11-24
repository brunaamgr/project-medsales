<?php

namespace App\Shared\Http;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeaders
{
    public function __construct(private Vite $vite) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->add([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // O servidor do Vite e a tela de erro de debug usam scripts inline; só existem em dev.
        $devPage = $this->vite->isRunningHot() || (config()->boolean('app.debug') && $response->getStatusCode() >= 500);

        if (! $devPage) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        }

        return $response;
    }
}
