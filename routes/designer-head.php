<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesignerHead\DashboardController;
use App\Http\Controllers\DesignerHead\RequestActionController;
use App\Http\Controllers\DesignerHead\TaskController;

Route::middleware(['auth','role:designer_head'])
    ->prefix('designer-head')
    ->name('designer-head.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');

        Route::post('/requests/{taskRequest}/approve', [RequestActionController::class, 'approve'])
            ->name('requests.approve');

        Route::post('/requests/{taskRequest}/reject', [RequestActionController::class, 'reject'])
            ->name('requests.reject');
    });
