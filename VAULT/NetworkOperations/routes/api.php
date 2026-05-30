<?php

use App\Http\Controllers\ConfigTemplateController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DeploymentLogController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HostVariableController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\RollbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function (): void {
    Route::middleware('role:admin,engineer,viewer')->group(function (): void {
        Route::apiResource('devices', DeviceController::class)->only(['index', 'show']);
        Route::apiResource('inventories', InventoryController::class)->only(['index', 'show']);
        Route::apiResource('host-variables', HostVariableController::class)->only(['index', 'show']);
        Route::apiResource('config-templates', ConfigTemplateController::class)->only(['index', 'show']);
        Route::apiResource('deployments', DeploymentController::class)->only(['index', 'show']);
        Route::apiResource('deployment-logs', DeploymentLogController::class)->only(['index', 'show']);
        Route::apiResource('rollbacks', RollbackController::class)->only(['index', 'show']);
    });

    Route::middleware('role:admin,engineer')->group(function (): void {
        Route::apiResource('devices', DeviceController::class)->only(['store', 'update']);
        Route::apiResource('inventories', InventoryController::class)->only(['store', 'update']);
        Route::apiResource('host-variables', HostVariableController::class)->only(['store', 'update']);
        Route::apiResource('config-templates', ConfigTemplateController::class)->only(['store', 'update']);
        Route::apiResource('deployments', DeploymentController::class)->only(['store', 'update']);
        Route::post('deployments/{deployment}/execute', [DeploymentController::class, 'execute'])->name('deployments.execute');
        Route::post('deployments/{deployment}/rollback', [DeploymentController::class, 'rollback'])->name('deployments.rollback');
        Route::apiResource('rollbacks', RollbackController::class)->only(['store']);
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('devices', DeviceController::class)->only(['destroy']);
        Route::apiResource('inventories', InventoryController::class)->only(['destroy']);
        Route::apiResource('host-variables', HostVariableController::class)->only(['destroy']);
        Route::apiResource('config-templates', ConfigTemplateController::class)->only(['destroy']);
        Route::apiResource('deployments', DeploymentController::class)->only(['destroy']);
    });
});
