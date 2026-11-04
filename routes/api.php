<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\Designer\RequestController as DesignerRequestController;
use App\Http\Controllers\Api\Designer\TaskController as DesignerTaskController;
use App\Http\Controllers\Api\DesignerHead\HeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Mobile API (prefix api/v1, see bootstrap/app.php). Token auth via Sanctum.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices', [DeviceController::class, 'destroy']);

    // Large-file direct-to-Spaces multipart uploads (same controller as the web; it authorizes per task/purpose).
    Route::middleware('role:designer,bd')->prefix('uploads')->group(function () {
        Route::post('/initiate', [FileUploadController::class, 'initiate']);
        Route::get('/active', [FileUploadController::class, 'active']);
        Route::get('/{upload}/part-url', [FileUploadController::class, 'partUrl']);
        Route::get('/{upload}/parts', [FileUploadController::class, 'parts']);
        Route::post('/{upload}/complete', [FileUploadController::class, 'complete']);
        Route::post('/{upload}/abort', [FileUploadController::class, 'abort']);
    });

    Route::middleware('role:designer')->prefix('designer')->group(function () {
        Route::get('/tasks', [DesignerTaskController::class, 'index']);
        Route::get('/tasks/{task}', [DesignerTaskController::class, 'show']);
        Route::post('/tasks/{task}/move', [DesignerTaskController::class, 'move']);
        Route::post('/tasks/{task}/backward-request', [DesignerTaskController::class, 'backwardRequest']);
        Route::post('/tasks/{task}/eod', [DesignerTaskController::class, 'eod']);
        Route::post('/tasks/{task}/rework', [DesignerTaskController::class, 'rework']);
        Route::get('/tasks/{task}/request-options', [DesignerRequestController::class, 'options']);
        Route::post('/tasks/{task}/requests', [DesignerRequestController::class, 'store']);
        Route::get('/tasks/{task}/comments', [DesignerTaskController::class, 'comments']);
        Route::post('/tasks/{task}/comments', [DesignerTaskController::class, 'storeComment']);
    });

    Route::middleware('role:designer_head')->prefix('designer-head')->group(function () {
        Route::get('/tasks', [HeadController::class, 'board']);
        Route::get('/tasks/{task}', [HeadController::class, 'show']);
        Route::get('/tasks/{task}/comments', [HeadController::class, 'comments']);
        Route::post('/tasks/{task}/comments', [HeadController::class, 'storeComment']);
        Route::get('/requests', [HeadController::class, 'requests']);
        Route::post('/requests/{taskRequest}/approve', [HeadController::class, 'approve']);
        Route::post('/requests/{taskRequest}/reject', [HeadController::class, 'reject']);
    });

    // Pusher-protocol auth for the private App.Models.User.{id} Reverb channel (bearer token).
    Broadcast::routes(['middleware' => ['auth:sanctum']]);
});
