<?php

use App\Http\Controllers\ControlPlane\Api\HealthController;
use App\Http\Controllers\ControlPlane\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('control.api.health');
Route::post('/tenants', [TenantController::class, 'register'])->name('control.api.tenants.register');
Route::get('/tenants/by-crm-instance/{crmInstanceId}', [TenantController::class, 'showByCrmInstance'])
    ->whereNumber('crmInstanceId')
    ->name('control.api.tenants.by-crm-instance');
Route::put('/tenants/{tenant}/domain', [TenantController::class, 'saveDomain'])->name('control.api.tenants.domain');
Route::put('/tenants/{tenant}/database', [TenantController::class, 'saveDatabase'])->name('control.api.tenants.database');
Route::post('/tenants/{tenant}/database/test', [TenantController::class, 'testDatabase'])->name('control.api.tenants.database.test');
Route::post('/tenants/{tenant}/migrations', [TenantController::class, 'migrate'])->name('control.api.tenants.migrate');
Route::post('/tenants/{tenant}/seed-template', [TenantController::class, 'seedTemplate'])->name('control.api.tenants.seed-template');
Route::put('/tenants/{tenant}/administrator', [TenantController::class, 'configureAdministrator'])->name('control.api.tenants.administrator');
Route::put('/tenants/{tenant}/status', [TenantController::class, 'changeStatus'])->name('control.api.tenants.status');
Route::get('/operations/{run}', [TenantController::class, 'operation'])->name('control.api.operations.show');
