<?php

use App\Http\Controllers\ControlPlane\AuthController;
use App\Http\Controllers\ControlPlane\DashboardController;
use App\Http\Controllers\ControlPlane\PlanController;
use App\Http\Controllers\ControlPlane\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:control')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('control.login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('control.login.submit');
});

Route::middleware('control.secure')->group(function () {
    Route::get('/', DashboardController::class)->name('control.dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('control.logout');

    Route::get('/tenants', [TenantController::class, 'index'])->name('control.tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('control.tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('control.tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('control.tenants.show');
    Route::post('/tenants/{tenant}/domains', [TenantController::class, 'addDomain'])->name('control.tenants.domains.store');
    Route::post('/domains/{domain}/verify', [TenantController::class, 'verifyDomain'])->name('control.domains.verify');
    Route::put('/tenants/{tenant}/database', [TenantController::class, 'saveDatabase'])->name('control.tenants.database');
    Route::post('/tenants/{tenant}/database/test', [TenantController::class, 'testDatabase'])->middleware('throttle:10,1')->name('control.tenants.database.test');
    Route::post('/tenants/{tenant}/subscriptions', [TenantController::class, 'addSubscription'])->name('control.tenants.subscriptions.store');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'addPayment'])->name('control.tenants.payments.store');
    Route::post('/payments/{payment}/reverse', [TenantController::class, 'reversePayment'])->name('control.payments.reverse');
    Route::put('/tenants/{tenant}/status', [TenantController::class, 'changeStatus'])->name('control.tenants.status');

    Route::get('/plans', [PlanController::class, 'index'])->name('control.plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('control.plans.store');
    Route::post('/plans/{plan}/toggle', [PlanController::class, 'toggle'])->name('control.plans.toggle');
});

// An ordinary catch-all (not Route::fallback) is required here. Laravel moves
// fallback routes behind domainless tenant routes during compiled matching.
Route::any('/{controlPath?}', static fn () => abort(404))->where('controlPath', '.*');
