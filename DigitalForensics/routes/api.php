<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\NetworkController;
use App\Http\Controllers\Api\ProcessController;

/*
|--------------------------------------------------------------------------
| DLDS API (protected ingestion + query layer)
|--------------------------------------------------------------------------
*/

Route::prefix('dlds')->group(function () {

    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events', [EventController::class, 'index']);

    Route::post('/alerts', [AlertController::class, 'store']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/network', [NetworkController::class, 'index']);
    Route::get('/processes', [ProcessController::class, 'index']);
});