<?php

namespace App\Core\Modules\Http;

use App\Core\Modules\ModuleRegistry;
use App\Core\Permissions\AccessResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Página provisória de entrada, até cada módulo ter a sua. */
final class ModuleHomeController
{
    public function __invoke(Request $request, ModuleRegistry $registry, AccessResolver $access): View
    {
        $slug = explode('.', (string) $request->route()?->getName())[0];

        abort_unless($access->hasAnyIn($request->user(), $slug), 403);

        return view('modules.home', ['module' => $registry->get($slug)]);
    }
}
