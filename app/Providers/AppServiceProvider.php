<?php

namespace App\Providers;

use App\Core\Modules\Entitlements;
use App\Core\Modules\ModuleRegistry;
use App\Core\Permissions\AccessResolver;
use App\Core\Permissions\SyncAccessCommand;
use App\Core\Platform\CreatePlatformAdminCommand;
use App\Core\Tenancy\QueueTenancy;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(Entitlements::class);
        $this->app->scoped(AccessResolver::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        (new QueueTenancy)->register();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncAccessCommand::class, CreatePlatformAdminCommand::class]);
        }

        View::composer('components.layouts.app', function (ViewContract $view): void {
            $user = auth()->user();
            $access = app(AccessResolver::class);

            $view->with([
                'tenant' => app(TenantContext::class)->current(),
                'menu' => array_filter(
                    app(ModuleRegistry::class)->business(),
                    fn ($module) => $user instanceof User && $access->hasAnyIn($user, $module->slug),
                ),
            ]);
        });
    }
}
