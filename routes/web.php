<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Bd\TaskController;
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => auth()->check() ? redirect()->route('bd.tasks.create') : redirect()->route('login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'role:bd,admin'])->prefix('bd')->name('bd.')->group(function () {
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
});
