<?php

use App\Http\Controllers\AlertPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventPageController;
use App\Http\Controllers\NetworkPageController;
use App\Http\Controllers\ProcessPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::middleware(['auth', 'approved', 'role:admin,analyst,viewer'])->group(function (): void {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/events', [EventPageController::class, 'index'])->middleware('permission:events.view')->name('events.index');
    Route::get('/events/{event}', [EventPageController::class, 'show'])->middleware('permission:events.view')->name('events.show');
    Route::get('/alerts', [AlertPageController::class, 'index'])->middleware('permission:alerts.view')->name('alerts.index');
    Route::get('/network', [NetworkPageController::class, 'index'])->middleware('permission:network.view')->name('network.index');
    Route::get('/processes', [ProcessPageController::class, 'index'])->middleware('permission:processes.view')->name('processes.index');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'approved', 'role:admin'])->group(function (): void {
    Route::get('/admin/health', fn () => response()->json(['status' => 'ok']))
        ->middleware('permission:admin.health.view')
        ->name('admin.health');
});
