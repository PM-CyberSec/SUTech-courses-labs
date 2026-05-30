<?php

use App\Http\Controllers\Api\AiAskController;
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
    // Public read-only endpoints for dashboards/embedded views
    Route::get('/public/events', [EventController::class, 'publicIndex']);
    Route::get('/public/events/{event}', [EventController::class, 'publicShow']);
    Route::get('/public/stats', [EventController::class, 'publicStats']);
    Route::get('/public/alerts', [AlertController::class, 'index']);
    Route::get('/public/network', [NetworkController::class, 'index']);
    Route::get('/public/processes', [ProcessController::class, 'index']);

    Route::middleware(['verify.agent', 'throttle:120,1'])->group(function (): void {
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/alerts', [AlertController::class, 'store']);
    });

    Route::middleware(['web', 'auth', 'approved', 'role:admin,analyst,viewer'])->group(function (): void {
        Route::get('/stats', [EventController::class, 'stats'])->middleware('permission:events.view');
        Route::get('/events', [EventController::class, 'index'])->middleware('permission:events.view');
        Route::get('/events/{event}', [EventController::class, 'show'])->middleware('permission:events.view');
        Route::get('/alerts', [AlertController::class, 'index'])->middleware('permission:alerts.view');
        Route::get('/network', [NetworkController::class, 'index'])->middleware('permission:network.view');
        Route::get('/processes', [ProcessController::class, 'index'])->middleware('permission:processes.view');
    });
});

Route::prefix('ai')
    ->middleware(['web', 'auth', 'approved', 'role:admin,analyst', 'permission:llm.invoke'])
    ->group(function (): void {
        Route::post('/ask', AiAskController::class);
    });
