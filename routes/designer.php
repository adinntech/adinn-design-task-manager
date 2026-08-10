<?php

use App\Http\Controllers\Designer\TaskAttachmentDownloadController;
use App\Http\Controllers\Designer\TaskPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:designer'])
    ->prefix('designer')
    ->name('designer.')
    ->group(function () {
        Route::get('/tasks', [TaskPageController::class, 'index'])->name('tasks.index');

        Route::get(
            '/tasks/{task}/attachments/download',
            TaskAttachmentDownloadController::class
        )->name('tasks.attachments.download');

        Route::get('/tasks/{task}', [TaskPageController::class, 'show'])->name('tasks.show');
    });
