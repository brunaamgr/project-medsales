<?php

use App\Core\Modules\Http\ModuleHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', ModuleHomeController::class)->name('home');
