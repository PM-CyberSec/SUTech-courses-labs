<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AlertPageController;
use App\Http\Controllers\EventPageController;
use App\Http\Controllers\NetworkPageController;
use App\Http\Controllers\ProcessPageController;



Route::get('/', [DashboardController::class, 'index']);
Route::get('/events', [EventPageController::class, 'index']);
Route::get('/alerts', [AlertPageController::class, 'index']);
Route::get('/network', [NetworkPageController::class, 'index']);
Route::get('/processes', [ProcessPageController::class, 'index']);