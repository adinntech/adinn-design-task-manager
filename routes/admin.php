<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManageEmailController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\RequestActionController;
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
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/tasks', [TaskMonitoringController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}/edit', [TaskMonitoringController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [TaskMonitoringController::class, 'update'])->name('tasks.update');
        Route::get('/tasks/{task}', [TaskMonitoringController::class, 'show'])->name('tasks.show');
        Route::delete('/tasks/{task}', [TaskMonitoringController::class, 'destroy'])->name('tasks.destroy');

        Route::get('/manage-email', [ManageEmailController::class, 'index'])->name('manage-email.index');
        Route::get('/manage-email/table', [ManageEmailController::class, 'table'])->name('manage-email.table');
        Route::post('/manage-email', [ManageEmailController::class, 'store'])->name('manage-email.store');
        Route::post('/manage-email/import', [ManageEmailController::class, 'import'])->name('manage-email.import');
        Route::get('/manage-email/{allUsersMail}/edit', [ManageEmailController::class, 'edit'])->name('manage-email.edit');
        Route::put('/manage-email/{allUsersMail}', [ManageEmailController::class, 'update'])->name('manage-email.update');
        Route::delete('/manage-email/{allUsersMail}', [ManageEmailController::class, 'destroy'])->name('manage-email.destroy');

        Route::get('/master-controls', [MasterController::class, 'index'])->name('master.index');
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

        Route::post('/requests/{taskRequest}/approve', [RequestActionController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{taskRequest}/reject', [RequestActionController::class, 'reject'])->name('requests.reject');
    });
