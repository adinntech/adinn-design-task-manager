<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesignerHead\RequestActionController;
use App\Http\Controllers\DesignerHead\TaskController;
use App\Http\Controllers\DesignerHead\DashboardController;

Route::middleware(['auth','role:designer_head'])
    ->prefix('designer-head')
    ->name('designer-head.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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
