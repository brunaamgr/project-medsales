<?php

namespace App\Core\Platform\Http;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Contracts\View\View;

final class DashboardController
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'active' => Tenant::query()->where('status', 'active')->count(),
            'suspended' => Tenant::query()->where('status', 'suspended')->count(),
            'recent' => Tenant::query()->latest('id')->limit(5)->get(),
        ]);
    }
}
