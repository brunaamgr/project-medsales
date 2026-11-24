<?php

use App\Modules\Operacao\Http\Controllers\AppointmentController;
use App\Modules\Operacao\Http\Controllers\CatalogController;
use App\Modules\Operacao\Http\Controllers\FieldController;
use App\Modules\Operacao\Http\Controllers\HomeController;
use App\Modules\Operacao\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

// Índice do módulo: só links, cada um conforme a permissão; exige alguma permissão de Operação.
Route::get('/', HomeController::class)->name('home');

Route::middleware('feature:operacao.agenda')->prefix('agenda')->name('agenda.')->group(function (): void {
    Route::get('/', [AppointmentController::class, 'index'])->middleware('permission:operacao.agenda.view')->name('index');
    Route::get('/nova', [AppointmentController::class, 'create'])->middleware('permission:operacao.agenda.create')->name('create');
    Route::post('/', [AppointmentController::class, 'store'])->middleware('permission:operacao.agenda.create')->name('store');
    Route::get('/{appointment}', [AppointmentController::class, 'edit'])->middleware('permission:operacao.agenda.view')->name('edit');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:operacao.agenda.update')->name('update');
    Route::put('/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->middleware('permission:operacao.agenda.cancel')->name('cancel');
});

Route::middleware('feature:operacao.pacientes')->prefix('pacientes')->name('pacientes.')->group(function (): void {
    Route::get('/', [PatientController::class, 'index'])->middleware('permission:operacao.paciente.view')->name('index');
    Route::get('/busca', [PatientController::class, 'lookup'])->middleware(['permission:operacao.paciente.view', 'throttle:120,1'])->name('lookup');
    Route::get('/{patient}', [PatientController::class, 'edit'])->middleware('permission:operacao.paciente.update')->name('edit');
    Route::put('/{patient}', [PatientController::class, 'update'])->middleware('permission:operacao.paciente.update')->name('update');
});

Route::middleware('feature:operacao.cadastros')->prefix('cadastros')->name('cadastros.')->group(function (): void {
    Route::get('/{type?}', [CatalogController::class, 'index'])->middleware('permission:operacao.cadastro.view')->name('index');
    Route::post('/{type}', [CatalogController::class, 'store'])->middleware('permission:operacao.cadastro.create')->name('store');
    Route::put('/item/{item}', [CatalogController::class, 'update'])->middleware('permission:operacao.cadastro.update')->name('update');
});

Route::middleware('feature:operacao.agenda')->prefix('campos')->name('campos.')->group(function (): void {
    Route::get('/', [FieldController::class, 'edit'])->middleware('permission:operacao.campo.view')->name('edit');
    Route::put('/', [FieldController::class, 'update'])->middleware('permission:operacao.campo.update')->name('update');
    Route::post('/', [FieldController::class, 'store'])->middleware('permission:operacao.campo.create')->name('store');
    Route::post('/modelo', [FieldController::class, 'preset'])->middleware('permission:operacao.campo.update')->name('preset');

    // Só Super Adm: a permissão é reservada no manifesto.
    Route::middleware('permission:operacao.campo.delete')->group(function (): void {
        Route::delete('/{key}', [FieldController::class, 'destroy'])->name('destroy');
        Route::put('/{key}/restaurar', [FieldController::class, 'restore'])->name('restore');
        Route::delete('/{key}/definitivo', [FieldController::class, 'purge'])->name('purge');
    });
});
