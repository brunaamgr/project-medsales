<?php

namespace App\Core\Audit\Http;

use App\Core\Audit\Models\AuditLog;
use Illuminate\Contracts\View\View;

final class AuditController
{
    public function __invoke(): View
    {
        return view('audit.index', [
            'logs' => AuditLog::query()->latest('id')->simplePaginate(25),
        ]);
    }
}
