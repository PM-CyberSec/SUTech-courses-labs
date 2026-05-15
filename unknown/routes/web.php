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

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/events', [EventPageController::class, 'index'])->name('events.index');
    Route::get('/alerts', [AlertPageController::class, 'index'])->name('alerts.index');
    Route::get('/network', [NetworkPageController::class, 'index'])->name('network.index');
    Route::get('/processes', [ProcessPageController::class, 'index'])->name('processes.index');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
