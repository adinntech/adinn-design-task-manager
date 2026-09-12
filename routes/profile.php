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

// Designer-only self-service editing of experienced verticals + skills.
Route::middleware(['auth', 'role:designer'])
    ->put('/profile/designer-profile', [ProfileController::class, 'updateDesignerProfile'])
    ->name('profile.designer-profile.update');

// BD-only self-service editing of working verticals (reuses the same
// experienced_verticals column as Designer — no separate field/table).
Route::middleware(['auth', 'role:bd'])
    ->put('/profile/bd-profile', [ProfileController::class, 'updateBdProfile'])
    ->name('profile.bd-profile.update');
