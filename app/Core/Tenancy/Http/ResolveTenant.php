<?php

namespace App\Core\Tenancy\Http;

use App\Core\Modules\Entitlements;
use App\Core\Permissions\AccessResolver;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Primeiro middleware do grupo web: roda antes de sessão, auth e bindings. */
final readonly class ResolveTenant
{
    public function __construct(
        private TenantContext $context,
        private TenantResolver $resolver,
        private Entitlements $entitlements,
        private AccessResolver $access,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->entitlements->flush();
        $this->access->flush();

        if (strtolower($request->getHost()) === config()->string('tenancy.platform_host')) {
            $this->context->enterPlatform();

            return $next($request);
        }

        $this->context->set($this->resolver->resolve($request->getHost()));

        abort_if($this->context->current() === null, 404);

        return $next($request);
    }
}
