<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalDashboardController extends Controller
{
    /**
     * GET /api/portal/dashboard - Summary stats for the portal client.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $clientId = $portalClient->client_id;

        $totalInvoices = Sale::whereNull('deleted_at')
            ->where('client_id', $clientId)
            ->where('statut', 'completed')
            ->count();

        $totalAmount = Sale::whereNull('deleted_at')
            ->where('client_id', $clientId)
            ->where('statut', 'completed')
            ->sum('GrandTotal');

        $totalPaid = Sale::whereNull('deleted_at')
            ->where('client_id', $clientId)
            ->where('statut', 'completed')
            ->sum('paid_amount');

        $salesDue = $totalAmount - $totalPaid;
        $openingBalance = (float) (Client::whereKey($clientId)->value('opening_balance') ?? 0);
        $totalDue = $salesDue + $openingBalance;

        $recentInvoices = Sale::whereNull('deleted_at')
            ->where('client_id', $clientId)
            ->where('statut', 'completed')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'Ref', 'date', 'GrandTotal', 'paid_amount', 'payment_statut']);

        return response()->json([
            'total_invoices' => $totalInvoices,
            'total_amount' => (float) $totalAmount,
            'total_paid' => (float) $totalPaid,
            'sales_due' => (float) $salesDue,
            'opening_balance' => (float) $openingBalance,
            'total_due' => (float) $totalDue,
            'recent_invoices' => $recentInvoices->map(function ($s) {
                return [
                    'id' => $s->id,
                    'Ref' => $s->Ref,
                    'date' => $s->date,
                    'GrandTotal' => (float) $s->GrandTotal,
                    'paid_amount' => (float) $s->paid_amount,
                    'due' => (float) $s->GrandTotal - (float) $s->paid_amount,
                    'payment_status' => $s->payment_statut,
                ];
            }),
        ]);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
