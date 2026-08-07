<?php

use App\Http\Controllers\Bd\AssignedTaskController;
use App\Http\Controllers\Bd\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}', [AssignedTaskController::class, 'show'])->name('tasks.show');
    });

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/designer.php';
require __DIR__.'/designer-head.php';
require __DIR__.'/premium-ui.php';
