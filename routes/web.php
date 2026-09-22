<?php

use App\Http\Controllers\ActivityFlagController;
use App\Http\Controllers\Bd\AssignedTaskController;
use App\Http\Controllers\Bd\TaskController;
use App\Http\Controllers\Bd\TaskExportController;
use Illuminate\Support\Facades\Route;

// Shared across all authenticated roles — not role-scoped, since every role
// uses the same refresh-flag mechanics (see App\Services\TaskNotificationUrlResolver
// for the role→route dispatch the bell's notification links use directly).
Route::middleware('auth')->group(function () {
    Route::post('/activity/{scope}/ack', [ActivityFlagController::class, 'ack'])->name('activity.ack');
});

Route::middleware(['auth', 'role:bd'])
    ->prefix('bd')
    ->name('bd.')
    ->group(function () {
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');

        Route::post('/drafts', [TaskController::class, 'storeDraft'])->name('drafts.store');
        Route::put('/drafts/{task}', [TaskController::class, 'updateDraft'])->name('drafts.update');

        Route::get('/designers/{designer}/availability', [TaskController::class, 'availability'])->name('designers.availability');

        Route::get('/clients/search', [TaskController::class, 'searchClients'])->name('clients.search');

        // Registered ahead of the /tasks/{task} wildcard below (same group, same
        // request cycle) so "export" is never swallowed by task-show's route model
        // binding — see the matching /tasks/export registration in premium-ui.php.
        Route::get('/tasks/export', [TaskExportController::class, 'export'])->name('tasks.export');

        // Also ahead of /tasks/{task} for the same reason — "clone-search" is a
        // literal single-segment path and would otherwise be swallowed by show's
        // route model binding.
        Route::get('/tasks/clone-search', [TaskController::class, 'cloneSearch'])->name('tasks.cloneSearch');

        Route::get('/tasks/{task}', [AssignedTaskController::class, 'show'])->name('tasks.show');

        Route::get('/tasks/{task}/clone-data', [TaskController::class, 'cloneData'])->name('tasks.cloneData');
    });

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/designer.php';
require __DIR__.'/designer-head.php';
require __DIR__.'/premium-ui.php';
require __DIR__.'/profile.php';
