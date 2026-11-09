<?php

use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\Designer\ApprovalController as DesignerApprovalController;
use App\Http\Controllers\DesignerAvailabilityController;
use App\Http\Controllers\Api\Designer\DashboardController as DesignerDashboardController;
use App\Http\Controllers\Api\Designer\RequestController as DesignerRequestController;
use App\Http\Controllers\Api\Designer\TaskController as DesignerTaskController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Bd\CreateTaskController;
use App\Http\Controllers\Api\Bd\DashboardController as BdDashboardController;
use App\Http\Controllers\Api\Bd\TaskController as BdTaskController;
use App\Http\Controllers\Bd\TaskController as WebBdTaskController;
use App\Http\Controllers\Api\DesignerHead\HeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Mobile API (prefix api/v1, see bootstrap/app.php). Token auth via Sanctum.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Forgot password (public): mail an OTP to the matching account, then reset with it.
Route::post('/password/forgot', [PasswordResetController::class, 'send'])->middleware('throttle:5,1');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

// In-app update gate: public so it also works before sign-in. Values come from config/app_update.php.
Route::get('/app-update', [AppUpdateController::class, 'show'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // My Profile (same self-service rules as the web page, which is untouched).
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile/basic-info', [ProfileController::class, 'updateBasicInfo']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications']);
    Route::patch('/profile/verticals', [ProfileController::class, 'updateVerticals']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Reverb connection settings + this user's private channel, so the app needs no hard-coded socket config.
    Route::get('/realtime', [RealtimeController::class, 'config']);

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
        // Delegate approvals (Swap / Split of teammates) for Designers the Admin allowed; the controller and service re-check.
        // Availability meter when picking a Preferred / Final designer for a split or swap.
        Route::get('/designers/{designer}/availability', DesignerAvailabilityController::class);

        Route::get('/approvals', [DesignerApprovalController::class, 'index']);
        Route::get('/approvals/{taskRequest}', [DesignerApprovalController::class, 'show']);
        Route::post('/approvals/{taskRequest}/approve', [DesignerApprovalController::class, 'approve']);
        Route::post('/approvals/{taskRequest}/reject', [DesignerApprovalController::class, 'reject']);

        Route::get('/dashboard', [DesignerDashboardController::class, 'index']);
        Route::get('/dashboard/tasks', [DesignerDashboardController::class, 'tasks']);
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

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/tasks', [AdminController::class, 'tasks']);
        Route::get('/tasks/{task}', [AdminController::class, 'task']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleUser']);
        Route::get('/requests', [AdminController::class, 'requests']);
        Route::post('/requests/{taskRequest}/approve', [AdminController::class, 'approve']);
        Route::post('/requests/{taskRequest}/reject', [AdminController::class, 'reject']);
    });

    Route::middleware('role:bd')->prefix('bd')->group(function () {
        // Task creation: runs the same validation/storage as the web form (see CreateTaskController).
        Route::get('/task-form', [CreateTaskController::class, 'form']);
        Route::post('/tasks', [CreateTaskController::class, 'store']);
        Route::post('/tasks/in-house', [CreateTaskController::class, 'storeInhouse']);
        Route::post('/drafts', [CreateTaskController::class, 'storeDraft']);
        Route::post('/drafts/in-house', [CreateTaskController::class, 'storeInhouseDraft']);
        Route::get('/designers/{designer}/availability', [WebBdTaskController::class, 'availability']);
        Route::get('/clients/search', [WebBdTaskController::class, 'searchClients']);

        Route::get('/dashboard', [BdDashboardController::class, 'index']);
        Route::get('/dashboard/tasks', [BdDashboardController::class, 'tasks']);

        Route::get('/tasks', [BdTaskController::class, 'board']);
        Route::get('/tasks/{task}', [BdTaskController::class, 'show']);
        Route::get('/tasks/{task}/comments', [BdTaskController::class, 'comments']);
        Route::post('/tasks/{task}/comments', [BdTaskController::class, 'storeComment']);
        Route::post('/tasks/{task}/rework', [BdTaskController::class, 'rework']);
        Route::post('/tasks/{task}/complete', [BdTaskController::class, 'complete']);
        Route::post('/tasks/{task}/approve', [BdTaskController::class, 'approve']);
        Route::post('/tasks/{task}/reject', [BdTaskController::class, 'reject']);
    });

    Route::middleware('role:designer_head')->prefix('designer-head')->group(function () {
        Route::get('/designers/{designer}/availability', DesignerAvailabilityController::class);
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
