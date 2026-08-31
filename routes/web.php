<?php

use App\Http\Controllers\Bd\AssignedTaskController;
use App\Http\Controllers\Bd\TaskController;
use App\Http\Controllers\Bd\TaskExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');

        // Registered ahead of the /tasks/{task} wildcard below (same group, same
        // request cycle) so "export" is never swallowed by task-show's route model
        // binding — see the matching /tasks/export registration in premium-ui.php.
        Route::get('/tasks/export', [TaskExportController::class, 'export'])->name('tasks.export');

        Route::get('/tasks/{task}', [AssignedTaskController::class, 'show'])->name('tasks.show');
    });

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/designer.php';
require __DIR__.'/designer-head.php';
require __DIR__.'/premium-ui.php';
