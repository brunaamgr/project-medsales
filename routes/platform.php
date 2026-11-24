<?php

use App\Core\Platform\Http\AuditController;
use App\Core\Platform\Http\DashboardController;
use App\Core\Platform\Http\TenantController;
use Illuminate\Support\Facades\Route;

// Carregado antes de web.php: no host da plataforma, estas rotas vencem as do tenant.
Route::domain(config()->string('tenancy.platform_host'))
    ->middleware(['auth', 'platform'])
    ->name('platform.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/auditoria', AuditController::class)->name('audit');
        Route::get('/clientes', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/clientes/novo', [TenantController::class, 'create'])->name('tenants.create');
        Route::get('/clientes/subdominio', [TenantController::class, 'availability'])->middleware('throttle:60,1')->name('tenants.availability');
        Route::post('/clientes', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/clientes/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('/clientes/{tenant}/modulos', [TenantController::class, 'entitlements'])->name('tenants.entitlements');
        Route::put('/clientes/{tenant}/status', [TenantController::class, 'status'])->name('tenants.status');
        Route::post('/clientes/{tenant}/convites', [TenantController::class, 'invite'])->middleware('throttle:20,1')->name('tenants.invite');
    });
