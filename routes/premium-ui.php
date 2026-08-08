<?php

use App\Http\Controllers\Bd\AssignedTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/tasks', [AssignedTaskController::class, 'index'])->name('tasks.index');
    });

