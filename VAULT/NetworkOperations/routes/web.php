<?php

use App\Http\Controllers\ConfigTemplatePageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeploymentLogPageController;
use App\Http\Controllers\DeploymentPageController;
use App\Http\Controllers\DevicePageController;
use App\Http\Controllers\InventoryPageController;
use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\SmartAutomationController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\AiTopologyController;
use Illuminate\Support\Facades\Route;

Route::get('/switch-role/{role}', RoleSwitchController::class)->name('role.switch');

use App\Http\Controllers\TopologyExportController;
Route::middleware('role:admin,engineer,viewer')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/devices', [DevicePageController::class, 'index'])->name('devices.index');
    Route::get('/inventories', [InventoryPageController::class, 'index'])->name('inventories.index');
    Route::get('/templates', [ConfigTemplatePageController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}', [ConfigTemplatePageController::class, 'show'])->whereNumber('template')->name('templates.show');
    Route::get('/deployments', [DeploymentPageController::class, 'index'])->name('deployments.index');
    Route::get('/deployments/{deployment}', [DeploymentPageController::class, 'show'])->whereNumber('deployment')->name('deployments.show');
    Route::get('/logs', [DeploymentLogPageController::class, 'index'])->name('logs.index');
    Route::get('/devices/{device}/auto-config', [SmartAutomationController::class, 'show'])->whereNumber('device')->name('devices.auto-config');
    Route::get('/topologies', [TopologyController::class, 'index'])->name('topologies.index');
    Route::get('/topologies/{topology}', [TopologyController::class, 'show'])->whereNumber('topology')->name('topologies.show');
    Route::get('/topologies/{topology}/generated-configs/{generatedConfig}/download', [TopologyController::class, 'downloadConfig'])
        ->whereNumber('topology')
        ->whereNumber('generatedConfig')
        ->name('topologies.generated-configs.download');
});

Route::middleware('role:admin,engineer')->group(function (): void {

    Route::get('/ai-topology', [AiTopologyController::class, 'index'])->name('ai-topology.index');
    Route::post('/ai-topology/generate', [AiTopologyController::class, 'generate'])->name('ai-topology.generate');
    Route::get('/ai-topology/{topology}', [AiTopologyController::class, 'show'])->whereNumber('topology')->name('ai-topology.show');
    Route::get('/ai-topology/{topology}/export/json', [TopologyExportController::class, 'json'])->whereNumber('topology')->name('ai-topology.export.json');
    Route::get('/ai-topology/{topology}/export/zip', [TopologyExportController::class, 'zip'])->whereNumber('topology')->name('ai-topology.export.zip');
    Route::get('/devices/create', [DevicePageController::class, 'create'])->name('devices.create');
    Route::post('/devices', [DevicePageController::class, 'store'])->name('devices.store');
    Route::get('/devices/{device}/edit', [DevicePageController::class, 'edit'])->whereNumber('device')->name('devices.edit');
    Route::put('/devices/{device}', [DevicePageController::class, 'update'])->whereNumber('device')->name('devices.update');

    Route::get('/inventories/create', [InventoryPageController::class, 'create'])->name('inventories.create');
    Route::post('/inventories', [InventoryPageController::class, 'store'])->name('inventories.store');
    Route::get('/inventories/{inventory}/edit', [InventoryPageController::class, 'edit'])->whereNumber('inventory')->name('inventories.edit');
    Route::put('/inventories/{inventory}', [InventoryPageController::class, 'update'])->whereNumber('inventory')->name('inventories.update');

    Route::get('/templates/create', [ConfigTemplatePageController::class, 'create'])->name('templates.create');
    Route::post('/templates', [ConfigTemplatePageController::class, 'store'])->name('templates.store');
    Route::get('/templates/{template}/edit', [ConfigTemplatePageController::class, 'edit'])->whereNumber('template')->name('templates.edit');
    Route::put('/templates/{template}', [ConfigTemplatePageController::class, 'update'])->whereNumber('template')->name('templates.update');
    Route::post('/templates/{template}/preview', [ConfigTemplatePageController::class, 'preview'])->whereNumber('template')->name('templates.preview');

    Route::get('/deployments/create', [DeploymentPageController::class, 'create'])->name('deployments.create');
    Route::get('/deployments/wizard', [DeploymentPageController::class, 'create'])->name('deployments.wizard');
    Route::post('/deployments', [DeploymentPageController::class, 'store'])->name('deployments.store');
    Route::post('/deployments/{deployment}/execute', [DeploymentPageController::class, 'execute'])->whereNumber('deployment')->name('deployments.execute');
    Route::post('/deployments/{deployment}/rollback', [DeploymentPageController::class, 'rollback'])->whereNumber('deployment')->name('deployments.rollback');
    Route::post('/devices/{device}/auto-config', [SmartAutomationController::class, 'generate'])->whereNumber('device')->name('devices.auto-config.generate');
    Route::get('/topologies/create', [TopologyController::class, 'create'])->name('topologies.create');
    Route::post('/topologies', [TopologyController::class, 'store'])->name('topologies.store');
    Route::post('/topologies/{topology}/generate-configs', [TopologyController::class, 'generateConfigs'])
        ->whereNumber('topology')
        ->name('topologies.generate-configs');
});

Route::middleware('role:admin')->group(function (): void {
    Route::delete('/devices/{device}', [DevicePageController::class, 'destroy'])->whereNumber('device')->name('devices.destroy');
    Route::delete('/inventories/{inventory}', [InventoryPageController::class, 'destroy'])->whereNumber('inventory')->name('inventories.destroy');
    Route::delete('/templates/{template}', [ConfigTemplatePageController::class, 'destroy'])->whereNumber('template')->name('templates.destroy');
    Route::delete('/deployments/{deployment}', [DeploymentPageController::class, 'destroy'])->whereNumber('deployment')->name('deployments.destroy');
});
