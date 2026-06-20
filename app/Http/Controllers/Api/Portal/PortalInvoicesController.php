<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalInvoicesController extends Controller
{
    /**
     * GET /api/portal/invoices - List invoices for the authenticated client.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $perPage = (int) $request->input('limit', 10);
        $page = max(1, (int) $request->input('page', 1));
        $search = $request->input('search');

        $q = Sale::query()
            ->whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where('statut', 'completed')
            ->with(['warehouse:id,name'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qr) use ($search) {
                    $qr->where('Ref', 'LIKE', "%{$search}%")
                        ->orWhere('payment_statut', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $totalRows = (clone $q)->count();

        if ($perPage > 0) {
            $rows = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();
        } else {
            $rows = $q->get();
        }

        $data = $rows->map(function ($sale) {
            return [
                'id' => $sale->id,
                'Ref' => $sale->Ref,
                'date' => $sale->date,
                'warehouse_name' => optional($sale->warehouse)->name,
                'GrandTotal' => (float) $sale->GrandTotal,
                'paid_amount' => (float) $sale->paid_amount,
                'due' => (float) $sale->GrandTotal - (float) $sale->paid_amount,
                'payment_status' => $sale->payment_statut,
            ];
        });

        return response()->json([
            'totalRows' => $totalRows,
            'invoices' => $data,
        ]);
    }

    /**
     * GET /api/portal/invoices/{id} - Single invoice detail (must belong to client).
     */
    public function show(Request $request, $id)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $sale = Sale::whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where('id', $id)
            ->with(['details.product.unitSale', 'client', 'warehouse'])
            ->firstOrFail();

        $details = $sale->details->map(function ($d) {
            // Per-line discount amount (mirrors SalesController logic)
            $discountNet = ((string) $d->discount_method === '2')
                ? (float) $d->discount
                : ((float) $d->price * (float) $d->discount / 100);

            // Per-line tax: TaxNet is a percentage rate applied to the discounted price
            $taxPrice = (float) $d->TaxNet * (((float) $d->price - $discountNet) / 100);

            if ((string) $d->tax_method === '1') {
                // Exclusive: tax added on top of discounted price
                $netPrice = (float) $d->price - $discountNet;
                $taxe = $taxPrice;
            } else {
                // Inclusive: tax already inside the price
                $netPrice = (float) $d->price - $discountNet - $taxPrice;
                $taxe = (float) $d->price - $netPrice - $discountNet;
            }

            return [
                'product_name' => optional($d->product)->name,
                'quantity' => (float) $d->quantity,
                'price' => (float) $d->price,
                'total' => (float) $d->total,
                'DiscountNet' => round($discountNet, 2),
                'taxe' => round($taxe, 2),
                'tax_method' => (string) $d->tax_method,
                'discount_method' => (string) $d->discount_method,
            ];
        });

        $subtotal = $details->sum('total');

        $data = [
            'id' => $sale->id,
            'Ref' => $sale->Ref,
            'date' => $sale->date,
            'time' => $sale->time ?? '',
            'notes' => $sale->notes,
            'GrandTotal' => (float) $sale->GrandTotal,
            'paid_amount' => (float) $sale->paid_amount,
            'due' => (float) $sale->GrandTotal - (float) $sale->paid_amount,
            'payment_status' => $sale->payment_statut,
            'statut' => $sale->statut,
            'subtotal' => (float) $subtotal,
            'TaxNet' => (float) ($sale->TaxNet ?? 0),
            'tax_rate' => (float) ($sale->tax_rate ?? 0),
            'discount' => (float) ($sale->discount ?? 0),
            'discount_Method' => (string) ($sale->discount_Method ?? '2'),
            'discount_from_points' => (float) ($sale->discount_from_points ?? 0),
            'shipping' => (float) ($sale->shipping ?? 0),
            'client' => [
                'name' => $sale->client->name ?? '',
                'email' => $sale->client->email ?? '',
                'phone' => $sale->client->phone ?? '',
                'adresse' => $sale->client->adresse ?? '',
            ],
            'details' => $details,
        ];

        return response()->json($data);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
