<?php

/*
|--------------------------------------------------------------------------
| Client Portal API Routes (separate auth for clients)
|--------------------------------------------------------------------------
|
| - Auth: portal guard only (PortalClient model). Admin (web/api) and store
|   auth do NOT grant access; clients must log in via /portal/login.
| - Session-based; uses web middleware for session support.
| - All data scoped to the authenticated portal client's client_id only.
|
*/

use App\Http\Controllers\Api\Portal\PortalAppointmentsController;
use App\Http\Controllers\Api\Portal\PortalAuthController;
use App\Http\Controllers\Api\Portal\PortalContractsController;
use App\Http\Controllers\Api\Portal\PortalDashboardController;
use App\Http\Controllers\Api\Portal\PortalInvoicePdfController;
use App\Http\Controllers\Api\Portal\PortalInvoicesController;
use App\Http\Controllers\Api\Portal\PortalKnowledgeBaseController;
use App\Http\Controllers\Api\Portal\PortalPaymentsController;
use App\Http\Controllers\Api\Portal\PortalProfileController;
use App\Http\Controllers\Api\Portal\PortalQuotationsController;
use App\Http\Controllers\Api\Portal\PortalStatementController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/portal')->middleware(['web', 'portal.auth', 'throttle:60,1'])->group(function () {
    // Public - no auth (portal login / set password only)
    Route::post('login', [PortalAuthController::class, 'login']);
    Route::get('validate-invite', [PortalAuthController::class, 'validateInviteToken']);
    Route::post('set-password', [PortalAuthController::class, 'setPassword']);

    // Protected - portal guard only (separate from admin and store)
    Route::middleware('auth:portal')->group(function () {
        Route::post('logout', [PortalAuthController::class, 'logout']);
        Route::get('me', [PortalAuthController::class, 'me']);

        Route::get('dashboard', [PortalDashboardController::class, 'index']);
        Route::get('invoices', [PortalInvoicesController::class, 'index']);
        Route::get('invoices/{id}', [PortalInvoicesController::class, 'show']);
        Route::get('invoices/{id}/pdf', [PortalInvoicePdfController::class, 'download']);
        Route::get('payments', [PortalPaymentsController::class, 'index']);
        Route::get('statement', [PortalStatementController::class, 'index']);
        Route::get('profile', [PortalProfileController::class, 'show']);
        Route::put('profile/password', [PortalProfileController::class, 'updatePassword']);

        // Quotations (read + request)
        Route::get('quotations', [PortalQuotationsController::class, 'index']);
        Route::get('quotations/{id}', [PortalQuotationsController::class, 'show']);
        Route::post('quotations', [PortalQuotationsController::class, 'store']);

        // Appointments (read + book)
        Route::get('appointments', [PortalAppointmentsController::class, 'index']);
        Route::get('appointments/{id}', [PortalAppointmentsController::class, 'show']);
        Route::post('appointments', [PortalAppointmentsController::class, 'store']);

        // Contracts (read + download)
        Route::get('contracts', [PortalContractsController::class, 'index']);
        Route::get('contracts/{id}', [PortalContractsController::class, 'show']);
        Route::get('contracts/{id}/attachments/{attachmentId}/download', [PortalContractsController::class, 'downloadAttachment']);

        // Knowledge base (read-only)
        Route::get('knowledge-base', [PortalKnowledgeBaseController::class, 'index']);
        Route::get('knowledge-base/{slug}', [PortalKnowledgeBaseController::class, 'show']);
    });
});
