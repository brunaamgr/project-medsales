<?php

use App\Core\Auth\AuthServiceProvider;
use App\Core\Modules\ModuleServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    ModuleServiceProvider::class,
];
