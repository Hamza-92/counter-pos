<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyOrdersApiController extends Controller
{
    /**
     * GET /store/my/orders
     * Query: page, per_page, q (ref), status (pending|confirmed|cancelled), from, to, sort, dir
     */
    public function index(Request $req)
    {
        $user = Auth::guard('store')->user();
        abort_unless($user, 401);

        $q = trim((string) $req->query('q', ''));
        $status = $req->query('status'); // pending|confirmed|cancelled
        $from = $req->query('from');
        $to = $req->query('to');
        $sort = $req->query('sort', 'created_at');
        $dir = $req->query('dir', 'desc');
        $per = (int) $req->query('per_page', 10);

        $allowedSort = ['created_at', 'total', 'ref', 'date'];

        $orders = OnlineOrder::query()
            ->with('warehouse:id,name')
            ->where('client_id', (int) $user->client_id)
            ->when($q !== '', fn ($qq) => $qq->where('ref', 'like', "%{$q}%"))
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($from, fn ($qq) => $qq->whereDate('date', '>=', $from))
            ->when($to, fn ($qq) => $qq->whereDate('date', '<=', $to))
            ->when(in_array($sort, $allowedSort, true),
                fn ($qq) => $qq->orderBy($sort, $dir),
                fn ($qq) => $qq->latest())
            ->paginate($per);

        $rows = $orders->getCollection()->map(function (OnlineOrder $o) {
            return [
                'id' => $o->id,
                'code' => $o->ref,
                'status' => $o->status,
                'total' => (float) $o->total,
                'payment_method' => $o->payment_method ?? 'cod',
                'payment_status' => $o->payment_status ?? 'pending',
                'created_at' => optional($o->created_at)->toDateTimeString() ?: (string) $o->date,
                'warehouse_name' => optional($o->warehouse)->name,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $orders->total(),
                'page' => $orders->currentPage(),
                'pages' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * (Optional) GET /store/my/orders/{id}
     * Useful if you add an order drawer/page on the account side.
     */
    public function show($id, Request $request)
    {
        $user = Auth::guard('store')->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Only allow the customer to see their own order
        $order = OnlineOrder::with(['items.product', 'items.productVariant', 'client', 'warehouse'])
            ->where('id', $id)
            ->where('client_id', $user->client_id) // assumes OnlineOrder.client_id references the same client as the ecommerce user
            ->firstOrFail();

        // Safely compute subtotal
        $subtotal = (float) $order->items->reduce(function ($a, $i) {
            return $a + ((float) $i->price * (float) $i->qty);
        }, 0.0);

        // Normalize date/time to strings (works whether date is Carbon or string)
        $dateStr = method_exists($order->date, 'toDateString')
            ? $order->date->toDateString()
            : (string) $order->date;

        $timeStr = (string) $order->time;

        return response()->json([
            'id' => $order->id,
            'code' => $order->ref,
            'status' => $order->status,                 // pending|confirmed|cancelled
            'date' => $dateStr,
            'time' => $timeStr,

            'warehouse_id' => $order->warehouse_id,
            'warehouse_name' => optional($order->warehouse)->name,

            'customer_name' => optional($order->client)->name,
            'customer_email' => optional($order->client)->email,
            'customer_phone' => optional($order->client)->phone,

            'subtotal' => $subtotal,
            'shipping' => 0.0,
            'discount' => 0.0,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method ?? 'cod',
            'payment_status' => $order->payment_status ?? 'pending',

            'items' => $order->items->map(function ($d) {
                $name = optional($d->product)->name ?? ('#'.$d->product_id);
                $variant = optional($d->productVariant)->name;

                return [
                    'id' => $d->id,
                    'product_id' => $d->product_id,
                    'product_variant_id' => $d->product_variant_id,
                    'name' => $variant ? ($name.' - '.$variant) : $name,
                    'qty' => (float) $d->qty,
                    'price' => (float) $d->price,
                    'line_total' => (float) $d->line_total,
                ];
            })->values(),
        ]);
    }
}
