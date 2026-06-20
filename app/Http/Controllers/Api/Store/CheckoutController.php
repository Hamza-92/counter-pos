<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Create a Stripe PaymentIntent for the given cart total.
     * Called via AJAX before placing a credit-card order.
     */
    public function createPaymentIntent(Request $req)
    {
        $user = Auth::guard('store')->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $req->validate([
            'amount' => ['required', 'numeric', 'min:0.50'],
            'currency' => ['nullable', 'string', 'max:3'],
        ]);

        $stripeSecret = config('services.stripe.secret');
        if (! $stripeSecret) {
            return response()->json(['error' => 'Stripe is not configured.'], 500);
        }

        \Stripe\Stripe::setApiKey($stripeSecret);

        $currency = strtolower($data['currency'] ?? 'usd');
        $amountInCents = (int) round($data['amount'] * 100);

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => $currency,
            'metadata' => [
                'client_id' => $user->client_id ?? $user->id,
            ],
        ]);

        return response()->json([
            'clientSecret' => $intent->client_secret,
        ]);
    }

    public function store(Request $req)
    {
        $user = Auth::guard('store')->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $req->validate([
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.qty'                => ['required', 'numeric', 'min:1'],
            'warehouse_id'               => ['nullable', 'integer'],
            'payment_method'             => ['required', 'string', 'in:credit_card,mobile_money,cod'],
            'stripe_payment_intent_id'   => ['nullable', 'string', 'max:128'],
        ]);

        $paymentMethod = $data['payment_method'];

        // For credit-card orders, verify the Stripe PaymentIntent succeeded
        if ($paymentMethod === 'credit_card') {
            $piId = $data['stripe_payment_intent_id'] ?? null;
            if (! $piId) {
                return response()->json(['error' => 'Stripe payment confirmation is required.'], 422);
            }

            $stripeSecret = config('services.stripe.secret');
            if (! $stripeSecret) {
                return response()->json(['error' => 'Stripe is not configured.'], 500);
            }

            \Stripe\Stripe::setApiKey($stripeSecret);
            try {
                $intent = \Stripe\PaymentIntent::retrieve($piId);
                if ($intent->status !== 'succeeded') {
                    return response()->json(['error' => 'Payment has not been completed.'], 422);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Could not verify payment.'], 422);
            }
        }

        // Resolve warehouse
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if (! $warehouseId) {
            $warehouseId = (int) DB::table('store_settings')->value('default_warehouse_id');
        }
        if ($warehouseId && ! Warehouse::whereKey($warehouseId)->exists()) {
            $warehouseId = 0;
        }
        if (! $warehouseId) {
            $warehouseId = (int) Warehouse::value('id');
        }
        if (! $warehouseId) {
            return response()->json(['error' => 'No warehouse configured.'], 422);
        }

        // Preload products and verify existence
        $ids = collect($data['items'])->pluck('product_id')->unique()->values();
        $products = Product::whereIn('id', $ids)
            ->get(['id', 'price', 'TaxNet', 'discount', 'discount_method', 'tax_method',
                   'is_preorder', 'preorder_available_date', 'preorder_limit', 'preorder_note'])
            ->keyBy('id');

        if ($products->count() !== $ids->count()) {
            $missing = $ids->diff($products->keys());

            return response()->json([
                'error'       => 'Some products not found.',
                'product_ids' => $missing->values(),
            ], 422);
        }

        // Preload variants
        $variantIds = collect($data['items'])->pluck('product_variant_id')->filter()->unique()->values();
        $variants = $variantIds->isEmpty()
            ? collect()
            : ProductVariant::whereIn('id', $variantIds)->get(['id', 'product_id', 'price'])->keyBy('id');

        // Preload stock levels for preorder detection
        $stockRows = product_warehouse::where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $ids)
            ->whereNull('deleted_at')
            ->get(['product_id', 'product_variant_id', 'qte'])
            ->keyBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?? 'p'));

        // Track preorder quantities per product for limit enforcement
        $preorderQtyAccumulator = [];

        // Build items using server-side prices
        $normalizedItems = [];
        $subtotal = 0.0;
        $hasPreorderItems = false;

        foreach ($data['items'] as $i) {
            $pid  = (int) $i['product_id'];
            $pvid = ! empty($i['product_variant_id']) ? (int) $i['product_variant_id'] : null;
            $qty  = max(1, (float) $i['qty']);

            $product = $products->get($pid);
            $price   = (float) $product->price;
            if ($pvid) {
                $variant = $variants->get($pvid);
                if ($variant && (int) $variant->product_id === $pid) {
                    $price = (float) $variant->price;
                }
            }
            $price = round(max(0, $price), 2);
            $line  = round($qty * $price, 2);

            // Determine if this line is a pre-order
            $stockKey = $pvid ? "{$pid}:{$pvid}" : "{$pid}:p";
            $currentStock = isset($stockRows[$stockKey]) ? (float) $stockRows[$stockKey]->qte : 0.0;
            $isPreorder = false;

            if ($product->is_preorder && $currentStock <= 0) {
                $isPreorder = true;
                $hasPreorderItems = true;

                // Enforce preorder_limit if set
                if ($product->preorder_limit !== null) {
                    $alreadyQueued = $preorderQtyAccumulator[$pid] ?? 0;
                    if (($alreadyQueued + $qty) > $product->preorder_limit) {
                        return response()->json([
                            'error' => 'Pre-order limit exceeded for ' . $product->name
                                . '. Maximum: ' . $product->preorder_limit . '.',
                        ], 422);
                    }
                    $preorderQtyAccumulator[$pid] = $alreadyQueued + $qty;
                }
            }

            $normalizedItems[] = [
                'product_id'         => $pid,
                'product_variant_id' => $pvid,
                'qty'                => $qty,
                'price'              => $price,
                'TaxNet'             => (float) ($product->TaxNet ?? 0),
                'discount'           => (float) ($product->discount ?? 0),
                'discount_method'    => (string) ($product->discount_method ?? '1'),
                'tax_method'         => (string) ($product->tax_method ?? '1'),
                'is_preorder'        => $isPreorder,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $subtotal += $line;
        }

        $grand    = round(max(0, $subtotal), 2);
        $clientId = $user->client_id ?? null;

        $todayDate = now()->toDateString();
        $nowTime   = now()->format('H:i:s');
        $ref = method_exists(\App\Models\OnlineOrder::class, 'generateRef')
            ? \App\Models\OnlineOrder::generateRef()
            : ('SO-' . now()->format('Ymd') . '-' . str_pad((string) ((\App\Models\OnlineOrder::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT));

        $paymentStatus = $paymentMethod === 'credit_card' ? 'paid' : 'pending';

        $order = DB::transaction(function () use ($clientId, $warehouseId, $grand, $normalizedItems, $todayDate, $nowTime, $ref, $paymentMethod, $paymentStatus, $data, $hasPreorderItems) {
            $order = \App\Models\OnlineOrder::create([
                'date'                      => $todayDate,
                'time'                      => $nowTime,
                'ref'                       => $ref,
                'status'                    => 'pending',
                'has_preorder_items'        => $hasPreorderItems,
                'client_id'                 => $clientId,
                'warehouse_id'              => $warehouseId,
                'total'                     => $grand,
                'payment_method'            => $paymentMethod,
                'payment_status'            => $paymentStatus,
                'stripe_payment_intent_id'  => $data['stripe_payment_intent_id'] ?? null,
            ]);

            $order->items()->createMany($normalizedItems);

            return $order;
        });

        return response()->json([
            'id'             => $order->id,
            'ref'            => $order->ref,
            'status'         => $order->status,
            'date'           => (string) $order->date,
            'time'           => (string) $order->time,
            'total'          => (float) $order->total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
        ], 201);
    }
}
