<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// Browser desktop notifications (Web Push) — Designer and BD only
// (WebPushService::isEnabledFor() is re-checked in the controller).
Route::middleware(['auth', 'role:designer,bd'])
    ->prefix('push')
    ->name('push.')
    ->group(function () {
        Route::post('/subscription', [PushSubscriptionController::class, 'store'])->name('subscribe');
        Route::post('/subscription/delete', [PushSubscriptionController::class, 'destroy'])->name('unsubscribe');
        Route::post('/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('test');
    });
