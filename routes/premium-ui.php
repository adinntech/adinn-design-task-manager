<?php

use App\Http\Controllers\Bd\AssignedTaskController;
use App\Http\Controllers\Bd\TaskEditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/tasks', [AssignedTaskController::class, 'index'])->name('tasks.index');

        Route::post('/tasks/{task}/comments', [AssignedTaskController::class, 'addComment'])
            ->name('tasks.comments.store');

        Route::get('/tasks/{task}/edit', [TaskEditController::class, 'edit'])
            ->name('tasks.edit');

        Route::put('/tasks/{task}', [TaskEditController::class, 'update'])
            ->name('tasks.update');
    });
