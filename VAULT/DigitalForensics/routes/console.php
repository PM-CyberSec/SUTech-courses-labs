<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| DLDS AUTOMATION ENGINE (SIEM BACKEND)
|--------------------------------------------------------------------------
| Runs correlation, cleanup, anomaly detection
*/

/**
 * 🧠 Correlation engine
 * Links:
 * - process events
 * - network flows
 * - alerts
 */
Schedule::call(function () {

    Artisan::call('dlds:correlate');

})->everyMinute();

Schedule::call(function () {

    Artisan::call('dlds:cleanup');

})->daily();

Schedule::call(function () {

    Artisan::call('dlds:score-events');

})->everyFiveMinutes();

Schedule::call(function () {

    Artisan::call('dlds:health-check');

})->everyTenMinutes();