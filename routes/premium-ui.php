<?php

use App\Http\Controllers\Bd\AssignedTaskController;
use App\Http\Controllers\Bd\DashboardController;
use App\Http\Controllers\Bd\TaskEditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/tasks', [AssignedTaskController::class, 'index'])->name('tasks.index');

        Route::post('/tasks/{task}/comments', [AssignedTaskController::class, 'addComment'])
            ->name('tasks.comments.store');

        Route::post('/tasks/{task}/rework', [AssignedTaskController::class, 'submitRework'])
            ->name('tasks.rework');

        Route::post('/tasks/{task}/complete-with-rating', [AssignedTaskController::class, 'completeWithRating'])
            ->name('tasks.complete-with-rating');

        Route::get('/tasks/{task}/edit', [TaskEditController::class, 'edit'])
            ->name('tasks.edit');

        Route::put('/tasks/{task}', [TaskEditController::class, 'update'])
            ->name('tasks.update');
    });
