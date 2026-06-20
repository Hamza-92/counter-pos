<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalQuotationsController extends Controller
{
    /**
     * GET /api/portal/quotations - list quotations belonging to the authenticated client.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $perPage = (int) $request->input('limit', 10);
        $page = max(1, (int) $request->input('page', 1));
        $search = $request->input('search');

        $q = Quotation::query()
            ->whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->with(['warehouse:id,name'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qr) use ($search) {
                    $qr->where('Ref', 'LIKE', "%{$search}%")
                        ->orWhere('statut', 'LIKE', "%{$search}%")
                        ->orWhere('notes', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $totalRows = (clone $q)->count();
        $rows = $perPage > 0
            ? $q->offset(($page - 1) * $perPage)->limit($perPage)->get()
            : $q->get();

        $data = $rows->map(function ($qt) {
            return [
                'id' => $qt->id,
                'Ref' => $qt->Ref,
                'date' => $qt->date,
                'warehouse_name' => optional($qt->warehouse)->name,
                'GrandTotal' => (float) $qt->GrandTotal,
                'statut' => $qt->statut,
                'notes' => $qt->notes,
            ];
        });

        return response()->json([
            'totalRows' => $totalRows,
            'quotations' => $data,
        ]);
    }

    /**
     * GET /api/portal/quotations/{id} - quotation detail (must belong to client).
     */
    public function show(Request $request, $id)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $qt = Quotation::whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where('id', $id)
            ->with(['details.product', 'warehouse:id,name'])
            ->firstOrFail();

        return response()->json([
            'id' => $qt->id,
            'Ref' => $qt->Ref,
            'date' => $qt->date,
            'warehouse_name' => optional($qt->warehouse)->name,
            'GrandTotal' => (float) $qt->GrandTotal,
            'discount' => (float) $qt->discount,
            'shipping' => (float) $qt->shipping,
            'TaxNet' => (float) $qt->TaxNet,
            'tax_rate' => (float) $qt->tax_rate,
            'statut' => $qt->statut,
            'notes' => $qt->notes,
            'details' => $qt->details->map(function ($d) {
                return [
                    'product_name' => optional($d->product)->name,
                    'quantity' => (float) $d->quantity,
                    'price' => (float) $d->price,
                    'total' => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * POST /api/portal/quotations - client requests a new quotation.
     * The quotation is stored with statut='pending' so admins can review and price it.
     */
    public function store(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:190'],
            'notes' => ['required', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
        ]);

        $defaultWarehouseId = Setting::value('warehouse_id')
            ?? optional(Warehouse::whereNull('deleted_at')->orderBy('id')->first())->id;

        // quotations.user_id is NOT NULL — portal-originated requests have no staff author,
        // so fall back to the first active user (typically the owner/admin seed account).
        // Admins can reassign when they review the request.
        $systemUserId = User::whereNull('deleted_at')->orderBy('id')->value('id');
        if (! $systemUserId) {
            return response()->json([
                'message' => 'No staff user available to receive the quotation request.',
            ], 500);
        }

        $notes = '';
        if (! empty($data['subject'])) {
            $notes .= '[' . $data['subject'] . "]\n";
        }
        $notes .= $data['notes'];
        if (! empty($data['items'])) {
            $notes .= "\n\nRequested items:";
            foreach ($data['items'] as $row) {
                $notes .= "\n- " . $row['description'] . ' x ' . $row['quantity'];
            }
        }

        $quotation = DB::transaction(function () use ($portalClient, $defaultWarehouseId, $systemUserId, $notes) {
            $year = date('Y');
            $prefix = "QT_REQ_{$year}_";
            $lastRef = Quotation::where('Ref', 'LIKE', $prefix . '%')
                ->orderByDesc('id')->value('Ref');
            $next = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;
            $ref = sprintf('QT_REQ_%s_%05d', $year, $next);

            return Quotation::create([
                'date' => now()->toDateString(),
                'Ref' => $ref,
                'client_id' => $portalClient->client_id,
                'warehouse_id' => $defaultWarehouseId,
                'user_id' => $systemUserId,
                'GrandTotal' => 0,
                'discount' => 0,
                'shipping' => 0,
                'TaxNet' => 0,
                'tax_rate' => 0,
                'statut' => 'pending',
                'notes' => $notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'id' => $quotation->id,
            'Ref' => $quotation->Ref,
        ], 201);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
