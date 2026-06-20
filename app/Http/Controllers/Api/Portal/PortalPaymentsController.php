<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalPaymentsController extends Controller
{
    /**
     * GET /api/portal/payments - List payments for the authenticated client.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $clientId = $portalClient->client_id;
        $perPage = (int) $request->input('limit', 10);
        $page = max(1, (int) $request->input('page', 1));
        $search = $request->input('search');

        $salesPaymentsQuery = DB::table('payment_sales')
            ->whereNull('payment_sales.deleted_at')
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'payment_sales.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.client_id', $clientId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qr) use ($search) {
                    $qr->where('payment_sales.Ref', 'LIKE', "%{$search}%")
                        ->orWhere('payment_sales.date', 'LIKE', "%{$search}%")
                        ->orWhere('payment_methods.name', 'LIKE', "%{$search}%");
                });
            })
            ->select(
                'payment_sales.id',
                'payment_sales.date',
                'payment_sales.Ref as Ref',
                'sales.Ref as Sale_Ref',
                'payment_methods.name as payment_method',
                'payment_sales.montant',
                DB::raw("'sale' as payment_type")
            )
            ->orderByDesc('payment_sales.id');

        $salesPayments = $salesPaymentsQuery->get()->map(function ($item) {
            return (array) $item;
        });

        $openingBalancePayments = collect();
        if (Schema::hasTable('client_opening_balance_payments')) {
            $openingBalancePayments = DB::table('client_opening_balance_payments')
                ->whereNull('client_opening_balance_payments.deleted_at')
                ->join('payment_methods', 'client_opening_balance_payments.payment_method_id', '=', 'payment_methods.id')
                ->where('client_opening_balance_payments.client_id', $clientId)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($qr) use ($search) {
                        $qr->where('client_opening_balance_payments.Ref', 'LIKE', "%{$search}%")
                            ->orWhere('client_opening_balance_payments.date', 'LIKE', "%{$search}%")
                            ->orWhere('payment_methods.name', 'LIKE', "%{$search}%");
                    });
                })
                ->select(
                    'client_opening_balance_payments.id',
                    'client_opening_balance_payments.date',
                    'client_opening_balance_payments.Ref as Ref',
                    DB::raw("NULL as Sale_Ref"),
                    'payment_methods.name as payment_method',
                    'client_opening_balance_payments.montant',
                    DB::raw("'opening_balance' as payment_type")
                )
                ->orderByDesc('client_opening_balance_payments.id')
                ->get()
                ->map(fn ($item) => (array) $item);
        }

        $allPayments = $salesPayments->merge($openingBalancePayments)
            ->sortByDesc('id')
            ->values()
            ->all();

        $totalRows = count($allPayments);

        if ($perPage > 0) {
            $rows = array_slice($allPayments, ($page - 1) * $perPage, $perPage);
        } else {
            $rows = $allPayments;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $rows,
        ]);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
