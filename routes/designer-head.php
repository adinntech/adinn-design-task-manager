<?php

use App\Http\Controllers\DesignerHead\DashboardController;
use App\Http\Controllers\DesignerHead\RequestActionController;
use App\Http\Controllers\DesignerHead\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:designer_head'])
    ->prefix('designer-head')
    ->name('designer-head.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/dashboard/partial', [DashboardController::class, 'fragment'])
            ->name('dashboard.partial');

        Route::get('/dashboard/ratings', [DashboardController::class, 'ratings'])
            ->name('dashboard.ratings');

        Route::view('/assigned-tasks', 'designer-head.assigned-tasks')
            ->name('assigned-tasks');

        // Backward compatibility for older dashboard/bookmarked links.
        Route::redirect('/all-tasks', '/designer-head/assigned-tasks')
            ->name('all-tasks');

        Route::get('/tasks/{task}', [TaskController::class, 'show'])
            ->name('tasks.show');

        Route::post('/tasks/{task}/comments', [TaskController::class, 'addComment'])
            ->name('tasks.comments.store');

        Route::post('/requests/{taskRequest}/approve', [RequestActionController::class, 'approve'])
            ->name('requests.approve');

        Route::post('/requests/{taskRequest}/reject', [RequestActionController::class, 'reject'])
            ->name('requests.reject');
    });
