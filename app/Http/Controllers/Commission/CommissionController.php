<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use App\Models\Sale;
use App\Models\SaleCommission;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionController extends BaseController
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /** Calculate commissions for a sale (idempotent: does not duplicate). */
    public function calculateForSale(Request $request, $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'create', CommissionProgram::class);
        $sale = Sale::findOrFail($saleId);
        $created = $this->commissionService->calculateForSale($sale);
        return $this->sendResponse([
            'created_count' => count($created),
            'commissions' => $created,
        ], __('Commissions calculated'));
    }

    /** Approve one or more commissions (status pending -> approved). */
    public function approve(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', CommissionProgram::class);
        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer|exists:sale_commissions,id',
        ]);
        $updated = SaleCommission::whereIn('id', $request->commission_ids)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);
        return $this->sendResponse(['updated_count' => $updated], __('Approved'));
    }

    /** Cancel one or more commissions. */
    public function cancel(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', CommissionProgram::class);
        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer|exists:sale_commissions,id',
        ]);
        $updated = SaleCommission::whereIn('id', $request->commission_ids)
            ->whereIn('status', ['pending', 'approved'])
            ->update(['status' => 'cancelled']);
        return $this->sendResponse(['updated_count' => $updated], __('Cancelled'));
    }
}
