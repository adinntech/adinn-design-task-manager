<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // Forgot password: OTP by email (Username / Email / Employee Code), then a new password.
    Route::get('/forgot-password', [PasswordResetOtpController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetOtpController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetOtpController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetOtpController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/', [AuthenticatedSessionController::class, 'redirectAuthenticatedUser'])->name('home');
});
