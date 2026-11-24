<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Permissions\AccessResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController
{
    use Concerns;

    public function __invoke(Request $request, AccessResolver $access): View
    {
        abort_unless($access->hasAnyIn($this->actor($request), 'operacao'), 403);

        return view('operacao::home');
    }
}
