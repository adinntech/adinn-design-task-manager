<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\TaskMonitoringController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('/tasks', [TaskMonitoringController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}', [TaskMonitoringController::class, 'show'])->name('tasks.show');

        Route::get('/master-controls', [MasterController::class, 'index'])->name('master.index');
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    });
