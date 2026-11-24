<?php

use App\Modules\Operacao\Http\Controllers\FieldController;
use Illuminate\Support\Facades\Route;

// Só configuração de formulário; nenhuma rota da plataforma lê paciente ou agenda.
Route::get('/campos', [FieldController::class, 'edit'])->name('settings');
Route::put('/campos', [FieldController::class, 'update'])->name('update');
Route::post('/campos', [FieldController::class, 'store'])->name('store');
Route::post('/campos/modelo', [FieldController::class, 'preset'])->name('preset');
Route::delete('/campos/{key}', [FieldController::class, 'destroy'])->name('destroy');
Route::put('/campos/{key}/restaurar', [FieldController::class, 'restore'])->name('restore');
Route::delete('/campos/{key}/definitivo', [FieldController::class, 'purge'])->name('purge');
