<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry([
            app_path('Core/module.php'),
            ...(glob(app_path('Modules/*/module.php')) ?: []),
        ]));
    }

    public function boot(ModuleRegistry $registry): void
    {
        foreach ($registry->business() as $module) {
            if (is_file($routes = "{$module->path}/Routes/web.php") && ! $this->app->routesAreCached()) {
                Route::middleware(['web', 'auth', 'tenant.member', "module:{$module->slug}"])
                    ->prefix($module->slug)
                    ->name("{$module->slug}.")
                    ->group($routes);
            }

            // Configurações do módulo feitas pela plataforma, por cliente.
            if (is_file($platform = "{$module->path}/Routes/platform.php") && ! $this->app->routesAreCached()) {
                Route::domain(config()->string('tenancy.platform_host'))
                    ->middleware(['web', 'auth', 'platform'])
                    ->prefix("clientes/{tenant}/{$module->slug}")
                    ->name("platform.modules.{$module->slug}.")
                    ->group($platform);
            }

            if (is_dir($views = "{$module->path}/Views")) {
                $this->loadViewsFrom($views, $module->slug);
            }

            if (is_dir($migrations = "{$module->path}/Database/Migrations")) {
                $this->loadMigrationsFrom($migrations);
            }
        }
    }
}
