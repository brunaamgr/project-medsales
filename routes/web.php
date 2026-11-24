<?php

use App\Core\Audit\Http\AuditController;
use App\Core\Employees\Http\EmployeeController;
use App\Core\Employees\Http\OwnPasswordController;
use App\Core\Employees\Http\ProfileController;
use App\Core\Invitations\Http\InvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function (): void {
    Route::get('/convite/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/convite/{token}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'tenant.member'])->group(function (): void {
    Route::view('/', 'dashboard')->name('dashboard');
    Route::get('/auditoria', AuditController::class)->middleware('permission:core.audit.view')->name('audit.index');

    Route::get('/minha-senha', [OwnPasswordController::class, 'edit'])->name('own-password.edit');
    Route::put('/minha-senha', [OwnPasswordController::class, 'update'])->middleware('throttle:10,1')->name('own-password.update');

    Route::middleware('permission:core.role.manage')->name('profiles.')->group(function (): void {
        Route::get('/perfis', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/perfis', [ProfileController::class, 'update'])->name('update');
    });

    Route::prefix('equipe')->name('employees.')->group(function (): void {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:core.usuario.view')->name('index');
        Route::get('/novo', [EmployeeController::class, 'create'])->middleware('permission:core.usuario.create')->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:core.usuario.create')->name('store');
        Route::get('/{member}', [EmployeeController::class, 'edit'])->middleware('permission:core.usuario.update')->name('edit');
        Route::put('/{member}', [EmployeeController::class, 'update'])->middleware('permission:core.usuario.update')->name('update');
        Route::put('/{member}/status', [EmployeeController::class, 'status'])->middleware('permission:core.usuario.suspend')->name('status');
        Route::put('/{member}/senha', [EmployeeController::class, 'password'])->middleware('permission:core.usuario.reset_password')->name('password');
        Route::delete('/{member}', [EmployeeController::class, 'destroy'])->middleware('permission:core.usuario.delete')->name('destroy');
    });
});
