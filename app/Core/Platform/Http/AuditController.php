<?php

namespace App\Core\Platform\Http;

use App\Core\Audit\Models\AuditLog;
use Illuminate\Contracts\View\View;

final class AuditController
{
    public function __invoke(): View
    {
        return view('platform.audit', ['logs' => AuditLog::query()->latest('id')->simplePaginate(25)]);
    }
}
