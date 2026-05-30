<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NetworkController;
use App\Http\Controllers\Api\ProcessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DLDS API (protected ingestion + query layer)
|--------------------------------------------------------------------------
*/

Route::prefix('dlds')->group(function (): void {
    Route::middleware(['verify.agent', 'throttle:120,1'])->group(function (): void {
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/alerts', [AlertController::class, 'store']);
    });

    Route::middleware(['web', 'auth'])->group(function (): void {
        Route::get('/stats', [EventController::class, 'stats']);
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::get('/network', [NetworkController::class, 'index']);
        Route::get('/processes', [ProcessController::class, 'index']);
    });
});
