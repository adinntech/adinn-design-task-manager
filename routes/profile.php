<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Shared "My Profile" page — Designer, Designer Head and BD only (not Admin,
// which manages users via the User Management admin screens instead).
Route::middleware(['auth', 'role:designer,designer_head,bd'])
    ->prefix('profile')
    ->name('profile.')
    ->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });
