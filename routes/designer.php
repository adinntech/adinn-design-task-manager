<?php

use App\Http\Controllers\Designer\DashboardController;
use App\Http\Controllers\Designer\TaskAttachmentDownloadController;
use App\Http\Controllers\Designer\TaskController;
use App\Http\Controllers\Designer\TaskExportController;
use App\Http\Controllers\Designer\TaskPageController;
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:designer'])
    ->prefix('designer')
    ->name('designer.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Designer-initiated task creation — sits in pending_bd_approval until the
        // selected BD confirms it (App\Livewire\Bd\TaskKanban::approveConfirmation()).
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');

        Route::get('/tasks', [TaskPageController::class, 'index'])->name('tasks.index');

        Route::get('/tasks/export', [TaskExportController::class, 'export'])->name('tasks.export');

        Route::get(
            '/tasks/{task}/attachments/download',
            TaskAttachmentDownloadController::class
        )->name('tasks.attachments.download');

        Route::get('/tasks/{task}', [TaskPageController::class, 'show'])->name('tasks.show');

        // Large-file (up to 6 GB) direct-to-Spaces multipart uploads for the
        // Progress Update / Rework ZIP fields — see CloudMultipartUploadService.
        Route::post('/uploads/initiate', [FileUploadController::class, 'initiate'])->name('uploads.initiate');
        Route::get('/uploads/active', [FileUploadController::class, 'active'])->name('uploads.active');
        Route::get('/uploads/{upload}/part-url', [FileUploadController::class, 'partUrl'])->name('uploads.part-url');
        Route::get('/uploads/{upload}/parts', [FileUploadController::class, 'parts'])->name('uploads.parts');
        Route::post('/uploads/{upload}/complete', [FileUploadController::class, 'complete'])->name('uploads.complete');
        Route::post('/uploads/{upload}/abort', [FileUploadController::class, 'abort'])->name('uploads.abort');
    });
