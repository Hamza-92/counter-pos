<?php

namespace App\Services\WooCommerce;

use App\Models\Brand as PosBrand;
use App\Models\Category as PosCategory;
use App\Models\Client as PosClient;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\PaymentSale;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WooCommerceLog;
use App\Models\WooCommerceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class SyncService
{
    // ---- Meta keys used to create a deterministic mapping POS <-> Woo ----
    private const WOO_META_EXTERNAL_PRODUCT_ID = 'external_product_id';
    private const WOO_META_STOCKY_PRODUCT_ID   = '_stocky_product_id';
    private const WOO_META_EXTERNAL_VARIANT_ID = 'external_variant_id';
    private const WOO_SYNC_ABORT_EXCEPTION     = '__WOO_SYNC_ABORT__';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    // ---------------------------------------------------------------------
    // Customer sync issue tracking (stored on clients table)
    // ---------------------------------------------------------------------
    private function setClientSyncIssue(PosClient $client, string $type, string $message, string $source): void
    {
        try {
            $client->sync_issue_type = $type;
            $client->sync_issue_message = $message;
            $client->sync_issue_source = $source;
            $client->sync_issue_at = now();
            $client->save();
        } catch (\Throwable $e) {
            // Best-effort: do not break sync for issue tracking
        }
    }

    private function clearClientSyncIssue(PosClient $client): void
    {
        try {
            if ($client->sync_issue_type === null
                && $client->sync_issue_message === null
                && $client->sync_issue_source === null
                && $client->sync_issue_at === null) {
                return;
            }
            $client->sync_issue_type = null;
            $client->sync_issue_message = null;
            $client->sync_issue_source = null;
            $client->sync_issue_at = null;
            $client->save();
        } catch (\Throwable $e) {
            // Best-effort
        }
    }

    /**
     * Compose Stocky Sale notes from a WooCommerce order payload.
     *
     * - Always includes an import marker.
     * - Appends WooCommerce `customer_note` when present.
     * - Best-effort fetches WooCommerce order notes via `/orders/{id}/notes` and appends them.
     */
    private function composeSaleNotesFromWooOrder(array $order, int $wooOrderId): string
    {
        $orderNumber = trim((string) ($order['number'] ?? ''));
        $base = 'Imported from WooCommerce order #'.($orderNumber !== '' ? $orderNumber : $wooOrderId);

        $parts = [$base];

        $customerNote = trim((string) ($order['customer_note'] ?? ''));
        if ($customerNote !== '') {
            $parts[] = "Customer note:\n".$customerNote;
        }

        $max = (int) env('WOO_PULL_ORDER_NOTES_MAX', 25);
        if ($max <= 0) {
            return implode("\n\n", $parts);
        }

        try {
            $collected = [];
            $perPage = 100;
            $page = 1;
            $pageCap = 3; // safety cap

            while (count($collected) < $max && $page <= $pageCap) {
                $res = $this->client->getNoRetry('orders/'.$wooOrderId.'/notes', [
                    'per_page' => $perPage,
                    'page' => $page,
                ], 12, 5);

                if (!$res->successful()) {
                    break;
                }

                $data = $res->json();
                $notesArr = is_array($data) ? ($data['notes'] ?? $data['order_notes'] ?? $data) : [];
                if (!is_array($notesArr) || empty($notesArr)) {
                    break;
                }

                foreach ($notesArr as $n) {
                    if (!is_array($n)) continue;
                    $txt = trim(strip_tags((string) ($n['note'] ?? '')));
                    if ($txt === '') continue;

                    // avoid duplicating customer_note if Woo also returns it in notes list
                    if ($customerNote !== '' && $txt === trim(strip_tags($customerNote))) {
                        continue;
                    }

                    $isCustomer = (bool) ($n['customer_note'] ?? false);
                    $created = trim((string) ($n['date_created'] ?? ''));
                    $prefix = $isCustomer ? 'Customer' : 'Private';
                    $line = $prefix.($created !== '' ? ' '.$created : '').': '.$txt;
                    $collected[] = $line;

                    if (count($collected) >= $max) {
                        break;
                    }
                }

                if (count($notesArr) < $perPage) {
                    break;
                }
                $page++;
            }

            if (!empty($collected)) {
                $parts[] = "Order notes:\n".implode("\n", $collected);
            }
        } catch (\Throwable $e) {
            // Best-effort: do not fail order import if notes fetch fails
            $this->log('orders.pull', 'warning', 'Failed fetching Woo order notes: '.$e->getMessage(), [
                'woocommerce_order_id' => $wooOrderId,
            ]);
        }

        return implode("\n\n", $parts);
    }

    // ---------------------------------------------------------------------
    // Orders (WooCommerce -> Stocky)
    // ---------------------------------------------------------------------
    /**
     * Map Woo order status to Stocky sale statut (3-state).
     *
     * Mapping (as requested):
     * - Woo completed -> Stocky completed
     * - Woo processing / on-hold -> Stocky ordered
     * - Woo pending / failed / cancelled / refunded (and everything else) -> Stocky pending
     */
    private function mapWooOrderStatusToStockyStatut(string $wooStatus): string
    {
        $s = strtolower(trim($wooStatus));
        if ($s === 'completed') {
            return 'completed';
        }
        if (in_array($s, ['processing', 'on-hold'], true)) {
            return 'ordered';
        }
        return 'pending';
    }

    /**
     * Woo statuses that should NOT result in a Stocky Sale (or, if a Sale was
     * previously imported, should cause it to be reversed/soft-deleted).
     */
    private const WOO_REVERSAL_STATUSES = ['cancelled', 'canceled', 'failed', 'refunded', 'trash', 'checkout-draft'];

    private function isWooReversalStatus(string $wooStatus): bool
    {
        return in_array(strtolower(trim($wooStatus)), self::WOO_REVERSAL_STATUSES, true);
    }

    /**
     * Compute paid amount + payment status from the Woo order payload.
     *
     * Conservative rules:
     *   - completed + no refunds         -> fully paid
     *   - date_paid set + no refunds     -> fully paid (Woo only sets date_paid
     *                                        once a gateway captures payment)
     *   - partial refund                 -> partial = grand_total - refunded
     *   - everything else                -> unpaid (e.g. processing without
     *                                        date_paid covers BACS/COD/auth-only)
     */
    private function computePaymentFromWooOrder(array $order, float $grandTotal): array
    {
        $status = strtolower((string) ($order['status'] ?? ''));
        $datePaid = trim((string) ($order['date_paid'] ?? ''));

        // Woo refunds are negative amounts; sum absolute values.
        $totalRefunded = 0.0;
        if (isset($order['refunds']) && is_array($order['refunds'])) {
            foreach ($order['refunds'] as $r) {
                if (!is_array($r)) continue;
                $totalRefunded += abs((float) ($r['total'] ?? 0));
            }
        }

        if ($totalRefunded > 0 && $totalRefunded < $grandTotal) {
            return [
                'paid_amount' => round(max(0, $grandTotal - $totalRefunded), 2),
                'payment_statut' => 'partial',
            ];
        }

        if ($totalRefunded >= $grandTotal && $totalRefunded > 0) {
            return ['paid_amount' => 0.0, 'payment_statut' => 'unpaid'];
        }

        if ($status === 'completed' || $datePaid !== '') {
            return ['paid_amount' => $grandTotal, 'payment_statut' => 'paid'];
        }

        return ['paid_amount' => 0.0, 'payment_statut' => 'unpaid'];
    }

    /**
     * Reverse a previously-imported Sale that was created from a Woo order
     * which has since been cancelled/refunded/failed. Restores stock,
     * soft-deletes payments and the sale itself.
     */
    private function reverseImportedSale(Sale $sale, string $reason, ?string $newWooStatus = null): void
    {
        DB::transaction(function () use ($sale, $reason, $newWooStatus) {
            // Restore stock — only if the sale was previously decremented
            // (i.e. statut was 'completed'). Mirrors SalesController::destroy.
            if (($sale->statut ?? '') === 'completed') {
                $details = SaleDetail::where('sale_id', $sale->id)
                    ->whereNull('deleted_at')
                    ->get();
                foreach ($details as $d) {
                    $unit = $d->sale_unit_id ? Unit::find($d->sale_unit_id) : null;
                    if (!$unit) {
                        $product = Product::find($d->product_id);
                        if ($product && $product->unit_sale_id) {
                            $unit = Unit::find($product->unit_sale_id);
                        }
                    }

                    $qty = (float) $d->quantity;
                    $opv = max((float) ($unit->operator_value ?? 1), 1);
                    $addQ = ($unit && ($unit->operator ?? '') === '/') ? ($qty / $opv) : ($qty * $opv);

                    $pwQuery = product_warehouse::whereNull('deleted_at')
                        ->where('warehouse_id', $sale->warehouse_id)
                        ->where('product_id', $d->product_id);
                    if ($d->product_variant_id) {
                        $pwQuery->where('product_variant_id', $d->product_variant_id);
                    } else {
                        $pwQuery->whereNull('product_variant_id');
                    }
                    $pw = $pwQuery->first();
                    if ($pw && $unit) {
                        $pw->qte = max(0, ((float) $pw->qte) + $addQ);
                        $pw->save();
                    }
                }
            }

            // Soft-delete payments tied to this sale
            PaymentSale::where('sale_id', $sale->id)
                ->whereNull('deleted_at')
                ->get()
                ->each(fn ($p) => $p->delete());

            // Soft-delete sale details and the sale itself
            SaleDetail::where('sale_id', $sale->id)
                ->whereNull('deleted_at')
                ->get()
                ->each(fn ($d) => $d->delete());

            if ($newWooStatus !== null) {
                $sale->woocommerce_order_status = $newWooStatus;
            }
            $sale->save();
            $sale->delete();
        }, 3);

        $this->log('orders.pull', 'info', 'Reversed imported sale due to Woo status change', [
            'sale_id' => $sale->id,
            'woocommerce_order_id' => $sale->woocommerce_order_id,
            'new_woo_status' => $newWooStatus,
            'reason' => $reason,
        ]);
    }

    /**
     * Pull WooCommerce orders into Stocky sales.
     *
     * Notes:
     * - Idempotency via sales.woocommerce_order_id (unique).
     * - Skips orders whose line items cannot be mapped to local products.
     */
    public function pullOrders(int $userId, ?int $warehouseId = null, ?callable $progress = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;

        // Pick default warehouse if not provided
        if (!$warehouseId || $warehouseId <= 0) {
            $settings = Setting::whereNull('deleted_at')->first();
            $candidate = $settings ? (int) ($settings->warehouse_id ?? 0) : 0;
            $warehouseId = $candidate > 0 && Warehouse::where('id', $candidate)->whereNull('deleted_at')->exists()
                ? $candidate
                : (int) (Warehouse::whereNull('deleted_at')->min('id') ?? 0);
        }

        if (!$warehouseId || $warehouseId <= 0) {
            return ['ok' => false, 'error' => 'No warehouse configured for order import'];
        }

        $page = 1;
        $perPage = 50;

        try {
            while (true) {
                if ($progress) {
                    $progress(['stage' => 'fetching', 'page' => $page, 'processed' => $processed]);
                }

                $res = $this->client->getNoRetry('orders', [
                    'page' => $page,
                    'per_page' => $perPage,
                    'orderby' => 'id',
                    'order' => 'asc',
                    // default: all statuses; Woo API returns recent
                ], 20, 5);

                if (!$res->successful()) {
                    $errors++;
                    $this->log('orders.pull', 'error', 'Failed to fetch WooCommerce orders', [
                        'status' => $res->status(),
                        'body' => $res->body(),
                        'page' => $page,
                    ]);
                    break;
                }

                $body = $res->json();
                $orders = is_array($body) ? ($body['orders'] ?? $body) : [];
                if (!is_array($orders) || empty($orders)) {
                    break;
                }

                foreach ($orders as $order) {
                    $processed++;

                    try {
                        $wooOrderId = (int) ($order['id'] ?? 0);
                        if ($wooOrderId <= 0) {
                            $skipped++;
                            continue;
                        }

                        $wooStatus = (string) ($order['status'] ?? '');

                        // Idempotency:
                        // - If an active sale exists => update STATUS ONLY (do not re-import lines/payments/stock).
                        //   If the new Woo status is a reversal (cancelled/refunded/failed),
                        //   reverse the imported sale: restore stock + soft-delete payments + soft-delete sale.
                        // - If a sale exists but is soft-deleted => allow inserting a new sale (do NOT restore).
                        $existingActiveSale = Sale::whereNull('deleted_at')->where('woocommerce_order_id', $wooOrderId)->first();
                        if ($existingActiveSale) {
                            if ($this->isWooReversalStatus($wooStatus)) {
                                $this->reverseImportedSale($existingActiveSale, 'woo status changed to '.$wooStatus, $wooStatus);
                                $updated++;
                                continue;
                            }
                            $existingActiveSale->statut = $this->mapWooOrderStatusToStockyStatut($wooStatus);
                            $existingActiveSale->woocommerce_order_status = $wooStatus;
                            $existingActiveSale->woocommerce_order_number = (string) ($order['number'] ?? $existingActiveSale->woocommerce_order_number ?? '');
                            $existingActiveSale->save();
                            $updated++;
                            continue;
                        }

                        // Skip new orders that are in a non-sale status (cancelled / failed / refunded / trash / draft).
                        if ($this->isWooReversalStatus($wooStatus)) {
                            $skipped++;
                            $this->log('orders.pull', 'info', 'Skipped order: non-sale Woo status', [
                                'woocommerce_order_id' => $wooOrderId,
                                'woo_status' => $wooStatus,
                            ]);
                            continue;
                        }

                        $wooCustomerId = (int) ($order['customer_id'] ?? 0);
                        $billing = $order['billing'] ?? [];
                        $billingEmail = is_array($billing) ? trim((string) ($billing['email'] ?? '')) : '';
                        $normalizedEmail = $this->normalizeEmail($billingEmail);

                        // Resolve client: prefer woocommerce_id -> fallback to email -> else default client (if configured)
                        $client = null;
                        if ($wooCustomerId > 0) {
                            $client = PosClient::whereNull('deleted_at')->where('woocommerce_id', $wooCustomerId)->first();
                        }
                        if (!$client && $normalizedEmail !== '') {
                            $client = PosClient::whereNull('deleted_at')
                                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                                ->first();
                        }
                        if (!$client) {
                            $settings = Setting::whereNull('deleted_at')->with('Client')->first();
                            $defaultClientId = $settings ? (int) ($settings->client_id ?? 0) : 0;
                            if ($defaultClientId > 0) {
                                $client = PosClient::whereNull('deleted_at')->find($defaultClientId);
                            }
                        }

                        // If no client and we have an email, create one
                        if (!$client && $normalizedEmail !== '') {
                            $maxCode = PosClient::max('code') ?? 0;
                            $newCode = $maxCode + 1;

                            $clientName = '';
                            if (is_array($billing)) {
                                $fn = trim((string) ($billing['first_name'] ?? ''));
                                $ln = trim((string) ($billing['last_name'] ?? ''));
                                $clientName = trim($fn.' '.$ln);
                            }
                            if ($clientName === '') {
                                $clientName = $billingEmail;
                            }

                            $validator = Validator::make(
                                ['email' => $billingEmail],
                                ['email' => ['required', 'email', Rule::unique('clients', 'email')->whereNull('deleted_at')]]
                            );
                            if ($validator->fails()) {
                                $skipped++;
                                $errors++;
                                $this->log('orders.pull', 'warning', 'Skipped order: customer email conflicts with existing client', [
                                    'woocommerce_order_id' => $wooOrderId,
                                    'email' => $billingEmail,
                                    'error' => $validator->errors()->first('email'),
                                ]);
                                continue;
                            }

                            $client = PosClient::create([
                                'name' => $clientName,
                                'code' => $newCode,
                                'email' => $billingEmail,
                                'phone' => is_array($billing) ? ((string) ($billing['phone'] ?? '')) : '',
                                'adresse' => is_array($billing) ? $this->joinAddressLines((string) ($billing['address_1'] ?? ''), (string) ($billing['address_2'] ?? '')) : '',
                                'city' => is_array($billing) ? ((string) ($billing['city'] ?? '')) : '',
                                'state' => is_array($billing) ? $this->resolveWooStateName((string) ($billing['state'] ?? ''), (string) ($billing['country'] ?? '')) : '',
                                'zip' => is_array($billing) ? ((string) ($billing['postcode'] ?? '')) : '',
                                'country' => is_array($billing) ? $this->resolveWooCountryName((string) ($billing['country'] ?? '')) : '',
                                'woocommerce_id' => $wooCustomerId > 0 ? $wooCustomerId : null,
                            ]);
                        }

                        if (!$client) {
                            $skipped++;
                            $errors++;
                            $this->log('orders.pull', 'warning', 'Skipped order: could not resolve customer', [
                                'woocommerce_order_id' => $wooOrderId,
                                'customer_id' => $wooCustomerId,
                                'billing_email' => $billingEmail,
                            ]);
                            continue;
                        }

                        $status = (string) ($order['status'] ?? '');
                        $dateCreated = (string) ($order['date_created'] ?? $order['date_created_gmt'] ?? '');
                        $date = $dateCreated !== '' ? substr($dateCreated, 0, 10) : now()->toDateString();
                        $time = $dateCreated !== '' ? substr(str_replace('T', ' ', $dateCreated), 11, 8) : now()->toTimeString();

                        $grandTotal = (float) ($order['total'] ?? 0);
                        $taxNet = (float) ($order['total_tax'] ?? 0);
                        $shipping = (float) ($order['shipping_total'] ?? 0);
                        $discount = (float) ($order['discount_total'] ?? 0);

                        $payment = $this->computePaymentFromWooOrder($order, $grandTotal);
                        $paidAmount = $payment['paid_amount'];
                        $paymentStatut = $payment['payment_statut'];

                        $saleStatut = $this->mapWooOrderStatusToStockyStatut($status);

                        $ref = 'WO-'.$wooOrderId;
                        $notes = $this->composeSaleNotesFromWooOrder($order, $wooOrderId);

                        $lineItems = $order['line_items'] ?? [];
                        if (!is_array($lineItems) || empty($lineItems)) {
                            $skipped++;
                            continue;
                        }

                        // Build sale detail rows + stock adjustments
                        $detailRows = [];
                        $stockAdjustments = [];

                        foreach ($lineItems as $li) {
                            if (!is_array($li)) {
                                continue;
                            }

                            $qty = (float) ($li['quantity'] ?? 0);
                            if ($qty <= 0) {
                                continue;
                            }

                            $wooProductId = (int) ($li['product_id'] ?? 0);
                            $wooVariationId = (int) ($li['variation_id'] ?? 0);
                            $sku = trim((string) ($li['sku'] ?? ''));

                            $resolved = $this->resolveLineItemMapping($wooProductId, $wooVariationId, $sku);
                            $product = $resolved['product'];
                            $variant = $resolved['variant'];

                            if (!$product) {
                                $errors++;
                                $skipped++;
                                $this->log('orders.pull', 'warning', 'Skipped order: could not map line item product', [
                                    'woocommerce_order_id' => $wooOrderId,
                                    'woo_product_id' => $wooProductId,
                                    'woo_variation_id' => $wooVariationId,
                                    'sku' => $sku,
                                ]);
                                // Abort whole order for safety
                                throw new \RuntimeException('Order contains unmapped products');
                            }

                            $unitId = (int) ($product->unit_sale_id ?? 0);
                            $unit = $unitId > 0 ? Unit::find($unitId) : null;

                            $price = (float) ($li['price'] ?? 0);
                            if ($price <= 0) {
                                $subtotal = (float) ($li['subtotal'] ?? $li['total'] ?? 0);
                                $price = $qty > 0 ? ($subtotal / $qty) : 0;
                            }
                            $lineTotal = (float) ($li['total'] ?? $li['subtotal'] ?? 0);

                            $detailRows[] = [
                                'date' => $date,
                                'sale_id' => 0, // filled after sale create
                                'sale_unit_id' => $unitId > 0 ? $unitId : null,
                                'quantity' => $qty,
                                'price' => $price,
                                'TaxNet' => 0,
                                'tax_method' => '1',
                                'discount' => 0,
                                'discount_method' => '1',
                                'product_id' => (int) $product->id,
                                'product_variant_id' => $variant ? (int) $variant->id : null,
                                'total' => $lineTotal,
                                'price_type' => 'retail',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $stockAdjustments[] = [
                                'product_id' => (int) $product->id,
                                'product_variant_id' => $variant ? (int) $variant->id : null,
                                'unit' => $unit,
                                'qty' => $qty,
                            ];
                        }

                        DB::transaction(function () use (
                            $wooOrderId,
                            $order,
                            $warehouseId,
                            $userId,
                            $date,
                            $time,
                            $ref,
                            $client,
                            $grandTotal,
                            $taxNet,
                            $discount,
                            $shipping,
                            $paidAmount,
                            $paymentStatut,
                            $saleStatut,
                            $notes,
                            &$detailRows,
                            $stockAdjustments,
                            &$created
                        ) {
                            $saleAttrs = [
                                'date' => $date,
                                'time' => $time,
                                'Ref' => $ref,
                                'is_pos' => 0,
                                'client_id' => (int) $client->id,
                                'warehouse_id' => (int) $warehouseId,
                                'user_id' => (int) $userId,
                                'GrandTotal' => $grandTotal,
                                'TaxNet' => $taxNet,
                                'tax_rate' => 0,
                                'discount' => $discount,
                                'discount_Method' => '2',
                                'shipping' => $shipping,
                                'statut' => $saleStatut,
                                'payment_statut' => $paymentStatut,
                                'paid_amount' => $paidAmount,
                                'notes' => $notes,
                                'woocommerce_order_id' => $wooOrderId,
                                'woocommerce_order_number' => (string) ($order['number'] ?? ''),
                                'woocommerce_order_status' => (string) ($order['status'] ?? ''),
                            ];

                            // Ensure idempotency inside transaction too (active only).
                            // If a sale exists but is soft-deleted, we still insert a new one.
                            if (Sale::whereNull('deleted_at')->where('woocommerce_order_id', $wooOrderId)->exists()) {
                                return;
                            }

                            $sale = Sale::create($saleAttrs);

                            foreach ($detailRows as &$r) {
                                $r['sale_id'] = (int) $sale->id;
                            }
                            unset($r);

                            DB::table('sale_details')->insert($detailRows);

                            // Create a payment_sales record for paid Woo orders (idempotent per sale)
                            if ($paidAmount > 0) {
                                // Prefix with WO- so the Ref doesn't collide with manually-created
                                // payments whose Ref happens to be the same numeric string.
                                $wooPayRef = 'WO-'.$wooOrderId;
                                // Idempotency:
                                // - If an active payment exists for this sale, update it (avoid duplicates).
                                // - Else if an active payment exists by Ref, update/link it to this sale.
                                // - If a matching payment is soft-deleted, create a NEW payment (do NOT restore).
                                $activePaymentForSale = PaymentSale::whereNull('deleted_at')
                                    ->where('sale_id', $sale->id)
                                    ->orderByDesc('id')
                                    ->first();
                                $activePaymentByRef = PaymentSale::whereNull('deleted_at')->where('Ref', $wooPayRef)->first();

                                if ($activePaymentForSale) {
                                    $activePaymentForSale->Ref = $wooPayRef;
                                    $activePaymentForSale->date = $date;
                                    $activePaymentForSale->montant = $paidAmount;
                                    $activePaymentForSale->account_id = null;
                                    $activePaymentForSale->save();
                                } elseif ($activePaymentByRef) {
                                    $activePaymentByRef->sale_id = $sale->id;
                                    $activePaymentByRef->date = $date;
                                    $activePaymentByRef->montant = $paidAmount;
                                    $activePaymentByRef->account_id = null;
                                    $activePaymentByRef->save();
                                } else {
                                    $paymentTitle = trim((string) ($order['payment_method_title'] ?? $order['payment_method'] ?? ''));
                                    $paymentMethodId = null;
                                    if ($paymentTitle !== '') {
                                        $pm = PaymentMethod::where('name', $paymentTitle)->first();
                                        if ($pm) {
                                            $paymentMethodId = (int) $pm->id;
                                        }
                                    }
                                    if (!$paymentMethodId) {
                                        // Fallback: first payment method
                                        $pm = PaymentMethod::orderBy('id')->first();
                                        $paymentMethodId = $pm ? (int) $pm->id : null;
                                    }

                                    PaymentSale::create([
                                        'sale_id' => $sale->id,
                                        'date' => $date,
                                        'montant' => $paidAmount,
                                        // Use Woo order id as idempotency reference
                                        'Ref' => $wooPayRef,
                                        'change' => 0,
                                        'payment_method_id' => $paymentMethodId,
                                        'user_id' => $userId,
                                        'notes' => 'Imported from WooCommerce order #'.((string) ($order['number'] ?? $wooOrderId)).($paymentTitle !== '' ? ' ('.$paymentTitle.')' : ''),
                                        'account_id' => null,
                                    ]);
                                }
                            }

                            // Adjust stock only for completed-like orders
                            // Adjust stock for completed-like orders (treat soft-deleted as new import)
                            if ($saleStatut === 'completed') {
                                foreach ($stockAdjustments as $adj) {
                                    $pw = product_warehouse::where('deleted_at', '=', null)
                                        ->where('warehouse_id', $warehouseId)
                                        ->where('product_id', $adj['product_id']);
                                    if ($adj['product_variant_id']) {
                                        $pw->where('product_variant_id', $adj['product_variant_id']);
                                    }
                                    $pw = $pw->first();

                                    $unit = $adj['unit'];
                                    if ($pw && $unit) {
                                        if ($unit->operator === '/') {
                                            $pw->qte -= $adj['qty'] / (float) $unit->operator_value;
                                        } else {
                                            $pw->qte -= $adj['qty'] * (float) $unit->operator_value;
                                        }
                                        $pw->save();
                                    }
                                }
                            }

                            $created++;
                        }, 3);
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->log('orders.pull', 'error', 'Failed to import order: '.$e->getMessage(), [
                            'woocommerce_order_id' => $order['id'] ?? null,
                        ]);
                    }
                }

                if (count($orders) < $perPage) {
                    break;
                }
                $page++;
            }
        } catch (\Throwable $e) {
            $errors++;
            $this->log('orders.pull', 'error', 'Fatal error during orders pull: '.$e->getMessage(), []);
        }

        if ($progress) {
            $progress(['stage' => 'completed', 'processed' => $processed, 'created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
        }

        return ['ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'processed' => $processed];
    }

    /**
     * Pull a single WooCommerce order into Stocky sales (idempotent).
     *
     * This is a focused version of pullOrders() used by the UI per-row sync button.
     */
    public function pullSingleOrder(int $wooOrderId, int $userId, ?int $warehouseId = null): array
    {
        if ($wooOrderId <= 0) {
            return ['ok' => false, 'error' => 'Invalid WooCommerce order id'];
        }

        // Pick default warehouse if not provided (same as pullOrders)
        if (!$warehouseId || $warehouseId <= 0) {
            $settings = Setting::whereNull('deleted_at')->first();
            $candidate = $settings ? (int) ($settings->warehouse_id ?? 0) : 0;
            $warehouseId = $candidate > 0 && Warehouse::where('id', $candidate)->whereNull('deleted_at')->exists()
                ? $candidate
                : (int) (Warehouse::whereNull('deleted_at')->min('id') ?? 0);
        }
        if (!$warehouseId || $warehouseId <= 0) {
            return ['ok' => false, 'error' => 'No warehouse configured for order import'];
        }

        // Idempotency:
        // - If an active sale exists => update STATUS ONLY (do not re-import lines/payments/stock).
        // - If a sale exists but is soft-deleted => allow inserting a new sale (do NOT restore).
        $existingActiveSale = Sale::whereNull('deleted_at')->where('woocommerce_order_id', $wooOrderId)->first();

        $res = $this->client->getNoRetry('orders/'.$wooOrderId, [], 20, 5);
        if (!$res->successful()) {
            return ['ok' => false, 'error' => 'Failed to fetch WooCommerce order', 'status' => $res->status(), 'body' => $res->body()];
        }

        $order = $res->json();
        if (!is_array($order)) {
            return ['ok' => false, 'error' => 'Invalid response from WooCommerce'];
        }

        try {
            $wooOrderId = (int) ($order['id'] ?? 0);
            if ($wooOrderId <= 0) {
                return ['ok' => false, 'error' => 'Invalid WooCommerce order payload'];
            }

            $wooStatus = (string) ($order['status'] ?? '');

            // If already imported, update status only and exit. If the new Woo
            // status is a reversal (cancelled/refunded/failed), reverse the
            // imported sale instead.
            if ($existingActiveSale) {
                if ($this->isWooReversalStatus($wooStatus)) {
                    $this->reverseImportedSale($existingActiveSale, 'woo status changed to '.$wooStatus, $wooStatus);
                    return ['ok' => true, 'created' => 0, 'updated' => 1, 'skipped' => 0, 'errors' => 0, 'processed' => 1, 'reversed' => true];
                }
                $existingActiveSale->statut = $this->mapWooOrderStatusToStockyStatut($wooStatus);
                $existingActiveSale->woocommerce_order_status = $wooStatus;
                $existingActiveSale->woocommerce_order_number = (string) ($order['number'] ?? $existingActiveSale->woocommerce_order_number ?? '');
                $existingActiveSale->save();

                return ['ok' => true, 'created' => 0, 'updated' => 1, 'skipped' => 0, 'errors' => 0, 'processed' => 1, 'already_imported' => true];
            }

            // Skip new orders that are in a non-sale status.
            if ($this->isWooReversalStatus($wooStatus)) {
                $this->log('orders.pull', 'info', 'Skipped order: non-sale Woo status', [
                    'woocommerce_order_id' => $wooOrderId,
                    'woo_status' => $wooStatus,
                ]);
                return ['ok' => true, 'created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0, 'processed' => 1, 'skipped_reason' => 'non_sale_status'];
            }

            // Resolve client: prefer woocommerce_id -> fallback to email -> else default client (if configured)
            $wooCustomerId = (int) ($order['customer_id'] ?? 0);
            $billing = $order['billing'] ?? [];
            $billingEmail = is_array($billing) ? trim((string) ($billing['email'] ?? '')) : '';
            $normalizedEmail = $this->normalizeEmail($billingEmail);

            $client = null;
            if ($wooCustomerId > 0) {
                $client = PosClient::whereNull('deleted_at')->where('woocommerce_id', $wooCustomerId)->first();
            }
            if (!$client && $normalizedEmail !== '') {
                $client = PosClient::whereNull('deleted_at')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                    ->first();
            }
            if (!$client) {
                $settings = Setting::whereNull('deleted_at')->with('Client')->first();
                $defaultClientId = $settings ? (int) ($settings->client_id ?? 0) : 0;
                if ($defaultClientId > 0) {
                    $client = PosClient::whereNull('deleted_at')->find($defaultClientId);
                }
            }

            // If no client and we have an email, create one (only if unique)
            if (!$client && $normalizedEmail !== '') {
                $validator = Validator::make(
                    ['email' => $billingEmail],
                    ['email' => ['required', 'email', Rule::unique('clients', 'email')->whereNull('deleted_at')]]
                );
                if ($validator->fails()) {
                    return ['ok' => false, 'error' => 'Order customer email conflicts with existing client: '.$validator->errors()->first('email')];
                }

                $maxCode = PosClient::max('code') ?? 0;
                $newCode = $maxCode + 1;

                $clientName = '';
                if (is_array($billing)) {
                    $fn = trim((string) ($billing['first_name'] ?? ''));
                    $ln = trim((string) ($billing['last_name'] ?? ''));
                    $clientName = trim($fn.' '.$ln);
                }
                if ($clientName === '') {
                    $clientName = $billingEmail;
                }

                $client = PosClient::create([
                    'name' => $clientName,
                    'code' => $newCode,
                    'email' => $billingEmail,
                    'phone' => is_array($billing) ? ((string) ($billing['phone'] ?? '')) : '',
                    'adresse' => is_array($billing) ? $this->joinAddressLines((string) ($billing['address_1'] ?? ''), (string) ($billing['address_2'] ?? '')) : '',
                    'city' => is_array($billing) ? ((string) ($billing['city'] ?? '')) : '',
                    'state' => is_array($billing) ? $this->resolveWooStateName((string) ($billing['state'] ?? ''), (string) ($billing['country'] ?? '')) : '',
                    'zip' => is_array($billing) ? ((string) ($billing['postcode'] ?? '')) : '',
                    'country' => is_array($billing) ? $this->resolveWooCountryName((string) ($billing['country'] ?? '')) : '',
                    'woocommerce_id' => $wooCustomerId > 0 ? $wooCustomerId : null,
                ]);
            }

            if (!$client) {
                return ['ok' => false, 'error' => 'Could not resolve order customer'];
            }

            $status = (string) ($order['status'] ?? '');
            $dateCreated = (string) ($order['date_created'] ?? $order['date_created_gmt'] ?? '');
            $date = $dateCreated !== '' ? substr($dateCreated, 0, 10) : now()->toDateString();
            $time = $dateCreated !== '' ? substr(str_replace('T', ' ', $dateCreated), 11, 8) : now()->toTimeString();

            $grandTotal = (float) ($order['total'] ?? 0);
            $taxNet = (float) ($order['total_tax'] ?? 0);
            $shipping = (float) ($order['shipping_total'] ?? 0);
            $discount = (float) ($order['discount_total'] ?? 0);

            $payment = $this->computePaymentFromWooOrder($order, $grandTotal);
            $paidAmount = $payment['paid_amount'];
            $paymentStatut = $payment['payment_statut'];

            $saleStatut = $this->mapWooOrderStatusToStockyStatut($status);
            $ref = 'WO-'.$wooOrderId;
            $notes = $this->composeSaleNotesFromWooOrder($order, $wooOrderId);

            $lineItems = $order['line_items'] ?? [];
            if (!is_array($lineItems) || empty($lineItems)) {
                return ['ok' => false, 'error' => 'Order has no line items'];
            }

            $detailRows = [];
            $stockAdjustments = [];

            foreach ($lineItems as $li) {
                if (!is_array($li)) continue;
                $qty = (float) ($li['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $wooProductId = (int) ($li['product_id'] ?? 0);
                $wooVariationId = (int) ($li['variation_id'] ?? 0);
                $sku = trim((string) ($li['sku'] ?? ''));

                $resolved = $this->resolveLineItemMapping($wooProductId, $wooVariationId, $sku);
                $product = $resolved['product'];
                $variant = $resolved['variant'];

                if (!$product) {
                    return ['ok' => false, 'error' => 'Order contains unmapped products'];
                }

                $unitId = (int) ($product->unit_sale_id ?? 0);
                $unit = $unitId > 0 ? Unit::find($unitId) : null;

                $price = (float) ($li['price'] ?? 0);
                if ($price <= 0) {
                    $subtotal = (float) ($li['subtotal'] ?? $li['total'] ?? 0);
                    $price = $qty > 0 ? ($subtotal / $qty) : 0;
                }
                $lineTotal = (float) ($li['total'] ?? $li['subtotal'] ?? 0);

                $detailRows[] = [
                    'date' => $date,
                    'sale_id' => 0,
                    'sale_unit_id' => $unitId > 0 ? $unitId : null,
                    'quantity' => $qty,
                    'price' => $price,
                    'TaxNet' => 0,
                    'tax_method' => '1',
                    'discount' => 0,
                    'discount_method' => '1',
                    'product_id' => (int) $product->id,
                    'product_variant_id' => $variant ? (int) $variant->id : null,
                    'total' => $lineTotal,
                    'price_type' => 'retail',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $stockAdjustments[] = [
                    'product_id' => (int) $product->id,
                    'product_variant_id' => $variant ? (int) $variant->id : null,
                    'unit' => $unit,
                    'qty' => $qty,
                ];
            }

            DB::transaction(function () use (
                $wooOrderId,
                $order,
                $warehouseId,
                $userId,
                $date,
                $time,
                $ref,
                $client,
                $grandTotal,
                $taxNet,
                $discount,
                $shipping,
                $paidAmount,
                $paymentStatut,
                $saleStatut,
                $notes,
                &$detailRows,
                $stockAdjustments
            ) {
                $saleAttrs = [
                    'date' => $date,
                    'time' => $time,
                    'Ref' => $ref,
                    'is_pos' => 0,
                    'client_id' => (int) $client->id,
                    'warehouse_id' => (int) $warehouseId,
                    'user_id' => (int) $userId,
                    'GrandTotal' => $grandTotal,
                    'TaxNet' => $taxNet,
                    'tax_rate' => 0,
                    'discount' => $discount,
                    'discount_Method' => '2',
                    'shipping' => $shipping,
                    'statut' => $saleStatut,
                    'payment_statut' => $paymentStatut,
                    'paid_amount' => $paidAmount,
                    'notes' => $notes,
                    'woocommerce_order_id' => $wooOrderId,
                    'woocommerce_order_number' => (string) ($order['number'] ?? ''),
                    'woocommerce_order_status' => (string) ($order['status'] ?? ''),
                ];

                // Ensure idempotency inside transaction too (active only).
                if (Sale::whereNull('deleted_at')->where('woocommerce_order_id', $wooOrderId)->exists()) {
                    return;
                }

                $sale = Sale::create($saleAttrs);

                foreach ($detailRows as &$r) {
                    $r['sale_id'] = (int) $sale->id;
                }
                unset($r);

                DB::table('sale_details')->insert($detailRows);

                // Create a payment_sales record for paid Woo orders (idempotent per sale)
                if ($paidAmount > 0) {
                    // Prefix with WO- so the Ref doesn't collide with manually-created
                    // payments whose Ref happens to be the same numeric string.
                    $wooPayRef = 'WO-'.$wooOrderId;
                    $activePaymentForSale = PaymentSale::whereNull('deleted_at')
                        ->where('sale_id', $sale->id)
                        ->orderByDesc('id')
                        ->first();
                    $activePaymentByRef = PaymentSale::whereNull('deleted_at')->where('Ref', $wooPayRef)->first();

                    if ($activePaymentForSale) {
                        $activePaymentForSale->Ref = $wooPayRef;
                        $activePaymentForSale->date = $date;
                        $activePaymentForSale->montant = $paidAmount;
                        $activePaymentForSale->account_id = null;
                        $activePaymentForSale->save();
                    } elseif ($activePaymentByRef) {
                        $activePaymentByRef->sale_id = $sale->id;
                        $activePaymentByRef->date = $date;
                        $activePaymentByRef->montant = $paidAmount;
                        $activePaymentByRef->account_id = null;
                        $activePaymentByRef->save();
                    } else {
                        $paymentTitle = trim((string) ($order['payment_method_title'] ?? $order['payment_method'] ?? ''));
                        $paymentMethodId = null;
                        if ($paymentTitle !== '') {
                            $pm = PaymentMethod::where('name', $paymentTitle)->first();
                            if ($pm) {
                                $paymentMethodId = (int) $pm->id;
                            }
                        }
                        if (!$paymentMethodId) {
                            $pm = PaymentMethod::orderBy('id')->first();
                            $paymentMethodId = $pm ? (int) $pm->id : null;
                        }

                        PaymentSale::create([
                            'sale_id' => $sale->id,
                            'date' => $date,
                            'montant' => $paidAmount,
                            // Use Woo order id as idempotency reference
                            'Ref' => $wooPayRef,
                            'change' => 0,
                            'payment_method_id' => $paymentMethodId,
                            'user_id' => $userId,
                            'notes' => 'Imported from WooCommerce order #'.((string) ($order['number'] ?? $wooOrderId)).($paymentTitle !== '' ? ' ('.$paymentTitle.')' : ''),
                            'account_id' => null,
                        ]);
                    }
                }

                // Adjust stock for completed-like orders (treat soft-deleted as new import)
                if ($saleStatut === 'completed') {
                    foreach ($stockAdjustments as $adj) {
                        $pw = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $warehouseId)
                            ->where('product_id', $adj['product_id']);
                        if ($adj['product_variant_id']) {
                            $pw->where('product_variant_id', $adj['product_variant_id']);
                        }
                        $pw = $pw->first();

                        $unit = $adj['unit'];
                        if ($pw && $unit) {
                            if ($unit->operator === '/') {
                                $pw->qte -= $adj['qty'] / (float) $unit->operator_value;
                            } else {
                                $pw->qte -= $adj['qty'] * (float) $unit->operator_value;
                            }
                            $pw->save();
                        }
                    }
                }
            }, 3);

            return ['ok' => true, 'created' => 1, 'skipped' => 0, 'errors' => 0, 'processed' => 1];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function fromSettings(WooCommerceSetting $settings): self
    {
        return new self(new Client($settings->store_url, $settings->consumer_key, $settings->consumer_secret));
    }

    // ---------------------------------------------------------------------
    // Connection
    // ---------------------------------------------------------------------
    public function testConnection(): array
    {
        $res = $this->client->getNoRetry('system_status', [], 20, 5);

        if ($res->successful()) {
            return ['ok' => true, 'data' => $res->json()];
        }

        return ['ok' => false, 'status' => $res->status(), 'error' => $res->body()];
    }

    // ---------------------------------------------------------------------
    // Helpers (meta + strict identity)
    // ---------------------------------------------------------------------
    private function wooMetaValue(array $wooProduct, string $key): ?string
    {
        $meta = $wooProduct['meta_data'] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        foreach ($meta as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (($m['key'] ?? null) === $key) {
                $v = $m['value'] ?? null;
                if ($v === null) {
                    return null;
                }
                $s = trim((string) $v);
                return $s === '' ? null : $s;
            }
        }

        return null;
    }

    /**
     * Resolve WooCommerce "uncategorized" category ID (best-effort).
     * Some stores do NOT use id=1 for Uncategorized, so we query by slug.
     */
    private function wooUncategorizedCategoryId(): int
    {
        static $cached = null;
        if (is_int($cached)) {
            return $cached;
        }

        try {
            $res = $this->client->getNoRetry('products/categories', [
                'slug' => 'uncategorized',
                'per_page' => 100,
                'page' => 1,
                '_fields' => 'id,slug',
                'context' => 'edit',
            ], 20, 5);
            if (!$res->successful()) {
                $cached = 1;
                return $cached;
            }

            $body = $res->json();
            $items = is_array($body) ? ($body['categories'] ?? $body) : [];
            if (is_array($items)) {
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    if (($it['slug'] ?? null) === 'uncategorized') {
                        $id = (int) ($it['id'] ?? 0);
                        if ($id > 0) {
                            $cached = $id;
                            return $cached;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Fallback (common default)
        $cached = 1;
        return $cached;
    }

    /**
     * Load Woo product category IDs (best-effort).
     */
    private function wooProductCategoryIds(int $wooProductId): array
    {
        if ($wooProductId <= 0) {
            return [];
        }

        try {
            $res = $this->client->getNoRetry('products/'.$wooProductId, [
                '_fields' => 'id,categories',
                'context' => 'edit',
            ], 20, 5);
            if (!$res->successful()) {
                return [];
            }
            $body = $res->json();
            if (!is_array($body)) {
                return [];
            }
            $cats = $body['categories'] ?? [];
            if (!is_array($cats)) {
                return [];
            }
            $ids = [];
            foreach ($cats as $c) {
                if (!is_array($c)) continue;
                $id = (int) ($c['id'] ?? 0);
                if ($id > 0) $ids[$id] = true;
            }
            return array_map('intval', array_keys($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Should we apply categories on update?
     *
     * We only auto-update categories when the Woo product is effectively "uncategorized",
     * to avoid unnecessary updates (and to prevent overwriting manual category edits in Woo).
     */
    private function wooProductNeedsCategoryFix(int $wooProductId): bool
    {
        if ($wooProductId <= 0) {
            return true;
        }

        $ids = $this->wooProductCategoryIds($wooProductId);
        if (empty($ids)) {
            return true;
        }

        $uncatId = $this->wooUncategorizedCategoryId();
        if ($uncatId > 0) {
            // Needs fix if ONLY uncategorized is assigned
            $unique = [];
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $unique[$id] = true;
                }
            }
            return count($unique) === 0 || (count($unique) === 1 && isset($unique[$uncatId]));
        }

        // If we can't resolve uncategorized id, be conservative: don't force update.
        return false;
    }

    /**
     * Get the current Woo product type (simple|variable|grouped|external...).
     * Best-effort; returns null on failure.
     */
    private function wooProductType(int $wooProductId): ?string
    {
        if ($wooProductId <= 0) {
            return null;
        }

        try {
            $res = $this->client->getNoRetry('products/'.$wooProductId, [
                '_fields' => 'id,type',
                'context' => 'edit',
                'status' => 'any',
            ], 12, 5);
            if (!$res->successful()) {
                // Some stores block context=edit on GET (security plugins). Fallback to default context.
                $res = $this->client->getNoRetry('products/'.$wooProductId, [
                    '_fields' => 'id,type',
                    'status' => 'any',
                ], 12, 5);
            }
            if (!$res->successful()) {
                return null;
            }
            $body = $res->json();
            if (!is_array($body)) {
                return null;
            }
            $type = trim((string) ($body['type'] ?? ''));
            return $type !== '' ? $type : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Deletes all variations of a Woo variable product.
     * Returns number of variations deleted (best-effort).
     */
    private function deleteAllWooVariations(int $wooProductId, ?callable $emit = null, ?callable $shouldCancel = null): int
    {
        $deleted = 0;
        if ($wooProductId <= 0) {
            return 0;
        }

        $ids = [];
        $page = 1;
        $per = 100;
        $pageCap = (int) env('WOO_DELETE_VARIATIONS_PAGE_CAP', 50);
        $pageCap = max(1, min(500, $pageCap));

        try {
            while ($page <= $pageCap) {
                if ($shouldCancel) {
                    $shouldCancel();
                }

                if ($emit) {
                    $emit([
                        'stage' => 'variations_list',
                        'variations_page' => $page,
                        'last_endpoint' => 'GET /products/'.$wooProductId.'/variations?page='.$page,
                    ]);
                }

                $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                    'page' => $page,
                    'per_page' => $per,
                    'context' => 'edit',
                ], 20, 5);
                if (!$res->successful()) {
                    // Fallback: some stores block context=edit on GET.
                    $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                        'page' => $page,
                        'per_page' => $per,
                        '_fields' => 'id,sku,attributes,meta_data',
                    ], 20, 5);
                }

                if (!$res->successful()) {
                    break;
                }

                $list = $res->json();
                if (!is_array($list) || empty($list)) {
                    break;
                }

                foreach ($list as $v) {
                    if (!is_array($v)) continue;
                    $vid = (int) ($v['id'] ?? 0);
                    if ($vid > 0) {
                        $ids[] = $vid;
                    }
                }

                if (count($list) < $per) {
                    break;
                }
                $page++;
            }
        } catch (\Throwable $e) {
            // ignore, fallback below if any ids were collected
        }

        if (empty($ids)) {
            return 0;
        }

        // Prefer batch endpoint; fall back to individual DELETE if batch isn't available.
        $chunks = array_chunk($ids, 50);
        foreach ($chunks as $chunk) {
            if ($shouldCancel) {
                $shouldCancel();
            }

            $batchOk = false;
            try {
                if ($emit) {
                    $emit([
                        'stage' => 'variations_delete_batch',
                        'batch_size' => count($chunk),
                        'last_endpoint' => 'POST /products/'.$wooProductId.'/variations/batch',
                    ]);
                }

                $res = $this->client->postNoRetry('products/'.$wooProductId.'/variations/batch', [
                    'delete' => array_values($chunk),
                ], 20, 5);

                if ($res->successful()) {
                    $deleted += count($chunk);
                    $batchOk = true;
                }
            } catch (\Throwable $e) {
                $batchOk = false;
            }

            if ($batchOk) {
                continue;
            }

            foreach ($chunk as $vid) {
                if ($shouldCancel) {
                    $shouldCancel();
                }

                try {
                    if ($emit) {
                        $emit([
                            'stage' => 'variations_delete_one',
                            'variation_id' => (int) $vid,
                            'last_endpoint' => 'DELETE /products/'.$wooProductId.'/variations/'.$vid.'?force=true',
                        ]);
                    }

                    $res = $this->client->deleteNoRetry('products/'.$wooProductId.'/variations/'.$vid, [
                        'force' => 'true',
                    ], 20, 5);

                    if ($res->successful()) {
                        $deleted++;
                    }
                } catch (\Throwable $e) {
                    // ignore per-variation errors
                }
            }
        }

        return $deleted;
    }

    /**
     * List Woo variations for a variable product (best-effort).
     * Returns array of: [id:int, sku:string, option:?string, external_variant_id:?string]
     */
    private function listWooVariations(int $wooProductId, ?callable $emit = null, ?callable $shouldCancel = null): array
    {
        if ($wooProductId <= 0) {
            return [];
        }

        $out = [];
        $page = 1;
        $per = 100;
        $cap = (int) env('WOO_VARIATIONS_LIST_PAGE_CAP', 100);
        $cap = max(1, min(500, $cap));

        while ($page <= $cap) {
            if ($shouldCancel) {
                $shouldCancel();
            }

            if ($emit) {
                $emit([
                    'stage' => 'variations_list',
                    'variations_page' => $page,
                    'last_endpoint' => 'GET /products/'.$wooProductId.'/variations?page='.$page,
                ]);
            }

            $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                'page' => $page,
                'per_page' => $per,
                'context' => 'edit',
                '_fields' => 'id,sku,attributes,meta_data',
            ], 20, 5);

            if (!$res->successful()) {
                // Fallback: some stores block context=edit on GET.
                $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                    'page' => $page,
                    'per_page' => $per,
                    '_fields' => 'id,sku,attributes,meta_data',
                ], 20, 5);
            }

            if (!$res->successful()) {
                break;
            }

            $list = $res->json();
            if (!is_array($list) || empty($list)) {
                break;
            }

            foreach ($list as $v) {
                if (!is_array($v)) continue;
                $vid = (int) ($v['id'] ?? 0);
                if ($vid <= 0) continue;

                $sku = trim((string) ($v['sku'] ?? ''));
                $option = null;
                $attrs = $v['attributes'] ?? [];
                if (is_array($attrs)) {
                    foreach ($attrs as $a) {
                        if (!is_array($a)) continue;
                        $opt = trim((string) ($a['option'] ?? ''));
                        if ($opt !== '') {
                            $option = $opt;
                            break;
                        }
                    }
                }

                $external = null;
                $meta = $v['meta_data'] ?? [];
                if (is_array($meta)) {
                    foreach ($meta as $m) {
                        if (!is_array($m)) continue;
                        if (($m['key'] ?? null) === self::WOO_META_EXTERNAL_VARIANT_ID) {
                            $val = (string) ($m['value'] ?? '');
                            if ($val !== '') {
                                $external = $val;
                                break;
                            }
                        }
                    }
                }

                $out[] = [
                    'id' => $vid,
                    'sku' => $sku,
                    'option' => $option,
                    'external_variant_id' => $external,
                ];
            }

            if (count($list) < $per) {
                break;
            }
            $page++;
        }

        return $out;
    }

    /**
     * Fix Woo products that ended up "Uncategorized" because categories were not synced/mapped.
     * Updates Woo product categories based on local product->category woocommerce_id mapping.
     */
    public function fixWooUncategorizedProducts(?callable $progress = null): array
    {
        $fixed = 0;
        $skipped = 0;
        $skippedNoWooId = 0;
        $skippedNoCategoryMapping = 0;
        $skippedNoUncategorized = 0;
        $errors = 0;
        $processed = 0;
        $samples = [];
        $uncatId = $this->wooUncategorizedCategoryId();

        Product::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->orderBy('id')
            ->chunk(100, function ($products) use (
                &$fixed,
                &$skipped,
                &$skippedNoWooId,
                &$skippedNoCategoryMapping,
                &$skippedNoUncategorized,
                &$errors,
                &$processed,
                &$samples,
                $uncatId,
                $progress
            ) {
                foreach ($products as $product) {
                    $processed++;

                    try {
                        $wooId = (int) ($product->woocommerce_id ?? 0);
                        if ($wooId <= 0) {
                            $skipped++;
                            $skippedNoWooId++;
                            if (count($samples) < 20) {
                                $samples[] = ['product_id' => $product->id ?? null, 'woocommerce_id' => $wooId, 'reason' => 'missing_woocommerce_id'];
                            }
                            continue;
                        }

                        $cat = null;
                        try {
                            $cat = $product->category;
                        } catch (\Throwable $e) {
                            $cat = null;
                        }

                        $wooCatId = $cat && !empty($cat->woocommerce_id) ? (int) $cat->woocommerce_id : 0;
                        if ($wooCatId <= 0) {
                            $skipped++;
                            $skippedNoCategoryMapping++;
                            if (count($samples) < 20) {
                                $samples[] = ['product_id' => $product->id ?? null, 'woocommerce_id' => $wooId, 'reason' => 'missing_category_mapping'];
                            }
                            continue;
                        }

                        // If the Woo product has Uncategorized, replace it with the mapped Stocky category.
                        $existingIds = $this->wooProductCategoryIds($wooId);
                        $hasUncategorized = in_array((int) $uncatId, $existingIds, true);
                        if (!$hasUncategorized) {
                            $skipped++;
                            $skippedNoUncategorized++;
                            if (count($samples) < 20) {
                                $samples[] = ['product_id' => $product->id ?? null, 'woocommerce_id' => $wooId, 'reason' => 'no_uncategorized'];
                            }
                            continue;
                        }

                        // Keep existing categories (except Uncategorized), ensure mapped category is present.
                        $newIds = array_values(array_unique(array_filter($existingIds, function ($id) use ($uncatId) {
                            return (int) $id > 0 && (int) $id !== (int) $uncatId;
                        })));
                        if (!in_array($wooCatId, $newIds, true)) {
                            $newIds[] = $wooCatId;
                        }
                        $payloadCats = array_map(fn ($id) => ['id' => (int) $id], $newIds);

                        $res = $this->client->putNoRetry('products/'.$wooId, [
                            'categories' => $payloadCats,
                        ], 20, 5);

                        if (!$res->successful()) {
                            $errors++;
                            $this->log('products.fix_categories', 'error', 'Woo request failed', [
                                'product_id' => $product->id,
                                'woocommerce_id' => $wooId,
                                'status' => $res->status(),
                                'body' => $res->body(),
                            ]);
                            continue;
                        }

                        $fixed++;
                        if ($progress) {
                            $progress(['stage' => 'fixed', 'processed' => $processed, 'fixed' => $fixed, 'errors' => $errors, 'skipped' => $skipped]);
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->log('products.fix_categories', 'error', $e->getMessage(), ['product_id' => $product->id ?? null]);
                    }
                }
            });

        return [
            'ok' => true,
            'fixed' => $fixed,
            'skipped' => $skipped,
            'errors' => $errors,
            'processed' => $processed,
            'skipped_breakdown' => [
                'missing_woocommerce_id' => $skippedNoWooId,
                'missing_category_mapping' => $skippedNoCategoryMapping,
                'no_uncategorized' => $skippedNoUncategorized,
            ],
            'samples' => $samples,
        ];
    }

    /**
     * Strict validation: candidate exists AND matches expected SKU OR matches our internal meta mapping.
     * We always fetch with context=edit so meta_data is available.
     */
    private function validateWooProductIdStrict(int $wooId, ?string $expectedSku, int $localProductId): bool
    {
        if ($wooId <= 0) {
            return false;
        }

        $expectedSku = trim((string) ($expectedSku ?? ''));
        $res = $this->client->getNoRetry('products/'.$wooId, ['status' => 'any', 'context' => 'edit'], 10, 5);
        if (!$res->successful()) {
            // Fallback for stores that block context=edit on GET.
            $res = $this->client->getNoRetry('products/'.$wooId, ['status' => 'any'], 10, 5);
        }

        if (!$res->successful()) {
            return false;
        }

        $data = $res->json();
        if (!is_array($data)) {
            return false;
        }

        // Safety: never treat a Woo "variation" id as the product mapping.
        // If we accidentally store a variation id in products.woocommerce_id, title updates will appear to "not work"
        // because Woo displays the parent product name, not the variation.
        $remoteType = (string) ($data['type'] ?? '');
        if ($remoteType === 'variation') {
            return false;
        }

        $remoteSku = trim((string) ($data['sku'] ?? ''));
        $skuOk = ($expectedSku !== '' && $remoteSku !== '' && strcasecmp($remoteSku, $expectedSku) === 0);

        $meta1 = $this->wooMetaValue($data, self::WOO_META_EXTERNAL_PRODUCT_ID);
        $meta2 = $this->wooMetaValue($data, self::WOO_META_STOCKY_PRODUCT_ID);
        $metaOk = ($meta1 !== null && $meta1 === (string) $localProductId)
            || ($meta2 !== null && $meta2 === (string) $localProductId);

        // If meta is unavailable (fallback context), rely on SKU match.
        return $skuOk || $metaOk;
    }

    /**
     * Debug helper: fetch minimal identity for a Woo product id.
     */
    private function wooIdentityForLog(int $wooId): array
    {
        try {
            $res = $this->client->getNoRetry('products/'.$wooId, ['status' => 'any', 'context' => 'edit'], 10, 5);
            $status = $res->status();
            $data = $res->json();

            if (!$res->successful() || !is_array($data)) {
                return ['ok' => false, 'status' => $status, 'body' => $res->body()];
            }

            return [
                'ok' => true,
                'status' => $status,
                'type' => (string) ($data['type'] ?? ''),
                'sku'  => (string) ($data['sku'] ?? ''),
                'external_product_id' => $this->wooMetaValue($data, self::WOO_META_EXTERNAL_PRODUCT_ID),
                'stocky_product_id'   => $this->wooMetaValue($data, self::WOO_META_STOCKY_PRODUCT_ID),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // Remote index (IMPORTANT: avoids per-product /products?sku=... slow/hang)
    // ---------------------------------------------------------------------
    /**
     * Build remote maps:
     * - bySku: lowercase(sku) => woo_product_id
     * - byExternalId: (string)local_product_id => woo_product_id (from meta)
     *
     * This is faster + avoids the "GET /products?sku=Bow Ties" meta query path.
     */
    private function buildRemoteIndex(?callable $progress = null): array
    {
        $remoteBySku = [];
        $remoteByExternalId = [];

        try {
            $page = 1;
            $per  = 100;

            while (true) {
                if ($progress) {
                    $progress(['stage' => 'index_remote', 'remote_page' => $page]);
                }

                $res = $this->client->getNoRetry('products', [
                    'page'     => $page,
                    'per_page' => $per,
                    'status'   => 'any',
                    'context'  => 'edit',
                    '_fields'  => 'id,type,sku,meta_data',
                ], 20, 5);
                $hasMeta = true;
                if (!$res->successful()) {
                    // Fallback: remove context=edit (some stores block it).
                    $res2 = $this->client->getNoRetry('products', [
                        'page'     => $page,
                        'per_page' => $per,
                        'status'   => 'any',
                        '_fields'  => 'id,type,sku',
                    ], 20, 5);
                    if ($res2->successful()) {
                        $res = $res2;
                        $hasMeta = false;
                    }
                }

                if (!$res->successful()) {
                    $this->log('products.push', 'warning', 'Failed building remote index', [
                        'status' => $res->status(),
                        'body'   => $res->body(),
                        'page'   => $page,
                    ]);
                    break;
                }

                $items = $res->json();
                if (empty($items) || !is_array($items)) {
                    break;
                }

                foreach ($items as $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    $rid = (int) ($it['id'] ?? 0);
                    if ($rid <= 0) {
                        continue;
                    }

                    // Never index "variation" as product
                    $type = (string) ($it['type'] ?? '');
                    if ($type === 'variation') {
                        continue;
                    }

                    $sku = trim((string) ($it['sku'] ?? ''));
                    if ($sku !== '') {
                        $key = mb_strtolower($sku);
                        // Keep first occurrence; duplicates -> ambiguous, but we handle later via strict validation.
                        if (!isset($remoteBySku[$key])) {
                            $remoteBySku[$key] = $rid;
                        }
                    }

                    if ($hasMeta) {
                        $external = $this->wooMetaValue($it, self::WOO_META_EXTERNAL_PRODUCT_ID);
                        if ($external !== null && !isset($remoteByExternalId[$external])) {
                            $remoteByExternalId[$external] = $rid;
                        }

                        $stocky = $this->wooMetaValue($it, self::WOO_META_STOCKY_PRODUCT_ID);
                        if ($stocky !== null && !isset($remoteByExternalId[$stocky])) {
                            $remoteByExternalId[$stocky] = $rid;
                        }
                    }
                }

                if (count($items) < $per) {
                    break;
                }

                // Hard cap to avoid infinite loops (configurable for large stores)
                $cap = (int) env('WOO_REMOTE_INDEX_PAGE_CAP', 200);
                if ($cap > 0 && $page >= $cap) {
                    $this->log('products.push', 'warning', 'Remote index pagination capped', [
                        'page' => $page,
                        'cap' => $cap,
                        'note' => 'Increase WOO_REMOTE_INDEX_PAGE_CAP if your store has > '.($cap * $per).' products',
                    ]);
                    break;
                }

                $page++;
            }
        } catch (\Throwable $e) {
            $this->log('products.push', 'warning', 'Remote index failed with exception', ['error' => $e->getMessage()]);
        }

        return [$remoteBySku, $remoteByExternalId];
    }

    /**
     * Resolve Woo ID for a local product using ONLY strong sources:
     * 1) Remote meta mapping (external_product_id / _stocky_product_id) from cached index
     * 2) Remote SKU mapping from cached index
     * 3) Fallback to GET /products?sku=... (short timeout) ONLY if index has no answer
     *
     * Returns: >0 woo id, 0 not found, -1 ambiguous (should be treated as error)
     */
    private function resolveWooIdStrict(Product $product, array $remoteBySku, array $remoteByExternalId, ?callable $emit = null): int
    {
        $localId = (int) $product->id;
        $sku = trim((string) ($product->code ?? ''));

        // 1) meta mapping (best, no SKU constraints)
        $metaCandidate = $remoteByExternalId[(string) $localId] ?? null;
        if ($metaCandidate) {
            $cid = (int) $metaCandidate;
            if ($this->validateWooProductIdStrict($cid, $sku !== '' ? $sku : null, $localId)) {
                return $cid;
            }
        }

        // 2) cached SKU mapping (fast path; avoids /products?sku=... completely)
        if ($sku !== '') {
            $skuKey = mb_strtolower($sku);
            $skuCandidate = $remoteBySku[$skuKey] ?? null;
            if ($skuCandidate) {
                $cid = (int) $skuCandidate;
                if ($this->validateWooProductIdStrict($cid, $sku, $localId)) {
                    return $cid;
                }
                // If cached index points to wrong item, DO NOT fail immediately.
                // This can happen when:
                // - remote index was built without meta_data (context=edit blocked)
                // - Woo has duplicate/dirty SKU history
                // We fallback to direct SKU lookup which returns the full match set.
                if ($emit) {
                    $emit([
                        'stage' => 'sku_candidate_invalid',
                        'woocommerce_id' => $cid,
                    ]);
                }
            }
        }

        // 3) fallback to Woo SKU filter (short timeout) when index has no reliable mapping.
        if ($sku === '') {
            return 0;
        }

        if ($emit) {
            $emit([
                'stage' => 'sku_lookup',
                'last_endpoint' => 'GET /products?sku='.$sku,
                'substep' => 'http_start',
                'substep_at' => now()->toDateTimeString(),
                'last_http_started_at' => now()->toDateTimeString(),
                'worker_heartbeat_at' => now()->toDateTimeString(),
            ]);
        }

        $t0 = microtime(true);
        try {
            $res = $this->client->getNoRetry('products', [
                'sku' => $sku,
                'status' => 'any',
                '_fields' => 'id,type,sku,meta_data',
                'context' => 'edit',
            ], 10, 5);
            if (!$res->successful()) {
                // Fallback: some stores block context=edit on GET.
                $res = $this->client->getNoRetry('products', [
                    'sku' => $sku,
                    'status' => 'any',
                    '_fields' => 'id,type,sku',
                ], 10, 5);
            }

            if ($emit) {
                $emit([
                    'substep' => 'http_end',
                    'substep_at' => now()->toDateTimeString(),
                    'last_http_duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                    'status' => $res->status(),
                    'worker_heartbeat_at' => now()->toDateTimeString(),
                ]);
            }

            if (!$res->successful()) {
                return 0;
            }

            $list = $res->json();
            if (!is_array($list) || count($list) === 0) {
                // Woo can keep SKUs reserved in lookup table even when product is trashed.
                // Try to resolve in trash before declaring "not found".
                try {
                    if ($emit) {
                        $emit([
                            'stage' => 'sku_lookup_trash',
                            'last_endpoint' => 'GET /products?sku='.$sku.'&status=trash',
                            'substep' => 'http_start',
                            'substep_at' => now()->toDateTimeString(),
                            'last_http_started_at' => now()->toDateTimeString(),
                            'worker_heartbeat_at' => now()->toDateTimeString(),
                        ]);
                    }

                    $t1 = microtime(true);
                    $trashRes = $this->client->getNoRetry('products', [
                        'sku' => $sku,
                        'status' => 'trash',
                        '_fields' => 'id,type,sku,meta_data,parent_id',
                        'context' => 'edit',
                    ], 10, 5);
                    if (!$trashRes->successful()) {
                        $trashRes = $this->client->getNoRetry('products', [
                            'sku' => $sku,
                            'status' => 'trash',
                            '_fields' => 'id,type,sku,parent_id',
                        ], 10, 5);
                    }

                    if ($emit) {
                        $emit([
                            'substep' => 'http_end',
                            'substep_at' => now()->toDateTimeString(),
                            'last_http_duration_ms' => (int) round((microtime(true) - $t1) * 1000),
                            'status' => $trashRes->status(),
                            'worker_heartbeat_at' => now()->toDateTimeString(),
                        ]);
                    }

                    if ($trashRes->successful()) {
                        $trashList = $trashRes->json();
                        if (is_array($trashList) && count($trashList) > 0) {
                            $matches = [];
                            foreach ($trashList as $it) {
                                if (!is_array($it)) {
                                    continue;
                                }
                                if ((string) ($it['type'] ?? '') === 'variation') {
                                    // Link parent product id
                                    $pid = (int) ($it['parent_id'] ?? 0);
                                    if ($pid > 0) {
                                        $matches[$pid] = true;
                                    }
                                    continue;
                                }
                                $remoteSku = trim((string) ($it['sku'] ?? ''));
                                if ($remoteSku !== '' && strcasecmp($remoteSku, $sku) === 0) {
                                    $id = (int) ($it['id'] ?? 0);
                                    if ($id > 0) {
                                        $matches[$id] = true;
                                    }
                                }
                            }
                            if (count($matches) === 1) {
                                $wooId = (int) array_key_first($matches);
                                return $this->validateWooProductIdStrict($wooId, $sku, $localId) ? $wooId : -1;
                            }
                            return count($matches) === 0 ? 0 : -1;
                        }
                    }
                } catch (\Throwable $e) {
                }

                return 0;
            }

            $matches = [];
            foreach ($list as $it) {
                if (!is_array($it)) {
                    continue;
                }
                if ((string) ($it['type'] ?? '') === 'variation') {
                    continue;
                }
                $remoteSku = trim((string) ($it['sku'] ?? ''));
                if ($remoteSku !== '' && strcasecmp($remoteSku, $sku) === 0) {
                    $id = (int) ($it['id'] ?? 0);
                    if ($id > 0) {
                        $matches[$id] = true;
                    }
                }
            }

            if (count($matches) !== 1) {
                return count($matches) === 0 ? 0 : -1;
            }

            $wooId = (int) array_key_first($matches);

            // Final strict validation gate
            return $this->validateWooProductIdStrict($wooId, $sku, $localId) ? $wooId : -1;
        } catch (\Throwable $e) {
            if ($emit) {
                $emit([
                    'substep' => 'http_end',
                    'substep_at' => now()->toDateTimeString(),
                    'last_http_duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                    'last_http_error_type' => 'exception',
                    'last_http_error_message' => $e->getMessage(),
                    'worker_heartbeat_at' => now()->toDateTimeString(),
                ]);
            }
            return 0;
        }
    }

    // ---------------------------------------------------------------------
    // Pull products (Woo -> POS)
    // ---------------------------------------------------------------------
    public function syncProducts(?callable $progress = null): array
    {
        // Backward compatible alias
        return $this->pullProducts(false, $progress);
    }

    private function ensureDefaultCategoryId(): int
    {
        $id = (int) (\App\Models\Category::whereNull('deleted_at')->min('id') ?? 0);
        return $id > 0 ? $id : 1;
    }

    /**
     * Ensure a Woo category exists locally (create/restore + link by woocommerce_id).
     * Used by Woo -> Stocky product pull to satisfy "auto-create missing" requirement.
     */
    private function ensureLocalCategoryFromWoo(int $wooCatId, ?array $inline = null): ?PosCategory
    {
        static $cache = [];
        if ($wooCatId <= 0) {
            return null;
        }
        if (isset($cache[$wooCatId]) && $cache[$wooCatId] instanceof PosCategory) {
            return $cache[$wooCatId];
        }

        // Prefer existing mapping (including soft-deleted; we restore it).
        $cat = PosCategory::withTrashed()->where('woocommerce_id', $wooCatId)->first();
        if ($cat) {
            if ($cat->deleted_at !== null) {
                $cat->deleted_at = null;
                $cat->save();
            }
            $cache[$wooCatId] = $cat;
            return $cat;
        }

        $name = '';
        if (is_array($inline)) {
            $name = trim((string) ($inline['name'] ?? ''));
        }

        // Fetch from Woo if not provided inline.
        if ($name === '') {
            try {
                $res = $this->client->getNoRetry('products/categories/'.$wooCatId, [], 20, 5);
                if ($res->successful()) {
                    $body = $res->json();
                    if (is_array($body)) {
                        $name = trim((string) ($body['name'] ?? ''));
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($name === '') {
            return null;
        }

        try {
            DB::transaction(function () use ($wooCatId, $name, &$cat) {
                // Link by woocommerce_id, else by exact name.
                $cat = PosCategory::withTrashed()->firstOrNew(['woocommerce_id' => $wooCatId]);
                if (!$cat->exists) {
                    $cat = PosCategory::withTrashed()->where('name', $name)->first() ?? $cat;
                }

                $cat->name = $name;
                $cat->woocommerce_id = $wooCatId;
                $cat->code = $cat->code ?? ('CAT-'.$wooCatId);
                $cat->deleted_at = null;
                $cat->save();
            }, 3);
        } catch (\Throwable $e) {
            $this->log('products.pull', 'warning', 'Failed creating missing category from Woo', [
                'woocommerce_category_id' => $wooCatId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($cat) {
            $cache[$wooCatId] = $cat;
        }

        return $cat;
    }

    /**
     * Ensure a Woo brand exists locally (create/restore + link by woocommerce_id).
     * Used by Woo -> Stocky product pull to satisfy "auto-create missing" requirement.
     */
    private function ensureLocalBrandFromWoo(int $wooBrandId, ?array $inline = null): ?PosBrand
    {
        static $cache = [];
        if ($wooBrandId <= 0) {
            return null;
        }
        if (isset($cache[$wooBrandId]) && $cache[$wooBrandId] instanceof PosBrand) {
            return $cache[$wooBrandId];
        }

        $brand = PosBrand::withTrashed()->where('woocommerce_id', $wooBrandId)->first();
        if ($brand) {
            if ($brand->deleted_at !== null) {
                $brand->deleted_at = null;
                $brand->save();
            }
            $cache[$wooBrandId] = $brand;
            return $brand;
        }

        $name = '';
        $description = '';
        $imageSrc = '';
        if (is_array($inline)) {
            $name = trim((string) ($inline['name'] ?? ''));
            $description = (string) ($inline['description'] ?? '');
            $img = $inline['image'] ?? null;
            if (is_array($img)) {
                $imageSrc = (string) ($img['src'] ?? '');
            }
        }

        if ($name === '') {
            try {
                $res = $this->client->getNoRetry('products/brands/'.$wooBrandId, [], 20, 5);
                if ($res->successful()) {
                    $body = $res->json();
                    if (is_array($body)) {
                        $name = trim((string) ($body['name'] ?? ''));
                        $description = (string) ($body['description'] ?? $description);
                        $img = $body['image'] ?? null;
                        if (is_array($img)) {
                            $imageSrc = (string) ($img['src'] ?? $imageSrc);
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($name === '') {
            return null;
        }

        try {
            DB::transaction(function () use ($wooBrandId, $name, $description, $imageSrc, &$brand) {
                $brand = PosBrand::withTrashed()->firstOrNew(['woocommerce_id' => $wooBrandId]);
                if (!$brand->exists) {
                    $brand = PosBrand::withTrashed()->where('name', $name)->first() ?? $brand;
                }

                $brand->name = $name;
                $brand->description = (string) $description;
                $brand->woocommerce_id = $wooBrandId;
                $brand->deleted_at = null;

                // Best-effort: download and store brand image if we have a source.
                if (trim($imageSrc) !== '') {
                    $filename = $this->downloadBrandImage((string) $imageSrc, $wooBrandId);
                    if ($filename !== null) {
                        $brand->image = $filename;
                    }
                }

                // If Woo has no image, always keep a placeholder instead of NULL.
                // (Prevents broken images in UI and matches product placeholder behavior.)
                $currentImage = trim((string) ($brand->image ?? ''));
                if ($currentImage === '') {
                    $brand->image = 'no-image.png';
                }

                $brand->save();
            }, 3);
        } catch (\Throwable $e) {
            $this->log('products.pull', 'warning', 'Failed creating missing brand from Woo', [
                'woocommerce_brand_id' => $wooBrandId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($brand) {
            $cache[$wooBrandId] = $brand;
        }

        return $brand;
    }

    private function ensureDefaultUnitId(): int
    {
        try {
            $id = (int) (\App\Models\Unit::whereNull('deleted_at')->min('id') ?? 0);
            return $id > 0 ? $id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function ensureDefaultWarehouseId(): int
    {
        try {
            $settings = Setting::whereNull('deleted_at')->first();
            $candidate = $settings ? (int) ($settings->warehouse_id ?? 0) : 0;
            if ($candidate > 0 && Warehouse::where('id', $candidate)->whereNull('deleted_at')->exists()) {
                return $candidate;
            }
            $id = (int) (Warehouse::whereNull('deleted_at')->min('id') ?? 0);
            return $id > 0 ? $id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Ensure the given product exists in product_warehouse for ALL warehouses.
     *
     * - Creates missing rows with qte=0, manage_stock=1 (non-variant row: product_variant_id = null)
     * - Revives soft-deleted rows (sets deleted_at=null, qte=0)
     *
     * This is required so newly pulled products are immediately available across all warehouses.
     */
    private function ensureProductInAllWarehouses(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        try {
            $warehouseIds = Warehouse::whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            if (empty($warehouseIds)) {
                return;
            }

            $rows = DB::table('product_warehouse')
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->whereIn('warehouse_id', $warehouseIds)
                ->get(['warehouse_id', 'deleted_at']);

            $existingActive = [];
            $existingDeleted = [];
            foreach ($rows as $r) {
                $wid = (int) ($r->warehouse_id ?? 0);
                if ($wid <= 0) continue;
                if (!empty($r->deleted_at)) {
                    $existingDeleted[$wid] = true;
                } else {
                    $existingActive[$wid] = true;
                }
            }

            $now = now();

            $toInsert = [];
            foreach ($warehouseIds as $wid) {
                if (isset($existingActive[$wid]) || isset($existingDeleted[$wid])) {
                    continue;
                }
                $toInsert[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $wid,
                    'product_variant_id' => null,
                    'qte' => 0,
                    'manage_stock' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            if (!empty($toInsert)) {
                DB::table('product_warehouse')->insert($toInsert);
            }

            if (!empty($existingDeleted)) {
                DB::table('product_warehouse')
                    ->where('product_id', $productId)
                    ->whereNull('product_variant_id')
                    ->whereIn('warehouse_id', array_keys($existingDeleted))
                    ->update([
                        'deleted_at' => null,
                        'qte' => 0,
                        'manage_stock' => 1,
                        'updated_at' => $now,
                    ]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Ensure the given product VARIANT exists in product_warehouse for ALL warehouses.
     *
     * Creates missing rows with qte=0, manage_stock=1 for the variant (product_variant_id = $variantId).
     */
    private function ensureVariantInAllWarehouses(int $productId, int $variantId): void
    {
        if ($productId <= 0 || $variantId <= 0) {
            return;
        }

        try {
            $warehouseIds = Warehouse::whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            if (empty($warehouseIds)) {
                return;
            }

            $rows = DB::table('product_warehouse')
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->get(['warehouse_id', 'deleted_at']);

            $existingActive = [];
            $existingDeleted = [];
            foreach ($rows as $r) {
                $wid = (int) ($r->warehouse_id ?? 0);
                if ($wid <= 0) continue;
                if (!empty($r->deleted_at)) {
                    $existingDeleted[$wid] = true;
                } else {
                    $existingActive[$wid] = true;
                }
            }

            $now = now();

            $toInsert = [];
            foreach ($warehouseIds as $wid) {
                if (isset($existingActive[$wid]) || isset($existingDeleted[$wid])) {
                    continue;
                }
                $toInsert[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $wid,
                    'product_variant_id' => $variantId,
                    'qte' => 0,
                    'manage_stock' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            if (!empty($toInsert)) {
                DB::table('product_warehouse')->insert($toInsert);
            }

            if (!empty($existingDeleted)) {
                DB::table('product_warehouse')
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->whereIn('warehouse_id', array_keys($existingDeleted))
                    ->update([
                        'deleted_at' => null,
                        'qte' => 0,
                        'manage_stock' => 1,
                        'updated_at' => $now,
                    ]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Woo -> Stocky: pull variations for a variable product into product_variants and product_warehouse.
     */
    private function pullWooVariationsIntoStocky(Product $product, int $wooProductId, int $defaultWarehouseId, ?callable $shouldCancel = null): void
    {
        if ($wooProductId <= 0 || (int) $product->id <= 0) {
            return;
        }

        $page = 1;
        $per = 100;
        $cap = (int) env('WOO_PULL_VARIATIONS_PAGE_CAP', 50);
        $cap = max(1, min(500, $cap));

        while ($page <= $cap) {
            if ($shouldCancel) {
                $shouldCancel();
            }

            $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                'page' => $page,
                'per_page' => $per,
                'status' => 'any',
                'orderby' => 'id',
                'order' => 'asc',
                'context' => 'edit',
            ], 30, 5);

            if (!$res->successful()) {
                // Fallback for stores that block context=edit
                $res = $this->client->getNoRetry('products/'.$wooProductId.'/variations', [
                    'page' => $page,
                    'per_page' => $per,
                    'status' => 'any',
                    'orderby' => 'id',
                    'order' => 'asc',
                ], 30, 5);
            }

            if (!$res->successful()) {
                $this->log('variants.pull', 'warning', 'Failed fetching Woo variations', [
                    'woocommerce_id' => $wooProductId,
                    'status' => $res->status(),
                    'body' => $res->body(),
                    'page' => $page,
                ]);
                break;
            }

            $items = $res->json();
            if (!is_array($items) || empty($items)) {
                break;
            }

            foreach ($items as $v) {
                if (!is_array($v)) continue;
                $varWooId = (int) ($v['id'] ?? 0);
                if ($varWooId <= 0) continue;

                $sku = trim((string) ($v['sku'] ?? ''));
                if ($sku === '') {
                    $sku = (string) ($product->code ?? '').'-'.$varWooId;
                }

                // Build a readable variant name from attributes options (e.g. "Red / XL")
                $optParts = [];
                $attrs = $v['attributes'] ?? [];
                if (is_array($attrs)) {
                    foreach ($attrs as $a) {
                        if (!is_array($a)) continue;
                        $opt = trim((string) ($a['option'] ?? ''));
                        if ($opt !== '') {
                            $optParts[] = $opt;
                        }
                    }
                }
                $variantName = trim(implode(' / ', $optParts));
                if ($variantName === '') {
                    $variantName = 'Variant '.$varWooId;
                }

                $price = $v['regular_price'] ?? ($v['price'] ?? null);
                $priceF = ($price !== null && $price !== '' && is_numeric($price)) ? (float) $price : 0.0;

                DB::transaction(function () use ($product, $varWooId, $sku, $variantName, $priceF, $defaultWarehouseId, $v) {
                    // Find existing local variant by Woo variation id, else by code within the product.
                    $pv = ProductVariant::whereNull('deleted_at')
                        ->where('product_id', (int) $product->id)
                        ->where(function ($q) use ($varWooId, $sku) {
                            $q->where('woocommerce_variation_id', $varWooId);
                            if ($sku !== '') {
                                $q->orWhere('code', $sku);
                            }
                        })
                        ->first();

                    if (!$pv) {
                        $pv = new ProductVariant();
                        $pv->product_id = (int) $product->id;
                    }

                    $pv->woocommerce_variation_id = $varWooId;
                    $pv->name = $variantName;
                    $pv->code = $sku;
                    $pv->price = $priceF;
                    // Keep cost aligned to price (same policy as product pull)
                    $pv->cost = $priceF;
                    // Keep variant image default unless you implement per-variation image pull
                    if (empty($pv->image)) {
                        $pv->image = 'no-image.png';
                    }
                    $pv->save();

                    // Dimensions fallback (Woo variations -> Stocky product)
                    // Many Woo stores leave parent dimensions empty and set them on variations.
                    // Stocky stores dimensions on the product record, so fill missing fields from the first variation that provides them.
                    $vdims = $v['dimensions'] ?? null;
                    if (is_array($vdims)) {
                        $lenRaw = trim((string) ($vdims['length'] ?? ''));
                        $widRaw = trim((string) ($vdims['width'] ?? ''));
                        $heiRaw = trim((string) ($vdims['height'] ?? ''));

                        $len = ($lenRaw !== '' && is_numeric($lenRaw)) ? (float) $lenRaw : null;
                        $wid = ($widRaw !== '' && is_numeric($widRaw)) ? (float) $widRaw : null;
                        $hei = ($heiRaw !== '' && is_numeric($heiRaw)) ? (float) $heiRaw : null;

                        $dirty = false;
                        $curLen = $product->length;
                        $curWid = $product->width;
                        $curHei = $product->height;

                        // Treat 0 as "unset" (common when UI/DB defaulted to 0).
                        $lenEmpty = ($curLen === null) || (is_numeric($curLen) && (float) $curLen == 0.0);
                        $widEmpty = ($curWid === null) || (is_numeric($curWid) && (float) $curWid == 0.0);
                        $heiEmpty = ($curHei === null) || (is_numeric($curHei) && (float) $curHei == 0.0);

                        if ($lenEmpty && $len !== null) {
                            $product->length = $len;
                            $dirty = true;
                        }
                        if ($widEmpty && $wid !== null) {
                            $product->width = $wid;
                            $dirty = true;
                        }
                        if ($heiEmpty && $hei !== null) {
                            $product->height = $hei;
                            $dirty = true;
                        }

                        if ($dirty) {
                            $product->save();
                        }
                    }

                    // Ensure variant exists across ALL warehouses
                    $this->ensureVariantInAllWarehouses((int) $product->id, (int) $pv->id);

                    // Apply stock qty to DEFAULT warehouse (if Woo provides it)
                    $qty = $v['stock_quantity'] ?? null;
                    if ($qty !== null && $qty !== '' && is_numeric($qty)) {
                        $qtyF = (float) $qty;
                        $pw = product_warehouse::whereNull('deleted_at')
                            ->where('product_id', (int) $product->id)
                            ->where('warehouse_id', (int) $defaultWarehouseId)
                            ->where('product_variant_id', (int) $pv->id)
                            ->first();
                        if (!$pw) {
                            $pw = new product_warehouse();
                            $pw->product_id = (int) $product->id;
                            $pw->warehouse_id = (int) $defaultWarehouseId;
                            $pw->product_variant_id = (int) $pv->id;
                        }
                        $pw->qte = $qtyF;
                        if (property_exists($pw, 'manage_stock')) {
                            $pw->manage_stock = 1;
                        }
                        $pw->save();
                    }
                }, 3);
            }

            if (count($items) < $per) {
                break;
            }
            $page++;
        }
    }

    private function downloadWooProductImage(string $url, int $wooId): ?string
    {
        try {
            $url = trim($url);
            if ($url === '') {
                return null;
            }

            // Handle scheme-relative urls like //example.com/img.jpg
            if (str_starts_with($url, '//')) {
                $url = 'https:'.$url;
            }

            $res = Http::timeout(30)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'StockyWooSync/1.0',
                    'Accept' => '*/*',
                ])
                ->get($url);

            if (!$res->successful()) {
                $this->log('products.pull', 'warning', 'Failed downloading product image', [
                    'woocommerce_id' => $wooId,
                    'url' => $url,
                    'status' => $res->status(),
                    'content_type' => (string) ($res->header('Content-Type') ?? ''),
                ]);
                return null;
            }

            $body = $res->body();
            if ($body === '') {
                $this->log('products.pull', 'warning', 'Empty body when downloading product image', [
                    'woocommerce_id' => $wooId,
                    'url' => $url,
                    'status' => $res->status(),
                ]);
                return null;
            }

            // Determine extension from Content-Type (preferred), fall back to url path.
            $ext = 'jpg';
            $ct = strtolower((string) ($res->header('Content-Type') ?? ''));
            if (str_contains($ct, 'png')) $ext = 'png';
            elseif (str_contains($ct, 'webp')) $ext = 'webp';
            elseif (str_contains($ct, 'gif')) $ext = 'gif';
            elseif (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) $ext = 'jpg';
            else {
                $path = parse_url($url, PHP_URL_PATH);
                $guess = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
                if (in_array($guess, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $ext = $guess === 'jpeg' ? 'jpg' : $guess;
                }
            }

            $dir = public_path('images/products');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir)) {
                $this->log('products.pull', 'warning', 'Product images directory missing/unwritable', [
                    'woocommerce_id' => $wooId,
                    'dir' => $dir,
                ]);
                return null;
            }

            $filename = 'woo_product_'.$wooId.'_'.uniqid().'.'.$ext;
            $filePath = $dir.DIRECTORY_SEPARATOR.$filename;
            if (file_put_contents($filePath, $body) === false) {
                $this->log('products.pull', 'warning', 'Failed saving downloaded product image', [
                    'woocommerce_id' => $wooId,
                    'path' => $filePath,
                    'url' => $url,
                ]);
                return null;
            }

            return $filename;
        } catch (\Throwable $e) {
            $this->log('products.pull', 'warning', 'Product image download exception: '.$e->getMessage(), [
                'woocommerce_id' => $wooId,
                'url' => $url,
            ]);
            return null;
        }
    }

    /**
     * Extract a "cost" value from a WooCommerce product payload.
     *
     * WooCommerce core does not have a standard cost field, so we try common plugin/meta keys.
     */
    private function extractWooProductCost(array $wp): ?float
    {
        // Some plugins expose direct fields
        $directKeys = ['cost', 'cost_price', 'purchase_price', 'cog_cost', 'cogs_cost'];
        foreach ($directKeys as $k) {
            if (array_key_exists($k, $wp)) {
                $v = $wp[$k];
                if ($v === null || $v === '') continue;
                if (is_numeric($v)) return (float) $v;
                if (is_string($v)) {
                    $v2 = trim(str_replace([',', ' '], ['', ''], $v));
                    if ($v2 !== '' && is_numeric($v2)) return (float) $v2;
                }
            }
        }

        // Meta data array: [{id,key,value}, ...]
        $meta = $wp['meta_data'] ?? null;
        if (!is_array($meta) || empty($meta)) {
            return null;
        }

        // Common meta keys used by cost-of-goods plugins
        $metaKeys = [
            '_wc_cog_cost',           // WooCommerce Cost of Goods (varies)
            '_wc_cog_product_cost',   // alternative
            '_alg_wc_cog_cost',       // Algolia/Booster COG
            '_cog_cost',              // generic
            '_cogs_cost',             // generic
            '_cost',                  // generic
            'cost',                   // generic
            'cost_price',             // generic
            '_wcfm_product_cost',     // WCFM
            '_product_cost',          // generic
            '_purchase_price',        // generic
        ];

        foreach ($meta as $m) {
            if (!is_array($m)) continue;
            $key = (string) ($m['key'] ?? '');
            if ($key === '') continue;
            if (!in_array($key, $metaKeys, true)) continue;
            $val = $m['value'] ?? null;
            if ($val === null || $val === '') continue;
            if (is_numeric($val)) return (float) $val;
            if (is_string($val)) {
                $v2 = trim(str_replace([',', ' '], ['', ''], $val));
                if ($v2 !== '' && is_numeric($v2)) return (float) $v2;
            }
        }

        return null;
    }

    /**
     * Pull WooCommerce products into Stocky.
     *
     * - Maps by woocommerce_id first, then SKU(code)
     * - Creates missing products with safe defaults
     * - Best-effort maps category (Woo categories -> local categories by woocommerce_id)
     * - Best-effort maps brand (Woo product_brand -> local brands by woocommerce_id)
     * - Best-effort downloads the first Woo image into public/images/products
     * - Creates/updates product_warehouse qty for default warehouse when stock_quantity is provided
     */
    public function pullProducts(
        bool $onlyUnsynced = false,
        ?callable $progress = null,
        ?callable $shouldCancel = null,
        ?int $maxProducts = null,
        int $startPage = 1,
        int $startIndex = 0
    ): array {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = 0;
        $skipped = 0;

        $page = max(1, $startPage);
        $perPage = 50;
        $defaultCategoryId = $this->ensureDefaultCategoryId();
        $defaultUnitId = $this->ensureDefaultUnitId();
        $defaultWarehouseId = $this->ensureDefaultWarehouseId();
        $indexInPage = max(0, $startIndex);
        $done = false;
        $remoteTotal = null;

        while (true) {
            if ($shouldCancel) {
                $shouldCancel();
            }

            $res = $this->client->getNoRetry('products', [
                'page' => $page,
                'per_page' => $perPage,
                'status' => 'any',
                'orderby' => 'id',
                'order' => 'asc',
            ], 30, 5);

            if (!$res->successful()) {
                $this->log('products.pull', 'error', 'Failed fetching products page', [
                    'page' => $page,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                break;
            }

            if ($remoteTotal === null) {
                $hdr = $res->header('x-wp-total');
                if ($hdr !== null && $hdr !== '') {
                    $remoteTotal = (int) $hdr;
                }
            }

            $items = $res->json();
            if (!is_array($items) || empty($items)) {
                $done = true;
                break;
            }

            $countItems = count($items);
            for ($i = $indexInPage; $i < $countItems; $i++) {
                $wp = $items[$i];
                if ($maxProducts !== null && $processed >= $maxProducts) {
                    // stop mid-page; resume from same page at this index next run
                    $done = false;
                    $indexInPage = $i;
                    break 2;
                }

                $processed++;
                $wooId = (int) ($wp['id'] ?? 0);
                $sku = trim((string) ($wp['sku'] ?? ''));
                $name = trim((string) ($wp['name'] ?? ''));
                $type = (string) ($wp['type'] ?? 'simple');

                if ($wooId <= 0 || $name === '') {
                    $skipped++;
                    continue;
                }

                // We keep initial pull conservative: skip variations (handled via local variants in Stocky)
                if ($type === 'variation') {
                    $skipped++;
                    continue;
                }

                if ($onlyUnsynced) {
                    $exists = Product::whereNull('deleted_at')
                        ->where(function ($q) use ($wooId, $sku) {
                            $q->where('woocommerce_id', $wooId);
                            if ($sku !== '') {
                                $q->orWhere('code', $sku);
                            }
                        })
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                try {
                    $localProductId = 0;
                    $isVariable = ($type === 'variable');

                    DB::transaction(function () use (
                        $wp, $wooId, $sku, $name,
                        &$created, &$updated,
                        $defaultCategoryId, $defaultUnitId, $defaultWarehouseId,
                        &$localProductId, $isVariable
                    ) {
                        $product = Product::whereNull('deleted_at')->where('woocommerce_id', $wooId)->first();
                        if (!$product && $sku !== '') {
                            $product = Product::whereNull('deleted_at')->where('code', $sku)->first();
                        }
                        $isNew = false;
                        if (!$product) {
                            $isNew = true;
                            $product = new Product();
                            $product->Type_barcode = 'CODE128';
                            $product->type = $isVariable ? 'is_variant' : 'is_single';
                            $product->is_variant = $isVariable ? 1 : 0;
                            $product->is_active = 1;
                            $product->stock_alert = 0;
                            $product->tax_method = '1';
                            $product->TaxNet = 0;
                            $product->unit_id = $defaultUnitId;
                            $product->unit_sale_id = $defaultUnitId;
                            $product->unit_purchase_id = $defaultUnitId;
                            $product->category_id = $defaultCategoryId;
                            $product->cost = 0;
                        } else {
                            // Keep variant flag aligned with Woo type on update as well.
                            $product->type = $isVariable ? 'is_variant' : 'is_single';
                            $product->is_variant = $isVariable ? 1 : 0;
                        }

                        $product->woocommerce_id = $wooId;
                        $product->name = $name;
                        $product->code = $sku !== '' ? $sku : ('WC-'.$wooId);

                        $price = $wp['regular_price'] ?? ($wp['price'] ?? null);
                        if ($price !== null && $price !== '') {
                            $product->price = (float) $price;
                        } elseif ($isNew) {
                            $product->price = 0;
                        }

                        // Discount sync (Woo sale price -> Stocky fixed discount)
                        // Stocky encoding: discount_method "2" = fixed amount.
                        // Compute: discount = regular_price - sale_price (clamped to >= 0).
                        $product->discount_method = '2';
                        $saleRaw = trim((string) ($wp['sale_price'] ?? ''));
                        if ($saleRaw !== '' && is_numeric($saleRaw)) {
                            $base = (float) ($product->price ?? 0);
                            $sale = (float) $saleRaw;
                            $disc = $base - $sale;
                            $product->discount = ($disc > 0) ? round($disc, 2) : 0;
                        } else {
                            // No sale price => clear local discount to keep in sync
                            $product->discount = 0;
                        }

                        // Requirement: wholesale price must always match retail price
                        // (keep consistent for both new and updated products)
                        $product->wholesale_price = (float) ($product->price ?? 0);

                        // Requirement: cost must always match retail price
                        // (overrides any Woo "cost of goods" meta)
                        $product->cost = (float) ($product->price ?? 0);

                        // Weight sync (Woo -> Stocky)
                        // WooCommerce sends weight as a string (can be empty). Always overwrite local weight (including clearing it).
                        $wooWeightRaw = trim((string) ($wp['weight'] ?? ''));
                        $product->weight = ($wooWeightRaw !== '' && is_numeric($wooWeightRaw)) ? (float) $wooWeightRaw : null;

                        // Dimensions sync (Woo -> Stocky)
                        // Woo usually sends: { dimensions: { length, width, height } } (strings),
                        // but some stores/plugins may flatten to top-level keys.
                        // If Woo provides any dimensions container/keys, overwrite local values (including clearing).
                        $dims = $wp['dimensions'] ?? null;
                        $hasAnyDimsKey = is_array($dims) || array_key_exists('length', $wp) || array_key_exists('width', $wp) || array_key_exists('height', $wp);
                        if ($hasAnyDimsKey) {
                            $lenRaw = trim((string) (is_array($dims) ? ($dims['length'] ?? '') : ($wp['length'] ?? '')));
                            $widRaw = trim((string) (is_array($dims) ? ($dims['width'] ?? '') : ($wp['width'] ?? '')));
                            $heiRaw = trim((string) (is_array($dims) ? ($dims['height'] ?? '') : ($wp['height'] ?? '')));
                            $product->length = ($lenRaw !== '' && is_numeric($lenRaw)) ? (float) $lenRaw : null;
                            $product->width = ($widRaw !== '' && is_numeric($widRaw)) ? (float) $widRaw : null;
                            $product->height = ($heiRaw !== '' && is_numeric($heiRaw)) ? (float) $heiRaw : null;
                        }

                        // Description sync (Woo -> Stocky)
                        // Prefer full description, fallback to short description.
                        // Always overwrite local note (including clearing it) to keep in sync.
                        $descHtml = (string) ($wp['description'] ?? ($wp['short_description'] ?? ''));
                        $descPlain = trim(strip_tags($descHtml));
                        $product->note = $descPlain !== '' ? $descPlain : null;

                        // Category mapping (first Woo category)
                        $cats = $wp['categories'] ?? [];
                        if (is_array($cats) && !empty($cats)) {
                            $first = $cats[0];
                            $wooCatId = is_array($first) ? (int) ($first['id'] ?? 0) : (int) $first;
                            if ($wooCatId > 0) {
                                $inline = is_array($first) ? $first : null;
                                $localCat = $this->ensureLocalCategoryFromWoo($wooCatId, $inline);
                                if ($localCat) {
                                    $product->category_id = (int) $localCat->id;
                                }
                            }
                        }

                        // Brand mapping (first Woo brand)
                        $brands = $wp['brands'] ?? [];
                        if (is_array($brands) && !empty($brands)) {
                            $firstB = $brands[0];
                            $wooBrandId = is_array($firstB) ? (int) ($firstB['id'] ?? 0) : (int) $firstB;
                            if ($wooBrandId > 0) {
                                $inlineB = is_array($firstB) ? $firstB : null;
                                $localBrand = $this->ensureLocalBrandFromWoo($wooBrandId, $inlineB);
                                if ($localBrand) {
                                    $product->brand_id = (int) $localBrand->id;
                                }
                            }
                        }

                        // Image (first Woo image) -> download
                        $src = '';
                        $images = $wp['images'] ?? null;
                        if (is_array($images) && !empty($images)) {
                            $img0 = $images[0];
                            if (is_array($img0)) {
                                $src = (string) ($img0['src'] ?? ($img0['url'] ?? ''));
                            } elseif (is_string($img0)) {
                                $src = $img0;
                            }
                        }
                        // Some Woo versions/plugins expose a featured url separately.
                        if ($src === '') {
                            $src = (string) ($wp['featured_src'] ?? '');
                        }
                        // Fallback: some payloads may use `image` instead of `images`.
                        if ($src === '') {
                            $img = $wp['image'] ?? null;
                            if (is_array($img)) {
                                // Variation schema uses array-of-image; keep generic.
                                if (isset($img['src'])) {
                                    $src = (string) ($img['src'] ?? ($img['url'] ?? ''));
                                } elseif (isset($img[0]) && is_array($img[0])) {
                                    $src = (string) ($img[0]['src'] ?? ($img[0]['url'] ?? ''));
                                }
                            }
                        }

                        if ($src !== '') {
                            $filename = $this->downloadWooProductImage($src, $wooId);
                            // If download fails, treat as missing image.
                            $product->image = $filename ?: 'no-image.png';
                        } else {
                            // Woo has no image => force placeholder.
                            $product->image = 'no-image.png';
                        }

                        $product->save();
                        $localProductId = (int) $product->id;

                        // Ensure product is linked to ALL warehouses (qty defaults to 0).
                        // This is required so pulled products are available everywhere.
                        $this->ensureProductInAllWarehouses((int) $product->id);

                        // Stock (default warehouse)
                        $qty = $wp['stock_quantity'] ?? null;
                        if ($qty !== null && $qty !== '') {
                            $qtyF = (float) $qty;
                            $pw = product_warehouse::whereNull('deleted_at')
                                ->where('product_id', (int) $product->id)
                                ->where('warehouse_id', (int) $defaultWarehouseId)
                                ->whereNull('product_variant_id')
                                ->first();
                            if (!$pw) {
                                $pw = new product_warehouse();
                                $pw->product_id = (int) $product->id;
                                $pw->warehouse_id = (int) $defaultWarehouseId;
                                $pw->product_variant_id = null;
                            }
                            $pw->qte = $qtyF;
                            if (property_exists($pw, 'manage_stock')) {
                                $pw->manage_stock = 1;
                            }
                            $pw->save();
                        }

                        $isNew ? $created++ : $updated++;
                    }, 3);

                    // Variable products: pull variations as local variants and link them to ALL warehouses too.
                    if ($isVariable && $localProductId > 0) {
                        $p = Product::whereKey($localProductId)->first();
                        if ($p) {
                            $this->pullWooVariationsIntoStocky($p, $wooId, $defaultWarehouseId, $shouldCancel);
                        }
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->log('products.pull', 'error', $e->getMessage(), [
                        'woocommerce_id' => $wooId,
                        'sku' => $sku,
                    ]);
                }

                if ($progress) {
                    $progress([
                        'page' => $page,
                        'processed' => $processed,
                        'created' => $created,
                        'updated' => $updated,
                        'errors' => $errors,
                        'current_woocommerce_id' => $wooId,
                        'current_sku' => $sku,
                        'stage' => 'pulling',
                        'worker_heartbeat_at' => now()->toDateTimeString(),
                    ]);
                }
            }

            if (count($items) < $perPage) {
                $done = true;
                $indexInPage = 0;
                break;
            }
            // full page processed => next page, reset index
            $page++;
            $indexInPage = 0;
        }

        $this->log('products.pull', 'info', 'Products pull completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'processed' => $processed,
            'skipped' => $skipped,
            'remote_total' => $remoteTotal,
            'cursor_page' => $page,
            'cursor_index' => $indexInPage,
            'done' => (bool) $done,
        ];
    }

    // ---------------------------------------------------------------------
    // Push products (POS -> Woo)
    // ---------------------------------------------------------------------
    public function pushProducts(
        bool $onlyUnsynced = false,
        ?callable $progress = null,
        ?callable $shouldCancel = null,
        ?int $startAfterId = null,
        ?int $maxProducts = null,
        array $initial = []
    ): array
    {
        ini_set('max_execution_time', 2000);
        ini_set('memory_limit', '512M');

        $created = (int) ($initial['created'] ?? 0);
        $updated = (int) ($initial['updated'] ?? 0);
        $errors  = (int) ($initial['errors'] ?? 0);
        $processed = (int) ($initial['processed'] ?? 0);
        $missingSku = (int) ($initial['missing_sku'] ?? 0);
        $maxConsecutiveWooFailures = (int) env('WOO_MAX_CONSECUTIVE_FAILURES', 7);
        $maxConsecutiveWooFailures = max(1, min(100, $maxConsecutiveWooFailures));
        $consecutiveWooFailures = 0;

        // Build remote index ONCE (prevents per-product sku query hang)
        if ($shouldCancel) {
            $shouldCancel();
        }
        [$remoteBySku, $remoteByExternalId] = $this->buildRemoteIndex($progress);

        $query = Product::whereNull('deleted_at');
        if ($onlyUnsynced) {
            $query->whereNull('woocommerce_id');
        }
        if (is_int($startAfterId) && $startAfterId > 0) {
            $query->where('id', '>', $startAfterId);
        }

        $processedThisRun = 0;
        $maxProducts = is_int($maxProducts) && $maxProducts > 0 ? $maxProducts : null;

        $query->orderBy('id')->chunk(100, function ($products) use (
            &$created, &$updated, &$errors, &$processed, &$missingSku, $progress,
            &$remoteBySku, &$remoteByExternalId, $shouldCancel,
            &$maxConsecutiveWooFailures, &$consecutiveWooFailures,
            &$processedThisRun, $maxProducts
        ) {
            foreach ($products as $product) {
                if ($maxProducts !== null && $processedThisRun >= $maxProducts) {
                    // Stop chunking early (job-level batching / shared hosting safety).
                    return false;
                }
                if ($shouldCancel) {
                    $shouldCancel();
                }
                // Count as processed as soon as we start this product.
                // This prevents the UI being stuck at 0% while the first product is doing slow work (media/variants).
                $processedThisRun++;
                $processed++;
                $emit = function (array $extra = []) use (
                    $progress, &$created, &$updated, &$errors, &$processed, &$missingSku, $product
                ) {
                    if (!$progress) {
                        return;
                    }
                    $progress(array_merge([
                        'processed' => $processed,
                        'created'   => $created,
                        'updated'   => $updated,
                        'errors'    => $errors,
                        'missing_sku' => $missingSku,
                        'current_product_id' => (int) $product->id,
                        'current_sku' => (string) ($product->code ?? ''),
                        'worker_heartbeat_at' => now()->toDateTimeString(),
                    ], $extra));
                };

                $emit([
                    'stage' => 'start',
                    'substep' => null,
                    'substep_at' => null,
                    'last_endpoint' => null,
                    'last_http_started_at' => null,
                    'last_http_duration_ms' => null,
                    'last_http_error_type' => null,
                    'last_http_error_message' => null,
                ]);

                try {
                    if ($shouldCancel) {
                        $shouldCancel();
                    }
                    $sku = trim((string) ($product->code ?? ''));

                    if ($sku === '') {
                        $missingSku++;
                        try {
                            $product->woocommerce_missing_sku = 1;
                            $product->save();
                        } catch (\Throwable $e) {
                        }

                        $emit([
                            'stage' => 'missing_sku',
                            'missing_sku_reason' => 'empty',
                        ]);

                        $this->log('products.push', 'warning', 'Skipping product sync: missing local SKU', [
                            'product_id' => $product->id,
                            'code' => (string) ($product->code ?? ''),
                            'name' => (string) ($product->name ?? ''),
                        ]);

                        continue;
                    }

                    // Clear missing SKU marker if it was set before
                    try {
                        if (!empty($product->woocommerce_missing_sku)) {
                            $product->woocommerce_missing_sku = 0;
                            $product->save();
                        }
                    } catch (\Throwable $e) {
                    }

                    // ---- STRICT resolve (prefer cached index; fallback to /products?sku only if needed) ----
                    $emit(['stage' => 'sku_lookup']);

                    // IMPORTANT for already-synced products:
                    // Prefer the locally stored mapping if it still strictly validates.
                    // This avoids missing updates when the remote index is incomplete (page cap),
                    // or when SKU resolution is slow/ambiguous.
                    $resolvedWooId = 0;
                    try {
                        $existingLocalWooId = (int) ($product->woocommerce_id ?? 0);
                        if ($existingLocalWooId > 0) {
                            $emit(['stage' => 'validate_existing_mapping', 'woocommerce_id' => $existingLocalWooId]);
                            if ($this->validateWooProductIdStrict($existingLocalWooId, $sku !== '' ? $sku : null, (int) $product->id)) {
                                $resolvedWooId = $existingLocalWooId;
                                // Update caches for faster next lookups
                                $remoteBySku[mb_strtolower($sku)] = (int) $existingLocalWooId;
                                $remoteByExternalId[(string) $product->id] = (int) $existingLocalWooId;
                            }
                        }
                    } catch (\Throwable $e) {
                        $resolvedWooId = 0;
                    }

                    if ($resolvedWooId <= 0) {
                        $resolvedWooId = $this->resolveWooIdStrict($product, $remoteBySku, $remoteByExternalId, $emit);
                    }

                    if ($resolvedWooId === -1) {
                        $errors++;
                        $emit([
                            'stage' => 'ambiguous_match',
                            'match' => 'sku_or_meta',
                            'last_http_error_type' => 'ambiguous',
                            'last_http_error_message' => 'Ambiguous Woo match (SKU/meta could not be validated uniquely) for SKU '.$sku,
                        ]);
                        $this->log('products.push', 'error', 'Ambiguous Woo match (cached mapping failed strict validation)', [
                            'reason' => 'ambiguous_match',
                            'product_id' => $product->id,
                            'sku' => $sku,
                        ]);
                        continue;
                    }

                    if ($resolvedWooId > 0) {
                        // Ensure local mapping is aligned
                        if ((int) $product->woocommerce_id !== (int) $resolvedWooId) {
                            $product->woocommerce_id = (int) $resolvedWooId;
                            $product->save();
                        }

                        // Update caches
                        $remoteBySku[mb_strtolower($sku)] = (int) $resolvedWooId;
                        $remoteByExternalId[(string) $product->id] = (int) $resolvedWooId;

                        $emit(['stage' => 'sku_found', 'woocommerce_id' => (int) $resolvedWooId]);
                    } else {
                        // Not found remotely (or fallback failed) => we will create
                        if (!empty($product->woocommerce_id)) {
                            $product->woocommerce_id = null;
                            $product->save();
                        }
                        $emit(['stage' => 'sku_not_found']);
                    }

                    // ---- Categories payload (only if already mapped) ----
                    $categoriesPayload = [];
                    try {
                        $cat = $product->category;
                        if ($cat && !empty($cat->woocommerce_id)) {
                            $categoriesPayload[] = ['id' => (int) $cat->woocommerce_id];
                        } elseif ($cat) {
                            $this->log('products.push', 'warning', 'Missing Woo category mapping', [
                                'product_id' => $product->id,
                                'category_id' => $cat->id ?? null,
                                'category_name' => $cat->name ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->log('products.push', 'warning', 'Category resolution failed', ['product_id' => $product->id]);
                    }

                    // ---- Tags payload (brands synced as WooCommerce product tags) ----
                    $tagsPayload = [];
                    try {
                        $brand = $product->brand;
                        if ($brand && !empty($brand->woocommerce_id)) {
                            $tagsPayload[] = ['id' => (int) $brand->woocommerce_id];
                        } elseif ($brand) {
                            $this->log('products.push', 'warning', 'Missing Woo brand mapping', [
                                'product_id' => $product->id,
                                'brand_id' => $brand->id ?? null,
                                'brand_name' => $brand->name ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->log('products.push', 'warning', 'Brand resolution failed', ['product_id' => $product->id]);
                    }

                    $isVariant = (int) ($product->is_variant ?? 0) === 1;

                    // ---- Base payload ----
                    $payload = [
                        'name'   => (string) ($product->name ?? ''),
                        'type'   => $isVariant ? 'variable' : 'simple',
                        'sku'    => $sku,
                        'status' => 'publish',
                        'meta_data' => [
                            ['key' => self::WOO_META_EXTERNAL_PRODUCT_ID, 'value' => (string) $product->id],
                            ['key' => self::WOO_META_STOCKY_PRODUCT_ID,   'value' => (string) $product->id],
                        ],
                    ];

                    // Weight sync (Stocky -> Woo)
                    // Woo expects string; send empty string to clear.
                    $w = $product->weight;
                    $payload['weight'] = ($w !== null && $w !== '' && is_numeric($w)) ? number_format((float) $w, 2, '.', '') : '';

                    // Dimensions sync (Stocky -> Woo)
                    // Woo expects strings; send empty string to clear.
                    $len = $product->length;
                    $wid = $product->width;
                    $hei = $product->height;
                    $payload['dimensions'] = [
                        'length' => ($len !== null && $len !== '' && is_numeric($len)) ? number_format((float) $len, 2, '.', '') : '',
                        'width' => ($wid !== null && $wid !== '' && is_numeric($wid)) ? number_format((float) $wid, 2, '.', '') : '',
                        'height' => ($hei !== null && $hei !== '' && is_numeric($hei)) ? number_format((float) $hei, 2, '.', '') : '',
                    ];

                    // Description sync (Stocky -> Woo)
                    // Keep Woo `description` and `short_description` identical to Stocky `note`.
                    // Include empty string to allow clearing.
                    $note = (string) ($product->note ?? '');
                    $payload['description'] = $note;
                    $payload['short_description'] = $note;

                    if (!$isVariant) {
                        $payload['regular_price'] = number_format((float) ($product->price ?? 0), 2, '.', '');

                        // Discount sync (Stocky -> Woo via sale_price)
                        // Woo uses `sale_price` (string). Stocky stores discount + discount_method.
                        // Encodings:
                        // - discount_method "1" = percent
                        // - discount_method "2" = fixed
                        $regular = (float) ($product->price ?? 0);
                        $dmRaw = $product->discount_method ?? null;
                        $dm = null; // 'percent' | 'fixed' | null
                        if (is_string($dmRaw)) {
                            $dmRaw = trim(strtolower($dmRaw));
                            if ($dmRaw === '1' || $dmRaw === 'percent') {
                                $dm = 'percent';
                            } elseif ($dmRaw === '2' || $dmRaw === 'fixed') {
                                $dm = 'fixed';
                            }
                        } elseif (is_int($dmRaw) || is_float($dmRaw)) {
                            if ((string) $dmRaw === '1') {
                                $dm = 'percent';
                            } elseif ((string) $dmRaw === '2') {
                                $dm = 'fixed';
                            }
                        }

                        $discVal = (float) ($product->discount ?? 0);
                        $sale = null;
                        if ($regular > 0 && $discVal > 0 && $dm === 'fixed') {
                            $sale = $regular - $discVal;
                        } elseif ($regular > 0 && $discVal > 0 && $dm === 'percent') {
                            $sale = $regular * (1 - ($discVal / 100));
                        }

                        // Clamp and format for Woo; empty string clears sale.
                        if ($sale !== null) {
                            $sale = round(max(0, min($sale, $regular)), 2);
                        }
                        $payload['sale_price'] = ($sale !== null && $sale > 0 && $sale < $regular)
                            ? number_format((float) $sale, 2, '.', '')
                            : '';
                    } else {
                        // Variant attribute options
                        try {
                            $variantNames = ProductVariant::where('product_id', $product->id)
                                ->whereNull('deleted_at')
                                ->pluck('name')
                                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                                ->unique()
                                ->values()
                                ->all();

                            if (!empty($variantNames)) {
                                $payload['attributes'] = [[
                                    'name' => 'Variant',
                                    'visible' => true,
                                    'variation' => true,
                                    'options' => array_values($variantNames),
                                ]];
                            }
                        } catch (\Throwable $e) {
                            $this->log('products.push', 'warning', 'Failed preparing variant attributes', ['product_id' => $product->id]);
                        }
                    }

                    if (!empty($categoriesPayload)) {
                        $payload['categories'] = $categoriesPayload;
                    }
                    if (!empty($tagsPayload)) {
                        $payload['brands'] = $tagsPayload;
                    }

                    // Image (best-effort, don’t block the sync)
                    // Attach on create ALWAYS (if local image exists). On update, attach ONLY if Woo has no images.
                    if (empty($product->woocommerce_id)) {
                        try {
                            if ($shouldCancel) {
                                $shouldCancel();
                            }
                            $emit(['stage' => 'media']);
                            $images = $this->buildWooProductImagesPayload($product, $shouldCancel);
                            if ($images) {
                                $payload['images'] = $images;
                            }
                        } catch (\Throwable $e) {
                        }
                    }

                    // ---- Write to Woo (update if mapped; otherwise create) ----
                    $res = null;
                    $wasUpdate = false;

                    if (!empty($product->woocommerce_id)) {
                        $emit(['stage' => 'woo_update']);

                        // Keep updates minimal to avoid expensive operations
                        $updatePayload = $payload;
                        // Categories can be expensive to update; only apply them if the remote
                        // product is currently uncategorized and we have a mapped category.
                        if (!empty($categoriesPayload)) {
                            $needsFix = $this->wooProductNeedsCategoryFix((int) $product->woocommerce_id);
                            if (!$needsFix) {
                                unset($updatePayload['categories']);
                            }
                        } else {
                            unset($updatePayload['categories']);
                        }

                        // ---- Variant type conversion (already-synced products) ----
                        // WooCommerce does NOT reliably change a product from variable -> simple
                        // if variations still exist. We must delete variations + clear attributes first.
                        try {
                            $desiredType = $isVariant ? 'variable' : 'simple';
                            $remoteType = $this->wooProductType((int) $product->woocommerce_id);

                            if ($remoteType !== null && $remoteType !== $desiredType) {
                                if (!$isVariant && $remoteType === 'variable') {
                                    $emit([
                                        'stage' => 'type_convert',
                                        'from' => $remoteType,
                                        'to' => $desiredType,
                                    ]);

                                    // Delete remote variations then clear local mappings (best-effort).
                                    $deleted = $this->deleteAllWooVariations((int) $product->woocommerce_id, $emit, $shouldCancel);
                                    try {
                                        ProductVariant::where('product_id', $product->id)
                                            ->whereNull('deleted_at')
                                            ->update(['woocommerce_variation_id' => null]);
                                    } catch (\Throwable $e) {
                                    }

                                    // Explicitly clear attributes/default_attributes on the parent.
                                    $updatePayload['attributes'] = [];
                                    $updatePayload['default_attributes'] = [];

                                    $this->log('products.push', 'info', 'Converted Woo product variable -> simple', [
                                        'product_id' => (int) $product->id,
                                        'woocommerce_id' => (int) $product->woocommerce_id,
                                        'deleted_variations' => (int) $deleted,
                                    ]);
                                } elseif ($isVariant) {
                                    $emit([
                                        'stage' => 'type_convert',
                                        'from' => $remoteType,
                                        'to' => $desiredType,
                                    ]);

                                    // When converting simple -> variable, clear any parent price remnants.
                                    $updatePayload['regular_price'] = '';
                                    $updatePayload['sale_price'] = '';

                                    // Ensure we send at least the attribute scaffold.
                                    if (empty($updatePayload['attributes']) || !is_array($updatePayload['attributes'])) {
                                        try {
                                            $variantNames = ProductVariant::where('product_id', $product->id)
                                                ->whereNull('deleted_at')
                                                ->pluck('name')
                                                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                                                ->unique()
                                                ->values()
                                                ->all();

                                            if (!empty($variantNames)) {
                                                $updatePayload['attributes'] = [[
                                                    'name' => 'Variant',
                                                    'visible' => true,
                                                    'variation' => true,
                                                    'options' => array_values($variantNames),
                                                ]];
                                            }
                                        } catch (\Throwable $e) {
                                        }
                                    }

                                    $this->log('products.push', 'info', 'Converted Woo product -> variable', [
                                        'product_id' => (int) $product->id,
                                        'woocommerce_id' => (int) $product->woocommerce_id,
                                        'from' => $remoteType,
                                    ]);
                                }
                            }
                        } catch (\Throwable $e) {
                            // best-effort; do not block product sync
                        }

                        // If the remote product has no image, attach/upload one during update.
                        try {
                            $hasLocalImage = $this->productMainImageSrc($product) !== null;
                            if ($hasLocalImage) {
                                $remoteHasImage = $this->wooProductHasAnyImage((int) $product->woocommerce_id);
                                if ($remoteHasImage === false) {
                                    if ($shouldCancel) {
                                        $shouldCancel();
                                    }
                                    $emit(['stage' => 'media']);
                                    $images = $this->buildWooProductImagesPayload($product, $shouldCancel);
                                    if ($images) {
                                        $updatePayload['images'] = $images;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                        }

                        $t0 = microtime(true);
                        try {
                            $emit([
                                'stage' => 'woo_update_request',
                                'substep' => 'http_start',
                                'substep_at' => now()->toDateTimeString(),
                                'last_http_started_at' => now()->toDateTimeString(),
                                'last_endpoint' => 'PUT /products/'.(string) $product->woocommerce_id,
                                'last_payload_bytes' => strlen(json_encode($updatePayload)),
                            ]);

                            $res = $this->client->putNoRetry('products/'.$product->woocommerce_id, $updatePayload, 20, 5);

                            $emit([
                                'substep' => 'http_end',
                                'substep_at' => now()->toDateTimeString(),
                                'last_http_duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                                'status' => $res->status(),
                            ]);
                        } catch (\Throwable $e) {
                            $emit([
                                'substep' => 'http_end',
                                'substep_at' => now()->toDateTimeString(),
                                'last_http_duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                                'last_http_error_type' => 'exception',
                                'last_http_error_message' => $e->getMessage(),
                            ]);
                            $res = null;
                        }

                        // Post-update verification (name is critical UX).
                        // Some Woo setups/plugins can appear to accept the request but not apply `name`.
                        try {
                            if ($res && $res->successful()) {
                                $localName = trim((string) ($product->name ?? ''));
                                $body = $res->json();
                                $remoteName = is_array($body) ? trim((string) ($body['name'] ?? '')) : '';

                                $norm = function (string $s): string {
                                    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    $s = preg_replace('/\s+/', ' ', $s);
                                    return trim((string) $s);
                                };

                                if ($localName !== '' && $remoteName !== '' && $norm($localName) !== $norm($remoteName)) {
                                    if ($emit) {
                                        $emit([
                                            'stage' => 'woo_name_verify_mismatch',
                                            'remote_name' => $remoteName,
                                            'local_name' => $localName,
                                        ]);
                                    }

                                    // Retry a minimal patch (best-effort).
                                    $resName = $this->client->putNoRetry('products/'.$product->woocommerce_id, [
                                        'name' => $localName,
                                    ], 20, 5);

                                    if (!$resName->successful()) {
                                        $this->log('products.push', 'warning', 'Woo name update did not apply (retry failed)', [
                                            'product_id' => (int) $product->id,
                                            'woocommerce_id' => (int) $product->woocommerce_id,
                                            'status' => $resName->status(),
                                            'body' => $resName->body(),
                                            'local_name' => $localName,
                                            'remote_name' => $remoteName,
                                        ]);
                                    } else {
                                        $this->log('products.push', 'warning', 'Woo name mismatch detected; applied minimal retry', [
                                            'product_id' => (int) $product->id,
                                            'woocommerce_id' => (int) $product->woocommerce_id,
                                            'local_name' => $localName,
                                            'remote_name' => $remoteName,
                                        ]);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // Do not block sync on verification failure.
                        }

                        // Remote id invalid / not found -> create fallback
                        if (!$res || $res->status() === 404) {
                            $emit(['stage' => 'woo_create_fallback']);
                            // If we are forced to create, attach media (and upload only if missing).
                            try {
                                if ($shouldCancel) {
                                    $shouldCancel();
                                }
                                $emit(['stage' => 'media']);
                                $images = $this->buildWooProductImagesPayload($product, $shouldCancel);
                                if ($images) {
                                    $payload['images'] = $images;
                                }
                            } catch (\Throwable $e) {
                            }
                            $res = $this->client->postNoRetry('products', $payload, 20, 5);
                            $wasUpdate = false;
                        } else {
                            $wasUpdate = $res->successful();
                        }
                    } else {
                        $emit(['stage' => 'woo_create']);
                        $res = $this->client->postNoRetry('products', $payload, 20, 5);
                        $wasUpdate = false;
                    }

                    if (!$res || !$res->successful()) {
                        // Woo can respond 400 when SKU exists but our lookup/index didn't find it.
                        // In that case:
                        // - First, try to find/link the existing product by SKU and treat as update.
                        // - If Woo still can't return it (often trashed / lookup table bug), create with an alternate SKU
                        //   while keeping our external id mapping in meta_data.
                        $bodyText = $res ? (string) $res->body() : '';
                        $bodyJson = null;
                        try {
                            $bodyJson = $res ? $res->json() : null;
                        } catch (\Throwable $e) {
                            $bodyJson = null;
                        }
                        $errCode = is_array($bodyJson) ? (string) ($bodyJson['code'] ?? '') : '';
                        $errMessage = is_array($bodyJson) ? (string) ($bodyJson['message'] ?? '') : $bodyText;

                        // Be robust: different Woo/hosting setups can change message formatting.
                        $isSkuLookupConflict = $res
                            && $res->status() === 400
                            && (
                                (stripos($bodyText, 'lookup table') !== false)
                                || (stripos($errMessage, 'lookup table') !== false)
                                || ($errCode !== '' && stripos($errCode, 'lookup') !== false)
                                || ($errCode === 'woocommerce_rest_product_not_created' && stripos($errMessage, 'SKU') !== false)
                            );

                        if ($isSkuLookupConflict) {
                            $emit(['stage' => 'sku_conflict_lookup']);
                            try {
                                $existingId = 0;

                                // Attempt 1: direct SKU filter (fast path)
                                $find = $this->client->getNoRetry('products', [
                                    'sku' => $sku,
                                    'status' => 'any',
                                    'per_page' => 100,
                                    '_fields' => 'id,sku,type,parent_id',
                                ], 10, 5);

                                if ($find->successful()) {
                                    $list = $find->json();
                                    if (is_array($list)) {
                                        foreach ($list as $it) {
                                            if (!is_array($it)) {
                                                continue;
                                            }
                                            $rid = (int) ($it['id'] ?? 0);
                                            $rsku = trim((string) ($it['sku'] ?? ''));
                                            $type = (string) ($it['type'] ?? '');
                                            $parentId = (int) ($it['parent_id'] ?? 0);
                                            if ($rid > 0 && $rsku !== '' && strcasecmp($rsku, $sku) === 0) {
                                                // If Woo returns a variation match, link the parent product id (not variation id).
                                                $existingId = ($type === 'variation' && $parentId > 0) ? $parentId : $rid;
                                                break;
                                            }
                                        }
                                    }
                                }

                                // Attempt 1b: direct SKU filter in TRASH (Woo keeps SKU reserved while trashed)
                                if ($existingId <= 0) {
                                    $findTrash = $this->client->getNoRetry('products', [
                                        'sku' => $sku,
                                        'status' => 'trash',
                                        'per_page' => 100,
                                        '_fields' => 'id,sku,type,parent_id',
                                        'context' => 'edit',
                                    ], 10, 5);

                                    if ($findTrash->successful()) {
                                        $listT = $findTrash->json();
                                        if (is_array($listT)) {
                                            foreach ($listT as $it) {
                                                if (!is_array($it)) {
                                                    continue;
                                                }
                                                $rid = (int) ($it['id'] ?? 0);
                                                $rsku = trim((string) ($it['sku'] ?? ''));
                                                $type = (string) ($it['type'] ?? '');
                                                $parentId = (int) ($it['parent_id'] ?? 0);
                                                if ($rid > 0 && $rsku !== '' && strcasecmp($rsku, $sku) === 0) {
                                                    $existingId = ($type === 'variation' && $parentId > 0) ? $parentId : $rid;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }

                                // Attempt 2: search by text then filter by SKU
                                if ($existingId <= 0) {
                                    $page2 = 1;
                                    $per2 = 100;
                                    $cap2 = (int) env('WOO_SKU_SEARCH_PAGE_CAP', 5);
                                    $cap2 = max(1, min(50, $cap2));

                                    while ($existingId <= 0 && $page2 <= $cap2) {
                                        $find2 = $this->client->getNoRetry('products', [
                                            'search' => $sku,
                                            'status' => 'any',
                                            'per_page' => $per2,
                                            'page' => $page2,
                                            '_fields' => 'id,sku,type,parent_id',
                                        ], 10, 5);

                                        if (!$find2->successful()) {
                                            break;
                                        }

                                        $list2 = $find2->json();
                                        if (!is_array($list2) || count($list2) === 0) {
                                            break;
                                        }

                                        foreach ($list2 as $it) {
                                            if (!is_array($it)) {
                                                continue;
                                            }
                                            $rid = (int) ($it['id'] ?? 0);
                                            $rsku = trim((string) ($it['sku'] ?? ''));
                                            $type = (string) ($it['type'] ?? '');
                                            $parentId = (int) ($it['parent_id'] ?? 0);
                                            if ($rid > 0 && $rsku !== '' && strcasecmp($rsku, $sku) === 0) {
                                                $existingId = ($type === 'variation' && $parentId > 0) ? $parentId : $rid;
                                                break;
                                            }
                                        }

                                        if (count($list2) < $per2) {
                                            break;
                                        }
                                        $page2++;
                                    }
                                }

                                // Attempt 2b: search in TRASH as well
                                if ($existingId <= 0) {
                                    $page3 = 1;
                                    $per3 = 100;
                                    $cap3 = (int) env('WOO_SKU_SEARCH_PAGE_CAP', 5);
                                    $cap3 = max(1, min(50, $cap3));

                                    while ($existingId <= 0 && $page3 <= $cap3) {
                                        $find3 = $this->client->getNoRetry('products', [
                                            'search' => $sku,
                                            'status' => 'trash',
                                            'per_page' => $per3,
                                            'page' => $page3,
                                            '_fields' => 'id,sku,type,parent_id',
                                            'context' => 'edit',
                                        ], 10, 5);

                                        if (!$find3->successful()) {
                                            break;
                                        }

                                        $list3 = $find3->json();
                                        if (!is_array($list3) || count($list3) === 0) {
                                            break;
                                        }

                                        foreach ($list3 as $it) {
                                            if (!is_array($it)) {
                                                continue;
                                            }
                                            $rid = (int) ($it['id'] ?? 0);
                                            $rsku = trim((string) ($it['sku'] ?? ''));
                                            $type = (string) ($it['type'] ?? '');
                                            $parentId = (int) ($it['parent_id'] ?? 0);
                                            if ($rid > 0 && $rsku !== '' && strcasecmp($rsku, $sku) === 0) {
                                                $existingId = ($type === 'variation' && $parentId > 0) ? $parentId : $rid;
                                                break;
                                            }
                                        }

                                        if (count($list3) < $per3) {
                                            break;
                                        }
                                        $page3++;
                                    }
                                }

                                // If found, link it and (best-effort) update it
                                if ($existingId > 0) {
                                    $product->woocommerce_id = $existingId;
                                    $product->save();

                                    $remoteBySku[mb_strtolower($sku)] = $existingId;
                                    $remoteByExternalId[(string) $product->id] = $existingId;

                                    $emit(['stage' => 'sku_conflict_linked', 'woocommerce_id' => $existingId]);

                                    $updatePayload = $payload;
                                    unset($updatePayload['images']);
                                    unset($updatePayload['categories']);
                                    try {
                                        $res2 = $this->client->putNoRetry('products/'.$existingId, $updatePayload, 20, 5);
                                        if ($res2->successful()) {
                                            $updated++;
                                            continue;
                                        }
                                    } catch (\Throwable $e) {
                                    }

                                    // Even if update fails, we linked the mapping; don't block the whole sync on this SKU conflict.
                                    $updated++;
                                    continue;
                                }

                                // SKU is blocked in lookup table but we couldn't find the product (often TRASH or Woo lookup bug).
                                // Default behavior: DO NOT create. Instead log and skip so user can restore or permanently delete in Woo.
                                $allowAlt = (bool) env('WOO_ALLOW_ALT_SKU_ON_LOOKUP_CONFLICT', false);
                                if (!$allowAlt) {
                                    $errors++;
                                    $emit(['stage' => 'sku_conflict_blocked', 'sku' => $sku]);
                                    $this->log('products.push', 'error', 'SKU is blocked in Woo lookup table; cannot create. Restore or permanently delete the trashed Woo product, then re-sync.', [
                                        'product_id' => $product->id,
                                        'sku' => $sku,
                                        'woocommerce_id_local' => $product->woocommerce_id ?? null,
                                        'woo_error_code' => $errCode,
                                        'woo_error_message' => $errMessage,
                                    ]);
                                    continue;
                                }

                                // Optional workaround: create with alternate SKU and store original SKU in meta.
                                $altSku = $sku.'-'.$product->id;
                                $emit(['stage' => 'sku_conflict_alt_create', 'alt_sku' => $altSku]);

                                $payloadAlt = $payload;
                                $payloadAlt['sku'] = $altSku;
                                $payloadAlt['meta_data'][] = ['key' => '_stocky_original_sku', 'value' => $sku];

                                $resAlt = $this->client->postNoRetry('products', $payloadAlt, 20, 5);
                                if ($resAlt->successful()) {
                                    $res = $resAlt;
                                    $wasUpdate = false;
                                }
                            } catch (\Throwable $e) {
                            }
                        }

                        // If our conflict-handling managed to recover (link or alt-create),
                        // we may now have a successful response in $res. Only error out if still failing.
                        if (!$res || !$res->successful()) {
                            $errors++;
                            if ($emit) {
                                $emit([
                                    'stage' => 'woo_error',
                                    'last_http_error_type' => $res ? 'http' : 'null_response',
                                    'last_http_error_message' => $res
                                        ? ('HTTP '.$res->status().': '.substr((string) $res->body(), 0, 800))
                                        : 'Null HTTP response from WooCommerce',
                                ]);
                            }
                            $this->log('products.push', 'error', 'Woo request failed', [
                                'status' => $res ? $res->status() : null,
                                'body'   => $res ? $res->body() : 'null response',
                                'product_id' => $product->id,
                                'sku' => $sku,
                            ]);

                            // Fail fast in production if Woo is down/unstable (prevents "hang for hours").
                            $consecutiveWooFailures++;
                            if ($consecutiveWooFailures >= $maxConsecutiveWooFailures) {
                                $this->log('products.push', 'error', 'Aborting sync: too many consecutive Woo failures', [
                                    'consecutive_failures' => $consecutiveWooFailures,
                                    'max_consecutive_failures' => $maxConsecutiveWooFailures,
                                    'last_status' => $res ? $res->status() : null,
                                    'last_body' => $res ? $res->body() : null,
                                ]);
                                throw new \RuntimeException(self::WOO_SYNC_ABORT_EXCEPTION);
                            }
                            continue;
                        }
                    }

                    $body = $res->json();
                    $remoteId = is_array($body) ? (int) ($body['id'] ?? 0) : 0;

                    // Strong verification before storing mapping:
                    // Prefer meta/SKU strict validation (fast by id).
                    $finalWooId = 0;
                    if ($remoteId > 0 && $this->validateWooProductIdStrict($remoteId, $sku, (int) $product->id)) {
                        $finalWooId = $remoteId;
                    } else {
                        // If response id missing or untrusted, try cached maps again, then fallback resolve once.
                        $finalWooId = $remoteByExternalId[(string) $product->id] ?? ($remoteBySku[mb_strtolower($sku)] ?? 0);
                        $finalWooId = (int) $finalWooId;

                        if ($finalWooId > 0 && !$this->validateWooProductIdStrict($finalWooId, $sku, (int) $product->id)) {
                            $finalWooId = 0;
                        }

                        if ($finalWooId <= 0) {
                            // last resort: strict resolve (short)
                            $finalWooId = $this->resolveWooIdStrict($product, $remoteBySku, $remoteByExternalId, $emit);
                            if ($finalWooId <= 0 || $finalWooId === -1) {
                                $finalWooId = 0;
                            }
                        }
                    }

                    if ($finalWooId <= 0) {
                        $errors++;
                        $this->log('products.push', 'error', 'Refusing to store woocommerce_id: strict validation failed after write', [
                            'product_id' => $product->id,
                            'sku' => $sku,
                            'candidate_woocommerce_id' => $remoteId,
                            'candidate_identity' => $remoteId > 0 ? $this->wooIdentityForLog($remoteId) : null,
                        ]);
                        continue;
                    }

                    // Persist mapping
                    if (empty($product->woocommerce_id) || (int) $product->woocommerce_id !== (int) $finalWooId) {
                        $product->woocommerce_id = (int) $finalWooId;
                        $product->save();
                    }

                    // Update caches for next products
                    $remoteBySku[mb_strtolower($sku)] = (int) $finalWooId;
                    $remoteByExternalId[(string) $product->id] = (int) $finalWooId;

                    if ($wasUpdate) {
                        $updated++;
                    } else {
                        $created++;
                    }

                    // Reset consecutive failure counter on a successful write.
                    $consecutiveWooFailures = 0;

                    // Sync variations (use FINAL woo id, not response id)
                    if ($isVariant) {
                        $emit(['stage' => 'variants']);
                        $this->syncVariantsForProduct($product, (int) $finalWooId, $emit, $shouldCancel);
                    }
                } catch (Throwable $e) {
                    if ($e instanceof \RuntimeException && $e->getMessage() === self::WOO_SYNC_ABORT_EXCEPTION) {
                        throw $e;
                    }
                    $errors++;
                    $this->log('products.push', 'error', $e->getMessage(), ['product_id' => $product->id]);
                } finally {
                    $emit([
                        'stage' => 'done',
                        'last_product_id' => (int) $product->id,
                        'last_sku' => (string) ($product->code ?? ''),
                    ]);
                }
            }

            if ($progress) {
                $progress([
                    'processed' => $processed,
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                ]);
            }
        });

        $this->log('products.push', 'info', 'Products push completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    // ---------------------------------------------------------------------
    // Variations (for variable products)
    // ---------------------------------------------------------------------
    private function syncVariantsForProduct(Product $product, int $wooProductId, ?callable $emit = null, ?callable $shouldCancel = null): void
    {
        try {
            if ($shouldCancel) {
                $shouldCancel();
            }
            if ($emit) {
                $emit(['stage' => 'variants_prepare']);
            }

            // Refresh attributes on parent (fail-fast)
            $variantNames = ProductVariant::where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->pluck('name')
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->unique()
                ->values()
                ->all();

            if (!empty($variantNames)) {
                if ($shouldCancel) {
                    $shouldCancel();
                }
                $res = $this->client->putNoRetry('products/'.$wooProductId, [
                    'type' => 'variable',
                    'attributes' => [[
                        'name' => 'Variant',
                        'visible' => true,
                        'variation' => true,
                        'options' => array_values($variantNames),
                    ]],
                ], 20, 5);

                if (!$res->successful()) {
                    throw new \RuntimeException('Failed updating parent attributes (status '.$res->status().').');
                }
            }

            // Load local variants
            $variants = ProductVariant::where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->get();

            $localVariants = [];
            foreach ($variants as $var) {
                if ($shouldCancel) {
                    $shouldCancel();
                }
                $variantName = trim((string) ($var->name ?? ''));
                $sku = trim((string) ($var->code ?? ''));
                if ($sku === '') {
                    $sku = $product->code ? ($product->code.'-'.($variantName !== '' ? $variantName : $var->id)) : ('VAR-'.$var->id);
                }
                $option = $variantName !== '' ? $variantName : ('Variant '.$var->id);

                $localVariants[] = [
                    'local_id' => (int) $var->id,
                    'sku' => $sku,
                    'option' => $option,
                    'payload' => [
                        'sku' => $sku,
                        'regular_price' => number_format((float) ($var->price ?? 0), 2, '.', ''),
                        'attributes' => [[
                            'name' => 'Variant',
                            'option' => $option,
                        ]],
                        'meta_data' => [[
                            'key' => self::WOO_META_EXTERNAL_VARIANT_ID,
                            'value' => (string) $var->id,
                        ]],
                    ],
                ];
            }

            $wooCache = null;

            $verify = function (bool $forceRefresh) use ($wooProductId, $localVariants, $emit, &$wooCache) {
                return $this->verifyWooVariants($wooProductId, $localVariants, $emit, $wooCache, $forceRefresh);
            };

            $result = $verify(true);

            foreach ($result['syncedMap'] as $localId => $wooVarId) {
                try {
                    ProductVariant::whereKey((int) $localId)->update(['woocommerce_variation_id' => (int) $wooVarId]);
                } catch (\Throwable $e) {
                }
            }

            // IMPORTANT: update existing Woo variations to match Stocky (price/sku/attributes/meta).
            // Previous behavior only created missing variations, leaving already-existing ones unchanged.
            try {
                $batchTimeoutMs = (int) env('WOO_TIMEOUT_MS_BATCH', 30000);
                $batchTimeoutSec = max(1, (int) ceil($batchTimeoutMs / 1000));

                $updates = [];
                foreach ($localVariants as $lv) {
                    $lid = (int) ($lv['local_id'] ?? 0);
                    if ($lid <= 0) continue;
                    $wid = (int) ($result['syncedMap'][$lid] ?? 0);
                    if ($wid <= 0) continue;

                    $payload = is_array($lv['payload'] ?? null) ? $lv['payload'] : [];
                    if (empty($payload)) continue;
                    $payload['id'] = $wid;
                    $updates[] = $payload;
                }

                if (!empty($updates)) {
                    $chunks = array_chunk($updates, 50);
                    $uAttempt = 0;
                    foreach ($chunks as $chunk) {
                        if ($shouldCancel) {
                            $shouldCancel();
                        }
                        $uAttempt++;

                        if ($emit) {
                            $emit([
                                'stage' => 'variant_batch_update_request',
                                'attempt' => $uAttempt,
                                'batch_update' => count($chunk),
                                'verified_count' => count($localVariants) - count($result['missingVariants'] ?? []),
                                'missing_count' => count($result['missingVariants'] ?? []),
                            ]);
                        }

                        $resU = null;
                        $lastErrU = null;
                        try {
                            $resU = $this->client->postNoRetry('products/'.$wooProductId.'/variations/batch', [
                                'update' => array_values($chunk),
                            ], $batchTimeoutSec, 5);
                            if (!$resU->successful()) {
                                $lastErrU = 'HTTP '.$resU->status().': '.$resU->body();
                            }
                        } catch (\Throwable $e) {
                            $lastErrU = $e->getMessage();
                        }

                        if ($lastErrU !== null) {
                            $this->log('variants.push', 'warning', 'Variant batch update failed; continuing with create/verify', [
                                'product_id' => $product->id,
                                'woocommerce_id' => $wooProductId,
                                'batch_update' => count($chunk),
                                'payload_bytes' => strlen(json_encode(['update' => $chunk])),
                                'error' => $lastErrU,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort: do not block sync on update failures
            }

            // Re-verify after attempting updates (ensures meta mapping is refreshed).
            try {
                $result = $verify(true);
                foreach ($result['syncedMap'] as $localId => $wooVarId) {
                    try {
                        ProductVariant::whereKey((int) $localId)->update(['woocommerce_variation_id' => (int) $wooVarId]);
                    } catch (\Throwable $e) {
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $missing = $result['missingVariants'];
            $maxAttempts = 5;
            $attempt = 0;

            $batchTimeoutMs = (int) env('WOO_TIMEOUT_MS_BATCH', 30000);
            $batchTimeoutSec = max(1, (int) ceil($batchTimeoutMs / 1000));

            while (!empty($missing) && $attempt < $maxAttempts) {
                if ($shouldCancel) {
                    $shouldCancel();
                }
                $attempt++;

                $payload = ['create' => []];
                foreach ($missing as $mv) {
                    $payload['create'][] = $mv['payload'];
                }

                if ($emit) {
                    $emit([
                        'stage' => 'variant_batch_request',
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'batch_create' => count($payload['create']),
                        'verified_count' => count($localVariants) - count($missing),
                        'missing_count' => count($missing),
                    ]);
                }

                $lastError = null;
                $res = null;

                try {
                    if ($shouldCancel) {
                        $shouldCancel();
                    }
                    $res = $this->client->postNoRetry('products/'.$wooProductId.'/variations/batch', $payload, $batchTimeoutSec, 5);
                    if (!$res->successful()) {
                        $lastError = 'HTTP '.$res->status().': '.$res->body();
                    }
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }

                if ($lastError !== null) {
                    $this->log('variants.push', 'warning', 'Variant batch attempt failed; verifying', [
                        'product_id' => $product->id,
                        'woocommerce_id' => $wooProductId,
                        'attempt' => $attempt,
                        'batch_size' => count($payload['create']),
                        'payload_bytes' => strlen(json_encode($payload)),
                        'error' => $lastError,
                    ]);
                }

                $vr = $verify(true);

                foreach ($vr['syncedMap'] as $localId => $wooVarId) {
                    try {
                        ProductVariant::whereKey((int) $localId)->update(['woocommerce_variation_id' => (int) $wooVarId]);
                    } catch (\Throwable $e) {
                    }
                }

                $missing = $vr['missingVariants'];

                if ($emit) {
                    $emit([
                        'stage' => empty($missing) ? 'variant_verify_ok' : 'variant_verify_failed',
                        'attempt' => $attempt,
                        'verified_count' => count($localVariants) - count($missing),
                        'missing_count' => count($missing),
                        'status' => $res ? $res->status() : null,
                    ]);
                }

                if (!empty($missing)) {
                    if ($shouldCancel) {
                        $shouldCancel();
                    }
                    sleep(min(5, $attempt));
                }
            }

            if (!empty($missing)) {
                $missingSkus = [];
                foreach ($missing as $mv) {
                    $s = trim((string) ($mv['sku'] ?? ''));
                    if ($s !== '') {
                        $missingSkus[] = $s;
                    }
                }

                $this->log('variants.push', 'error', 'Variant sync incomplete after retries', [
                    'product_id' => $product->id,
                    'woocommerce_id' => $wooProductId,
                    'missing_count' => count($missing),
                    'missing_skus' => array_slice($missingSkus, 0, 15),
                ]);

                throw new \RuntimeException(
                    'Variant sync incomplete after '.$maxAttempts.' attempts. Missing variants: '.count($missing)
                    .(empty($missingSkus) ? '' : (' (e.g. '.implode(', ', array_slice($missingSkus, 0, 5)).')'))
                );
            }

            // -----------------------------------------------------------------
            // Deletions: if variants were removed locally, delete matching Woo variations.
            // We only delete Woo variations that we can confidently identify as Stocky-managed:
            // - those having meta external_variant_id, whose value is NOT in current local variant ids.
            // This keeps both systems in sync without deleting manually-created Woo variations.
            // -----------------------------------------------------------------
            try {
                if ($shouldCancel) {
                    $shouldCancel();
                }

                $localIdSet = [];
                $localSkuSet = [];
                $localOptSet = [];
                foreach ($localVariants as $lv) {
                    $lid = (int) ($lv['local_id'] ?? 0);
                    if ($lid > 0) $localIdSet[(string) $lid] = true;
                    $s = trim((string) ($lv['sku'] ?? ''));
                    if ($s !== '') $localSkuSet[mb_strtolower($s)] = true;
                    $o = trim((string) ($lv['option'] ?? ''));
                    if ($o !== '') $localOptSet[mb_strtolower($o)] = true;
                }

                $wooVars = $this->listWooVariations($wooProductId, $emit, $shouldCancel);
                $toDelete = [];
                $staleLocalIds = [];

                foreach ($wooVars as $wv) {
                    $vid = (int) ($wv['id'] ?? 0);
                    if ($vid <= 0) continue;

                    $ext = trim((string) ($wv['external_variant_id'] ?? ''));
                    if ($ext !== '') {
                        // Meta-based deletion (strong)
                        if (!isset($localIdSet[$ext])) {
                            $toDelete[] = $vid;
                            $staleLocalIds[$ext] = true;
                        }
                        continue;
                    }

                    // Weak fallback: if Woo variation has no meta, do not delete it automatically.
                    // (avoids removing manually-created Woo variations)
                }

                $toDelete = array_values(array_unique(array_map('intval', $toDelete)));
                if (!empty($toDelete)) {
                    $batchTimeoutMs = (int) env('WOO_TIMEOUT_MS_BATCH', 30000);
                    $batchTimeoutSec = max(1, (int) ceil($batchTimeoutMs / 1000));

                    if ($emit) {
                        $emit([
                            'stage' => 'variant_delete_stale',
                            'delete_count' => count($toDelete),
                        ]);
                    }

                    $resD = null;
                    $batchOk = false;
                    try {
                        // Try batch delete first; include force=true for consistency.
                        $resD = $this->client->postNoRetry('products/'.$wooProductId.'/variations/batch?force=true', [
                            'delete' => $toDelete,
                        ], $batchTimeoutSec, 5);
                        $batchOk = $resD->successful();
                    } catch (\Throwable $e) {
                        $batchOk = false;
                    }

                    if (!$batchOk) {
                        // Fallback to individual deletes
                        foreach ($toDelete as $vid) {
                            if ($shouldCancel) {
                                $shouldCancel();
                            }
                            try {
                                $this->client->deleteNoRetry('products/'.$wooProductId.'/variations/'.$vid, [
                                    'force' => 'true',
                                ], 20, 5);
                            } catch (\Throwable $e) {
                            }
                        }
                    }

                    // Clear local variation mapping for stale local ids (best-effort, includes soft-deleted variants)
                    try {
                        $ids = [];
                        foreach (array_keys($staleLocalIds) as $k) {
                            $id = (int) $k;
                            if ($id > 0) $ids[] = $id;
                        }
                        if (!empty($ids)) {
                            ProductVariant::whereIn('id', $ids)->update(['woocommerce_variation_id' => null]);
                        }
                    } catch (\Throwable $e) {
                    }

                    // Re-verify after deletions (so the next runs are clean)
                    try {
                        $verify(true);
                    } catch (\Throwable $e) {
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort; do not fail product sync on deletion issues.
            }
        } catch (\Throwable $e) {
            $this->log('variants.push', 'error', 'Variant sync failed for product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify which local variants exist in WooCommerce.
     * Returns:
     * - syncedMap: [local_variant_id => woo_variation_id]
     * - missingVariants: array of local variant entries (same shape as $localVariants)
     */
    private function verifyWooVariants(int $wooProductId, array $localVariants, ?callable $emit = null, ?array &$cache = null, bool $forceRefresh = false): array
    {
        $cache = $cache ?? [];

        if (!$forceRefresh && isset($cache['bySku'], $cache['byExternal'], $cache['byOption'])) {
            $bySku = $cache['bySku'];
            $byExternal = $cache['byExternal'];
            $byOption = $cache['byOption'];
        } else {
            $bySku = [];
            $byExternal = [];
            $byOption = [];

            $page = 1;
            $per = 100;

            while (true) {
                $maxAttempts = 5;
                $attempt = 0;
                $res = null;
                $lastErr = null;

                while ($attempt < $maxAttempts) {
                    $attempt++;

                    if ($emit) {
                        $emit([
                            'stage' => 'variants_fetch',
                            'variants_page' => $page,
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'last_endpoint' => 'GET /products/'.$wooProductId.'/variations?page='.$page,
                            'substep' => 'http_start',
                            'substep_at' => now()->toDateTimeString(),
                            'worker_heartbeat_at' => now()->toDateTimeString(),
                        ]);
                    }

                    try {
                        $t0 = microtime(true);
                        $res = $this->client->getNoRetry(
                            'products/'.$wooProductId.'/variations',
                            ['page' => $page, 'per_page' => $per, 'context' => 'edit'],
                            20,
                            5
                        );
                        if (!$res->successful()) {
                            // Fallback: some stores block context=edit on GET.
                            $res2 = $this->client->getNoRetry(
                                'products/'.$wooProductId.'/variations',
                                ['page' => $page, 'per_page' => $per, '_fields' => 'id,sku,attributes,meta_data'],
                                20,
                                5
                            );
                            if ($res2->successful()) {
                                $res = $res2;
                            }
                        }

                        if ($emit) {
                            $emit([
                                'substep' => 'http_end',
                                'substep_at' => now()->toDateTimeString(),
                                'last_http_duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                                'status' => $res->status(),
                                'worker_heartbeat_at' => now()->toDateTimeString(),
                            ]);
                        }

                        if ($res->successful()) {
                            $lastErr = null;
                            break;
                        }

                        $lastErr = 'HTTP '.$res->status().': '.$res->body();
                    } catch (\Throwable $e) {
                        $res = null;
                        $lastErr = $e->getMessage();

                        if ($emit) {
                            $emit([
                                'substep' => 'http_end',
                                'substep_at' => now()->toDateTimeString(),
                                'last_http_error_type' => 'exception',
                                'last_http_error_message' => $lastErr,
                                'worker_heartbeat_at' => now()->toDateTimeString(),
                            ]);
                        }
                    }

                    sleep(min(5, $attempt));
                }

                if (!$res || !$res->successful()) {
                    throw new \RuntimeException('Failed verifying variations page '.$page.' (attempts '.$maxAttempts.'). Last error: '.($lastErr ?? 'n/a'));
                }

                $list = $res->json();
                if (empty($list) || !is_array($list)) {
                    break;
                }

                foreach ($list as $v) {
                    $vid = (int) ($v['id'] ?? 0);
                    if ($vid <= 0) {
                        continue;
                    }

                    $sku = trim((string) ($v['sku'] ?? ''));
                    if ($sku !== '') {
                        $bySku[mb_strtolower($sku)] = $vid;
                    }

                    // Option fallback (Woo sanitizes SKU sometimes)
                    $attrs = $v['attributes'] ?? [];
                    if (is_array($attrs)) {
                        foreach ($attrs as $a) {
                            if (!is_array($a)) {
                                continue;
                            }
                            $opt = trim((string) ($a['option'] ?? ''));
                            if ($opt !== '') {
                                $byOption[mb_strtolower($opt)] = $vid;
                            }
                        }
                    }

                    $meta = $v['meta_data'] ?? [];
                    if (is_array($meta)) {
                        foreach ($meta as $m) {
                            if (!is_array($m)) {
                                continue;
                            }
                            if (($m['key'] ?? null) === self::WOO_META_EXTERNAL_VARIANT_ID) {
                                $val = (string) ($m['value'] ?? '');
                                if ($val !== '') {
                                    $byExternal[$val] = $vid;
                                }
                            }
                        }
                    }
                }

                if (count($list) < $per || $page >= 50) {
                    break;
                }

                $page++;
            }

            $cache['bySku'] = $bySku;
            $cache['byExternal'] = $byExternal;
            $cache['byOption'] = $byOption;
        }

        $syncedMap = [];
        $missing = [];

        foreach ($localVariants as $lv) {
            $localId = (int) ($lv['local_id'] ?? 0);
            $sku = trim((string) ($lv['sku'] ?? ''));
            $opt = trim((string) ($lv['option'] ?? ''));

            $wooId = null;

            if ($sku !== '') {
                $wooId = $bySku[mb_strtolower($sku)] ?? null;
            }
            if (!$wooId) {
                $wooId = $byExternal[(string) $localId] ?? null;
            }
            if (!$wooId && $opt !== '') {
                $wooId = $byOption[mb_strtolower($opt)] ?? null;
            }

            if ($wooId) {
                $syncedMap[$localId] = (int) $wooId;
            } else {
                $missing[] = $lv;
            }
        }

        return ['syncedMap' => $syncedMap, 'missingVariants' => $missing];
    }

    // ---------------------------------------------------------------------
    // Stock sync (POS -> Woo)
    // ---------------------------------------------------------------------
    public function syncStock(?callable $progress = null): array
    {
        $updated = 0;
        $errors = 0;
        $processed = 0;

        Product::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->orderBy('id')
            ->chunk(200, function ($products) use (&$updated, &$errors, &$processed, $progress) {
                foreach ($products as $product) {
                    try {
                        // Skip services
                        if (($product->type ?? '') === 'is_service') {
                            $processed++;
                            if ($progress) {
                                $progress(['processed' => $processed, 'updated' => $updated, 'errors' => $errors]);
                            }
                            continue;
                        }

                        $isVariant = (int) ($product->is_variant ?? 0) === 1 || ($product->type ?? '') === 'is_variant';

                        if ($isVariant) {
                            $anyInStock = false;

                            // Fetch remote variations map (fail-fast)
                            $existingMapBySku = [];
                            $existingMapByOption = [];

                            $page = 1;
                            $per = 100;
                            while (true) {
                                $vres = $this->client->getNoRetry(
                                    'products/'.(int) $product->woocommerce_id.'/variations',
                                    ['page' => $page, 'per_page' => $per],
                                    20,
                                    5
                                );

                                if (!$vres->successful()) {
                                    break;
                                }

                                $list = $vres->json();
                                if (empty($list) || !is_array($list)) {
                                    break;
                                }

                                foreach ($list as $v) {
                                    $vid = (int) ($v['id'] ?? 0);
                                    if ($vid <= 0) {
                                        continue;
                                    }

                                    $sku = (string) ($v['sku'] ?? '');
                                    if ($sku !== '') {
                                        $existingMapBySku[$sku] = $vid;
                                    }

                                    $attrs = $v['attributes'] ?? [];
                                    if (is_array($attrs) && isset($attrs[0]['option'])) {
                                        $opt = (string) $attrs[0]['option'];
                                        if ($opt !== '') {
                                            $existingMapByOption[$opt] = $vid;
                                        }
                                    }
                                }

                                if (count($list) < $per) {
                                    break;
                                }
                                $page++;
                            }

                            $variants = ProductVariant::where('product_id', $product->id)
                                ->whereNull('deleted_at')
                                ->get();

                            foreach ($variants as $var) {
                                $variantName = trim((string) ($var->name ?? ''));
                                $sku = trim((string) ($var->code ?? ''));
                                if ($sku === '') {
                                    $sku = $product->code ? ($product->code.'-'.($variantName !== '' ? $variantName : $var->id)) : ('VAR-'.$var->id);
                                }

                                $qty = $this->computeVariantStockQuantity((int) $product->id, (int) $var->id);
                                $status = $qty > 0 ? 'instock' : 'outofstock';
                                if ($qty > 0) {
                                    $anyInStock = true;
                                }

                                $payload = [
                                    'manage_stock' => true,
                                    'stock_quantity' => $qty,
                                    'stock_status' => $status,
                                ];

                                $media = $this->resolveOrUploadWpMedia($product);
                                if ($media && isset($media['id'])) {
                                    $payload['image'] = ['id' => (int) $media['id']];
                                } else {
                                    $src = $this->productMainImageSrc($product);
                                    if ($src) {
                                        $payload['image'] = ['src' => $src];
                                    }
                                }

                                $targetId = $existingMapBySku[$sku] ?? ($existingMapByOption[$variantName] ?? null);

                                if ($targetId) {
                                    $res = $this->client->putNoRetry(
                                        'products/'.(int) $product->woocommerce_id.'/variations/'.$targetId,
                                        $payload,
                                        20,
                                        5
                                    );
                                    $res->successful() ? $updated++ : $errors++;
                                } else {
                                    $createPayload = $payload + [
                                        'sku' => $sku,
                                        'attributes' => [[
                                            'name' => 'Variant',
                                            'option' => $variantName !== '' ? $variantName : ('Variant '.$var->id),
                                        ]],
                                    ];
                                    $res = $this->client->postNoRetry(
                                        'products/'.(int) $product->woocommerce_id.'/variations',
                                        $createPayload,
                                        20,
                                        5
                                    );
                                    $res->successful() ? $updated++ : $errors++;
                                }
                            }

                            // Parent stock status only
                            try {
                                $this->client->putNoRetry(
                                    'products/'.(int) $product->woocommerce_id,
                                    ['manage_stock' => false, 'stock_status' => $anyInStock ? 'instock' : 'outofstock'],
                                    20,
                                    5
                                );
                            } catch (Throwable $e) {
                            }

                            $processed++;
                            if ($progress) {
                                $progress(['processed' => $processed, 'updated' => $updated, 'errors' => $errors]);
                            }
                            continue;
                        }

                        // Combo
                        if (($product->type ?? '') === 'is_combo') {
                            $qty = $this->computeComboStockQuantity((int) $product->id);
                            $status = $qty > 0 ? 'instock' : 'outofstock';

                            $res = $this->client->putNoRetry(
                                'products/'.(int) $product->woocommerce_id,
                                ['manage_stock' => true, 'stock_quantity' => $qty, 'stock_status' => $status],
                                20,
                                5
                            );

                            $res->successful() ? $updated++ : $errors++;

                            $processed++;
                            if ($progress) {
                                $progress(['processed' => $processed, 'updated' => $updated, 'errors' => $errors]);
                            }
                            continue;
                        }

                        // Simple
                        $qty = $this->computeStockQuantity((int) $product->id);
                        $status = $qty > 0 ? 'instock' : 'outofstock';

                        $res = $this->client->putNoRetry(
                            'products/'.(int) $product->woocommerce_id,
                            ['manage_stock' => true, 'stock_quantity' => $qty, 'stock_status' => $status],
                            20,
                            5
                        );

                        $res->successful() ? $updated++ : $errors++;

                        $processed++;
                        if ($progress) {
                            $progress(['processed' => $processed, 'updated' => $updated, 'errors' => $errors]);
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        $processed++;
                        if ($progress) {
                            $progress(['processed' => $processed, 'updated' => $updated, 'errors' => $errors]);
                        }
                    }
                }
            });

        $this->log('stock.sync', 'info', 'Stock sync completed', ['updated' => $updated, 'errors' => $errors]);

        return ['updated' => $updated, 'errors' => $errors];
    }

    private function computeStockQuantity(int $productId): int
    {
        $sum = (float) product_warehouse::where('product_id', $productId)
            ->whereNull('deleted_at')
            ->sum('qte');

        $qty = (int) round($sum);
        return $qty < 0 ? 0 : $qty;
    }

    private function computeVariantStockQuantity(int $productId, int $variantId): int
    {
        $sum = (float) product_warehouse::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereNull('deleted_at')
            ->sum('qte');

        $qty = (int) round($sum);
        return $qty < 0 ? 0 : $qty;
    }

    private function computeComboStockQuantity(int $productId): int
    {
        $components = DB::table('combined_products')
            ->where('product_id', $productId)
            ->get(['combined_product_id', 'quantity']);

        if ($components->isEmpty()) {
            return $this->computeStockQuantity($productId);
        }

        $min = null;
        foreach ($components as $c) {
            $componentStock = $this->computeStockQuantity((int) $c->combined_product_id);
            $required = max(1.0, (float) $c->quantity);
            $possible = (int) floor($componentStock / $required);
            $min = is_null($min) ? $possible : min($min, $possible);
        }

        return max(0, (int) ($min ?? 0));
    }

    // ---------------------------------------------------------------------
    // Media (best-effort)
    // ---------------------------------------------------------------------
    private function wooProductHasAnyImage(int $wooId): ?bool
    {
        if ($wooId <= 0) {
            return null;
        }

        try {
            $res = $this->client->getNoRetry('products/'.$wooId, [
                'status' => 'any',
                'context' => 'edit',
                '_fields' => 'id,images',
            ], 10, 5);

            if (!$res->successful()) {
                return null;
            }

            $data = $res->json();
            if (!is_array($data)) {
                return null;
            }

            $images = $data['images'] ?? null;
            if (!is_array($images)) {
                // If field missing/invalid, treat as “no images”
                return false;
            }

            return count($images) > 0;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildWooProductImagesPayload(Product $product, ?callable $shouldCancel = null): ?array
    {
        try {
            if ($shouldCancel) {
                $shouldCancel();
            }

            $media = $this->resolveOrUploadWpMedia($product, $shouldCancel);
            if ($media && isset($media['id'])) {
                return [['id' => (int) $media['id']]];
            }

            $src = $this->productMainImageSrc($product);
            if ($src) {
                return [['src' => $src]];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function productMainImageSrc(Product $product): ?string
    {
        try {
            $imageName = (string) ($product->image ?? '');
            if ($imageName === '' || strtolower($imageName) === 'no-image.png') {
                return null;
            }

            $public = public_path('images/products/'.$imageName);
            if (!is_file($public)) {
                return null;
            }

            return asset('images/products/'.$imageName);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve or upload the product's main image to WP media using Application Passwords.
     * Returns ['id' => int, 'src' => string] on success, or null if not available/failed.
     */
    private function resolveOrUploadWpMedia(Product $product, ?callable $shouldCancel = null): ?array
    {
        try {
            if ($shouldCancel) {
                $shouldCancel();
            }
            $settings = WooCommerceSetting::first();
            if (!$settings) {
                return null;
            }

            $username = (string) ($settings->wp_username ?? '');
            $appPass  = (string) ($settings->wp_app_password ?? '');
            $baseUrl  = rtrim((string) ($settings->store_url ?? ''), '/');

            if ($username === '' || $appPass === '' || $baseUrl === '') {
                return null;
            }

            $imageName = (string) ($product->image ?? '');
            if ($imageName === '' || strtolower($imageName) === 'no-image.png') {
                return null;
            }

            $abs = public_path('images/products/'.$imageName);
            if (!is_file($abs)) {
                return null;
            }

            // Skip huge files
            try {
                $size = @filesize($abs);
                if (is_int($size) && $size > (5 * 1024 * 1024)) {
                    return null;
                }
            } catch (\Throwable $e) {
            }

            $searchTimeout = (int) env('WOO_WP_MEDIA_SEARCH_TIMEOUT', 10);
            // Shared hosting + large images can be slow: use a higher default, but keep a hard cap.
            $uploadTimeout = (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 60);
            $searchTimeout = max(1, min(60, $searchTimeout));
            $uploadTimeout = max(1, min(300, $uploadTimeout));

            $filenameBase = pathinfo($imageName, PATHINFO_FILENAME);

            // 1) Search existing media (avoid re-upload duplicates)
            try {
                if ($shouldCancel) {
                    $shouldCancel();
                }
                $matches = function ($m) use ($imageName, $filenameBase): bool {
                    if (!is_array($m)) {
                        return false;
                    }
                    $src = (string) ($m['source_url'] ?? '');
                    $file = '';
                    if (isset($m['media_details']) && is_array($m['media_details'])) {
                        $file = (string) ($m['media_details']['file'] ?? '');
                    }

                    $srcPath = $src !== '' ? (string) parse_url($src, PHP_URL_PATH) : '';
                    $srcBase = $srcPath !== '' ? basename($srcPath) : '';
                    $fileBase = $file !== '' ? basename($file) : '';

                    $want = strtolower($imageName);
                    $wantDecoded = strtolower(urldecode($imageName));

                    foreach ([$srcBase, urldecode($srcBase), $fileBase, urldecode($fileBase)] as $candidate) {
                        $c = strtolower((string) $candidate);
                        if ($c !== '' && ($c === $want || $c === $wantDecoded)) {
                            return true;
                        }
                    }

                    // fallback: contains match on full url/file
                    if ($src !== '' && (stripos($src, $imageName) !== false || stripos($src, $filenameBase) !== false)) {
                        return true;
                    }
                    if ($file !== '' && (stripos($file, $imageName) !== false || stripos($file, $filenameBase) !== false)) {
                        return true;
                    }

                    return false;
                };

                $trySearch = function (string $term) use ($baseUrl, $username, $appPass, $searchTimeout, $matches): ?array {
                    $mediaList = Http::timeout($searchTimeout)
                        ->connectTimeout(5)
                        ->retry((int) env('WOO_WP_MEDIA_RETRIES', 1), (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 250))
                        ->withBasicAuth($username, $appPass)
                        ->get($baseUrl.'/wp-json/wp/v2/media', [
                            'search' => $term,
                            'per_page' => 100,
                            '_fields' => 'id,source_url,media_details',
                        ]);

                    if (!$mediaList->successful()) {
                        return null;
                    }
                    $items = $mediaList->json();
                    if (!is_array($items)) {
                        return null;
                    }
                    foreach ($items as $m) {
                        if (!$matches($m)) {
                            continue;
                        }
                        $id = (int) ($m['id'] ?? 0);
                        $src = (string) ($m['source_url'] ?? '');
                        if ($id > 0) {
                            return ['id' => $id, 'src' => $src];
                        }
                    }
                    return null;
                };

                $trySlug = function (string $slug) use ($baseUrl, $username, $appPass, $searchTimeout, $matches): ?array {
                    $mediaList = Http::timeout($searchTimeout)
                        ->connectTimeout(5)
                        ->retry((int) env('WOO_WP_MEDIA_RETRIES', 1), (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 250))
                        ->withBasicAuth($username, $appPass)
                        ->get($baseUrl.'/wp-json/wp/v2/media', [
                            'slug' => $slug,
                            'per_page' => 100,
                            '_fields' => 'id,source_url,media_details',
                        ]);

                    if (!$mediaList->successful()) {
                        return null;
                    }
                    $items = $mediaList->json();
                    if (!is_array($items)) {
                        return null;
                    }
                    foreach ($items as $m) {
                        if (!$matches($m)) {
                            continue;
                        }
                        $id = (int) ($m['id'] ?? 0);
                        $src = (string) ($m['source_url'] ?? '');
                        if ($id > 0) {
                            return ['id' => $id, 'src' => $src];
                        }
                    }
                    return null;
                };

                // Try exact filename first, then base name, then slug(base name)
                $found = $trySearch($imageName);
                if (!$found && $filenameBase !== '' && $filenameBase !== $imageName) {
                    $found = $trySearch($filenameBase);
                }
                if (!$found && $filenameBase !== '') {
                    // WP stores media as attachment posts; slug is often the filename base
                    $found = $trySlug($filenameBase);
                }
                if ($found) {
                    return $found;
                }
            } catch (\Throwable $e) {
                try {
                    WooCommerceLog::create([
                        'action' => 'media.resolve',
                        'level' => 'error',
                        'message' => 'WP media search exception',
                        'context' => [
                            'product_id' => $product->id,
                            'image' => $imageName,
                            'error' => $e->getMessage(),
                        ],
                    ]);
                } catch (\Throwable $e2) {
                }
                return null;
            }

            // 2) Upload
            try {
                if ($shouldCancel) {
                    $shouldCancel();
                }
                $upload = Http::timeout($uploadTimeout)
                    ->connectTimeout(5)
                    ->retry((int) env('WOO_WP_MEDIA_RETRIES', 1), (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 250))
                    ->withBasicAuth($username, $appPass)
                    ->attach('file', fopen($abs, 'r'), $imageName)
                    ->withHeaders([
                        'Content-Disposition' => 'attachment; filename="'.$imageName.'"',
                    ])
                    ->post($baseUrl.'/wp-json/wp/v2/media');

                if ($upload->successful() || $upload->status() === 201) {
                    $body = $upload->json();
                    $id  = (int) ($body['id'] ?? 0);
                    $src = (string) ($body['source_url'] ?? '');
                    if ($id > 0) {
                        return ['id' => $id, 'src' => $src];
                    }
                }

                try {
                    WooCommerceLog::create([
                        'action' => 'media.upload',
                        'level' => 'error',
                        'message' => 'WP media upload failed',
                        'context' => [
                            'product_id' => $product->id,
                            'image' => $imageName,
                            'status' => $upload->status(),
                            'body' => $upload->body(),
                        ],
                    ]);
                } catch (\Throwable $e2) {
                }
            } catch (\Throwable $e) {
                try {
                    WooCommerceLog::create([
                        'action' => 'media.upload',
                        'level' => 'error',
                        'message' => 'WP media upload exception',
                        'context' => [
                            'product_id' => $product->id,
                            'image' => $imageName,
                            'error' => $e->getMessage(),
                        ],
                    ]);
                } catch (\Throwable $e2) {
                }
                return null;
            }
        } catch (\Throwable $e) {
            try {
                WooCommerceLog::create([
                    'action' => 'media.upload',
                    'level' => 'error',
                    'message' => 'WP media handler exception',
                    'context' => [
                        'product_id' => $product->id ?? null,
                        'error' => $e->getMessage(),
                    ],
                ]);
            } catch (\Throwable $e2) {
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Orders (Woo -> POS)
    // ---------------------------------------------------------------------
    public function syncOrders(?callable $progress = null): array
    {
        $imported = 0;
        $errors = 0;
        $page = 1;
        $perPage = 25;

        while (true) {
            $res = $this->client->getNoRetry('orders', [
                'page' => $page,
                'per_page' => $perPage,
                'status' => 'any',
            ], 20, 5);

            if (!$res->successful()) {
                break;
            }

            $orders = $res->json();
            if (empty($orders) || !is_array($orders)) {
                break;
            }

            foreach ($orders as $o) {
                try {
                    DB::transaction(function () use ($o) {
                        $sale = new Sale;
                        $sale->date = now()->toDateString();
                        $sale->time = now()->format('H:i');
                        $sale->Ref = 'WC-'.($o['id'] ?? '');
                        $sale->client_id = optional(\App\Models\Client::first())->id ?? 1;
                        $sale->warehouse_id = optional(\App\Models\Warehouse::first())->id ?? 1;
                        $sale->user_id = auth()->id() ?? 1;
                        $sale->is_pos = 0;
                        $sale->statut = 'completed';
                        $sale->payment_statut = 'paid';
                        $sale->GrandTotal = (float) ($o['total'] ?? 0);
                        $sale->discount = 0;
                        $sale->shipping = (float) ($o['shipping_total'] ?? 0);
                        $sale->save();

                        foreach (($o['line_items'] ?? []) as $item) {
                            $code = (string) ($item['sku'] ?? ('WC-'.($item['product_id'] ?? '')));
                            $product = Product::where('code', $code)->first();
                            if (!$product) {
                                continue;
                            }

                            $detail = new SaleDetail;
                            $detail->sale_id = $sale->id;
                            $detail->product_id = $product->id;
                            $detail->quantity = (float) ($item['quantity'] ?? 1);
                            $detail->price = isset($item['price']) ? (float) $item['price'] : (float) ($product->price ?? 0);
                            $detail->total = round($detail->price * $detail->quantity, 2);
                            $detail->tax_method = '1';
                            $detail->discount = 0;
                            $detail->TaxNet = 0;
                            $detail->save();
                        }
                    }, 3);

                    $imported++;
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('orders.sync', 'error', $e->getMessage(), ['order' => $o]);
                }
            }

            if ($progress) {
                $progress(['page' => $page, 'imported' => $imported]);
            }

            if (count($orders) < $perPage) {
                break;
            }

            $page++;
        }

        $this->log('orders.sync', 'info', 'Orders sync completed', ['imported' => $imported, 'errors' => $errors]);

        return ['imported' => $imported, 'errors' => $errors];
    }

    // ---------------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------------
    public function pullCategories(?callable $progress = null): array
    {
        $synced = 0;
        $errors = 0;

        $page = 1;
        $perPage = 100;

        while (true) {
            $res = $this->client->getNoRetry('products/categories', [
                'page' => $page,
                'per_page' => $perPage,
                'hide_empty' => false,
            ], 20, 5);

            if (!$res->successful()) {
                $this->log('categories.pull', 'error', 'Failed fetching categories page', [
                    'page' => $page,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                break;
            }

            $items = $res->json();
            if (empty($items) || !is_array($items)) {
                break;
            }

            foreach ($items as $c) {
                try {
                    DB::transaction(function () use ($c) {
                        $wooId = (int) ($c['id'] ?? 0);
                        $name  = (string) ($c['name'] ?? '');

                        if ($wooId <= 0 || $name === '') {
                            return;
                        }

                        $cat = PosCategory::firstOrNew(['woocommerce_id' => $wooId]);
                        if (!$cat->exists) {
                            $cat = PosCategory::where('name', $name)->first() ?? $cat;
                        }

                        $cat->name = $name;
                        $cat->woocommerce_id = $wooId;
                        $cat->code = $cat->code ?? 'CAT-'.$wooId;
                        $cat->save();
                    }, 3);

                    $synced++;
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('categories.pull', 'error', $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'category' => $c,
                    ]);
                }
            }

            if ($progress) {
                $progress(['page' => $page, 'synced' => $synced]);
            }

            if (count($items) < $perPage) {
                break;
            }

            $page++;
        }

        $this->log('categories.pull', 'info', 'Categories pull completed', ['synced' => $synced, 'errors' => $errors]);

        return ['synced' => $synced, 'errors' => $errors];
    }

    public function pushCategories(bool $onlyUnsynced = false, ?callable $progress = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = 0;

        $query = PosCategory::whereNull('deleted_at');
        if ($onlyUnsynced) {
            $query->whereNull('woocommerce_id');
        }

        $query->orderBy('id')->chunk(100, function ($categories) use (&$created, &$updated, &$errors, &$processed, $progress) {
            foreach ($categories as $cat) {
                try {
                    // Pre-link by name to avoid duplicates
                    if (empty($cat->woocommerce_id)) {
                        $name = (string) ($cat->name ?? '');
                        if ($name !== '') {
                            try {
                                $findRes = $this->client->getNoRetry('products/categories', [
                                    'search' => $name,
                                    'per_page' => 100,
                                    'hide_empty' => false,
                                ], 20, 5);

                                if ($findRes->successful()) {
                                    $list = $findRes->json();
                                    if (is_array($list) && count($list) > 0) {
                                        foreach ($list as $remote) {
                                            if (isset($remote['id'], $remote['name']) && strcasecmp((string) $remote['name'], $name) === 0) {
                                                $cat->woocommerce_id = (int) $remote['id'];
                                                $cat->save();

                                                $this->log('categories.push', 'info', 'Linked existing Woo category by name', [
                                                    'category_id' => $cat->id,
                                                    'name' => $name,
                                                    'woocommerce_id' => $cat->woocommerce_id,
                                                ]);
                                                break;
                                            }
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    }

                    $payload = ['name' => (string) ($cat->name ?? '')];

                    $res = null;
                    $wasUpdate = false;

                    if (!empty($cat->woocommerce_id)) {
                        $res = $this->client->putNoRetry('products/categories/'.$cat->woocommerce_id, $payload, 20, 5);
                        $wasUpdate = true;

                        if ($res->status() === 404) {
                            $res = $this->client->postNoRetry('products/categories', $payload, 20, 5);
                            $wasUpdate = false;
                        }
                    } else {
                        $res = $this->client->postNoRetry('products/categories', $payload, 20, 5);
                    }

                    if (!$res->successful()) {
                        $errors++;
                        $this->log('categories.push', 'error', 'Woo request failed', [
                            'status' => $res->status(),
                            'body' => $res->body(),
                            'category_id' => $cat->id,
                        ]);
                        continue;
                    }

                    $body = $res->json();
                    $remoteId = is_array($body) ? (int) ($body['id'] ?? 0) : 0;

                    if ($remoteId > 0) {
                        if (empty($cat->woocommerce_id) || (int) $cat->woocommerce_id !== (int) $remoteId) {
                            $cat->woocommerce_id = (int) $remoteId;
                            $cat->save();
                        }
                        $wasUpdate ? $updated++ : $created++;
                    } else {
                        $errors++;
                        $this->log('categories.push', 'error', 'Missing id in Woo response', [
                            'body' => $body,
                            'category_id' => $cat->id,
                        ]);
                    }
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('categories.push', 'error', $e->getMessage(), ['category_id' => $cat->id]);
                } finally {
                    $processed++;
                }
            }

            if ($progress) {
                $progress(['processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors]);
            }
        });

        $this->log('categories.push', 'info', 'Categories push completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    // ---------------------------------------------------------------------
    // Brands (product_brand taxonomy)
    // ---------------------------------------------------------------------
    /**
     * Upload brand image to WP media and return media id/src for brands API.
     * Returns ['id' => int, 'src' => string] or null.
     */
    private function resolveOrUploadBrandImage(PosBrand $brand): ?array
    {
        try {
            $settings = WooCommerceSetting::first();
            if (!$settings) {
                return null;
            }

            $username = (string) ($settings->wp_username ?? '');
            $appPass = (string) ($settings->wp_app_password ?? '');
            $baseUrl = rtrim((string) ($settings->store_url ?? ''), '/');

            if ($username === '' || $appPass === '' || $baseUrl === '') {
                return null;
            }

            $imageName = trim((string) ($brand->image ?? ''));
            if ($imageName === '' || strtolower($imageName) === 'no-image.png') {
                return null;
            }

            $abs = public_path('images/brands/'.$imageName);
            if (!is_file($abs)) {
                return null;
            }

            $size = @filesize($abs);
            if (is_int($size) && $size > (5 * 1024 * 1024)) {
                return null;
            }

            $uploadTimeout = max(1, min(300, (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 60)));

            $upload = Http::timeout($uploadTimeout)
                ->connectTimeout(5)
                ->retry((int) env('WOO_WP_MEDIA_RETRIES', 1), (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 250))
                ->withBasicAuth($username, $appPass)
                ->attach('file', fopen($abs, 'r'), $imageName)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="'.$imageName.'"',
                ])
                ->post($baseUrl.'/wp-json/wp/v2/media');

            if ($upload->successful() || $upload->status() === 201) {
                $body = $upload->json();
                $id = (int) ($body['id'] ?? 0);
                $src = (string) ($body['source_url'] ?? '');
                if ($id > 0) {
                    return ['id' => $id, 'src' => $src];
                }
            }

            $this->log('brands.push', 'warning', 'WP media upload failed for brand image', [
                'brand_id' => $brand->id,
                'image' => $imageName,
                'status' => $upload->status(),
            ]);
        } catch (\Throwable $e) {
            $this->log('brands.push', 'warning', 'Brand image upload exception: '.$e->getMessage(), [
                'brand_id' => $brand->id ?? null,
            ]);
        }

        return null;
    }

    private function downloadBrandImage(string $url, int $wooId): ?string
    {
        try {
            $res = Http::timeout(15)->get($url);
            if (!$res->successful()) {
                return null;
            }
            $body = $res->body();
            if (empty($body)) {
                return null;
            }
            $ext = 'png';
            $contentType = $res->header('Content-Type');
            if (is_string($contentType)) {
                if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
                    $ext = 'jpg';
                } elseif (str_contains($contentType, 'gif')) {
                    $ext = 'gif';
                } elseif (str_contains($contentType, 'webp')) {
                    $ext = 'webp';
                }
            }
            $dir = public_path('images/brands');
            if (!is_dir($dir)) {
                return null;
            }
            $filename = 'woo_brand_'.$wooId.'_'.uniqid().'.'.$ext;
            $path = $dir.'/'.$filename;
            if (file_put_contents($path, $body) !== false) {
                return $filename;
            }
        } catch (\Throwable $e) {
            $this->log('brands.pull', 'warning', 'Failed to download brand image: '.$e->getMessage(), ['url' => $url]);
        }

        return null;
    }

    public function pullBrands(?callable $progress = null): array
    {
        $synced = 0;
        $errors = 0;

        $page = 1;
        $perPage = 100;

        while (true) {
            $res = $this->client->getNoRetry('products/brands', [
                'page' => $page,
                'per_page' => $perPage,
                'hide_empty' => false,
            ], 20, 5);

            if (!$res->successful()) {
                $this->log('brands.pull', 'error', 'Failed fetching brands page', [
                    'page' => $page,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                break;
            }

            $items = $res->json();
            if (empty($items) || !is_array($items)) {
                break;
            }

            foreach ($items as $t) {
                try {
                    DB::transaction(function () use ($t) {
                        $wooId = (int) ($t['id'] ?? 0);
                        $name = (string) ($t['name'] ?? '');

                        if ($wooId <= 0 || $name === '') {
                            return;
                        }

                        $brand = PosBrand::withTrashed()->firstOrNew(['woocommerce_id' => $wooId]);
                        if (!$brand->exists) {
                            $brand = PosBrand::withTrashed()->where('name', $name)->first() ?? $brand;
                        }

                        $brand->name = $name;
                        $brand->description = (string) ($t['description'] ?? '');
                        $brand->woocommerce_id = $wooId;
                        $brand->deleted_at = null;

                        $img = $t['image'] ?? null;
                        if (is_array($img) && !empty($img['src'])) {
                            $filename = $this->downloadBrandImage((string) $img['src'], $wooId);
                            if ($filename !== null) {
                                $brand->image = $filename;
                            }
                        }

                        // If Woo has no image, keep a placeholder instead of NULL/empty.
                        // Do NOT overwrite an existing image if one already exists.
                        $currentImage = trim((string) ($brand->image ?? ''));
                        if ($currentImage === '') {
                            $brand->image = 'no-image.png';
                        }

                        $brand->save();
                    }, 3);

                    $synced++;
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('brands.pull', 'error', $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'tag' => $t,
                    ]);
                }
            }

            if ($progress) {
                $progress(['page' => $page, 'synced' => $synced]);
            }

            if (count($items) < $perPage) {
                break;
            }

            $page++;
        }

        $this->log('brands.pull', 'info', 'Brands pull completed', ['synced' => $synced, 'errors' => $errors]);

        return ['synced' => $synced, 'errors' => $errors];
    }

    public function pushBrands(bool $onlyUnsynced = false, ?callable $progress = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = 0;

        $query = PosBrand::whereNull('deleted_at');
        if ($onlyUnsynced) {
            $query->whereNull('woocommerce_id');
        }

        $query->orderBy('id')->chunk(100, function ($brands) use (&$created, &$updated, &$errors, &$processed, $progress) {
            foreach ($brands as $brand) {
                try {
                    // Pre-link by name to avoid duplicates
                    if (empty($brand->woocommerce_id)) {
                        $name = (string) ($brand->name ?? '');
                        if ($name !== '') {
                            try {
                                $findRes = $this->client->getNoRetry('products/brands', [
                                    'search' => $name,
                                    'per_page' => 100,
                                    'hide_empty' => false,
                                ], 20, 5);

                                if ($findRes->successful()) {
                                    $list = $findRes->json();
                                    if (is_array($list) && count($list) > 0) {
                                        foreach ($list as $remote) {
                                            if (isset($remote['id'], $remote['name']) && strcasecmp((string) $remote['name'], $name) === 0) {
                                                $brand->woocommerce_id = (int) $remote['id'];
                                                $brand->save();

                                                $this->log('brands.push', 'info', 'Linked existing Woo brand by name', [
                                                    'brand_id' => $brand->id,
                                                    'name' => $name,
                                                    'woocommerce_id' => $brand->woocommerce_id,
                                                ]);
                                                break;
                                            }
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    }

                    $payload = [
                        'name' => (string) ($brand->name ?? ''),
                        'description' => (string) ($brand->description ?? ''),
                    ];

                    $media = $this->resolveOrUploadBrandImage($brand);
                    if ($media && isset($media['id'])) {
                        $payload['image'] = ['id' => (int) $media['id']];
                    } elseif ($media && !empty($media['src'])) {
                        $payload['image'] = ['src' => $media['src']];
                    } else {
                        $imageFile = trim((string) ($brand->image ?? ''));
                        if ($imageFile !== '' && $imageFile !== 'no-image.png') {
                            $payload['image'] = ['src' => asset('images/brands/'.$imageFile)];
                        }
                    }

                    $res = null;
                    $wasUpdate = false;

                    if (!empty($brand->woocommerce_id)) {
                        $res = $this->client->putNoRetry('products/brands/'.$brand->woocommerce_id, $payload, 20, 5);
                        $wasUpdate = true;

                        if ($res->status() === 404) {
                            $res = $this->client->postNoRetry('products/brands', $payload, 20, 5);
                            $wasUpdate = false;
                        }
                    } else {
                        $res = $this->client->postNoRetry('products/brands', $payload, 20, 5);
                    }

                    if (!$res->successful()) {
                        $errors++;
                        $this->log('brands.push', 'error', 'Woo request failed', [
                            'status' => $res->status(),
                            'body' => $res->body(),
                            'brand_id' => $brand->id,
                        ]);
                        continue;
                    }

                    $body = $res->json();
                    $remoteId = is_array($body) ? (int) ($body['id'] ?? 0) : 0;

                    if ($remoteId > 0) {
                        if (empty($brand->woocommerce_id) || (int) $brand->woocommerce_id !== (int) $remoteId) {
                            $brand->woocommerce_id = (int) $remoteId;
                            $brand->save();
                        }
                        $wasUpdate ? $updated++ : $created++;
                    } else {
                        $errors++;
                        $this->log('brands.push', 'error', 'Missing id in Woo response', [
                            'body' => $body,
                            'brand_id' => $brand->id,
                        ]);
                    }
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('brands.push', 'error', $e->getMessage(), ['brand_id' => $brand->id]);
                } finally {
                    $processed++;
                }
            }

            if ($progress) {
                $progress(['processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors]);
            }
        });

        $this->log('brands.push', 'info', 'Brands push completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Push POS clients (customers) to WooCommerce.
     *
     * Loop Stocky customers:
     *   - If woocommerce_id exists → UPDATE
     *   - Else:
     *       - Search Woo by email (best & safest)
     *       - If found → save ID → UPDATE
     *       - If not found, search by phone (optional fallback)
     *       - If found → save ID → UPDATE
     *       - If not found → CREATE → save ID
     *
     * Priority matching: Email first, then Phone (optional fallback).
     * Skips clients without email (WooCommerce requires it).
     */
    public function pushCustomers(bool $onlyUnsynced = false, ?callable $progress = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = 0;
        $skipped = 0;
        $linkedByEmail = 0;
        $linkedByPhone = 0;

        // Warm country/state index once (used to convert Stocky names -> Woo codes)
        $this->wooCountriesIndex();

        $query = PosClient::whereNull('deleted_at');
        if ($onlyUnsynced) {
            $query->whereNull('woocommerce_id');
        }

        $query->orderBy('id')->chunk(100, function ($clients) use (&$created, &$updated, &$errors, &$processed, &$skipped, &$linkedByEmail, &$linkedByPhone, $progress) {
            foreach ($clients as $client) {
                try {
                    $email = trim((string) ($client->email ?? ''));
                    $normalizedEmail = $this->normalizeEmail($email);

                    $name = trim((string) ($client->name ?? ''));
                    $firstName = trim((string) ($client->firstname ?? ''));
                    $lastName = trim((string) ($client->lastname ?? ''));
                    if ($firstName === '' && $lastName === '' && $name !== '') {
                        if (preg_match('/^(.+?)\s+(.+)$/u', $name, $m)) {
                            $firstName = $m[1];
                            $lastName = $m[2];
                        } else {
                            $firstName = $name;
                        }
                    }

                    $wooCountry = $this->resolveWooCountryCode((string) ($client->country ?? ''));
                    $wooState = $this->resolveWooStateCode((string) ($client->state ?? ''), $wooCountry);

                    $billing = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'company' => '',
                        'address_1' => $this->splitAddressLines((string) ($client->adresse ?? ''))[0],
                        'address_2' => $this->splitAddressLines((string) ($client->adresse ?? ''))[1],
                        'city' => (string) ($client->city ?? ''),
                        'state' => $wooState,
                        'postcode' => (string) ($client->zip ?? ''),
                        'phone' => (string) ($client->phone ?? ''),
                    ];
                    if ($wooCountry !== '') {
                        $billing['country'] = $wooCountry;
                    } else {
                        $this->setClientSyncIssue($client, 'country_unmapped', 'Could not map Stocky country to a valid WooCommerce country code. Use a supported country name (Woo language) or ISO code.', 'push');
                    }
                    if ($wooState === '' && trim((string) ($client->state ?? '')) !== '') {
                        $this->setClientSyncIssue($client, 'state_unmapped', 'Could not map Stocky state to a valid WooCommerce state code for the selected country.', 'push');
                    }
                    if ($email !== '') {
                        $billing['email'] = $email;
                    }
                    $shipping = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'company' => '',
                        'address_1' => $this->splitAddressLines((string) ($client->adresse ?? ''))[0],
                        'address_2' => $this->splitAddressLines((string) ($client->adresse ?? ''))[1],
                        'city' => (string) ($client->city ?? ''),
                        'state' => $wooState,
                        'postcode' => (string) ($client->zip ?? ''),
                    ];
                    if ($wooCountry !== '') {
                        $shipping['country'] = $wooCountry;
                    }

                    $payload = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        // WooCommerce REST v3 uses `billing`/`shipping`.
                        // Keep legacy keys too (some older integrations used *_address).
                        'billing' => $billing,
                        'shipping' => $shipping,
                        'billing_address' => $billing,
                        'shipping_address' => $shipping,
                    ];
                    if ($email !== '') {
                        $payload['email'] = $email;
                    }
                    if ($name !== '' && empty($client->woocommerce_id)) {
                        $payload['username'] = $name;
                    }
                    // Map extra fields to Woo meta (best-effort; doesn't require plugins)
                    $meta = [];
                    if ($name !== '') {
                        $meta[] = ['key' => 'nickname', 'value' => (string) $name];
                    }
                    // If tax_number is explicitly set (even empty), propagate to Woo.
                    // This allows clearing the value in Woo when user clears it in Stocky.
                    if ($client->tax_number !== null) {
                        $meta[] = ['key' => 'tax_number', 'value' => (string) $client->tax_number];
                        $meta[] = ['key' => 'vat_number', 'value' => (string) $client->tax_number];
                    }
                    if (!empty($meta)) {
                        $payload['meta_data'] = $meta;
                    }

                    $wooId = ! empty($client->woocommerce_id) ? (int) $client->woocommerce_id : null;
                    $wasUpdate = false;
                    $hadIssue = false;

                    // If email is empty and we don't have a Woo ID → can't match/create (manual link required)
                    if ($email === '' && $wooId === null) {
                        $skipped++;
                        $this->log('customers.push', 'info', 'Skipped client without email (requires manual link)', ['client_id' => $client->id]);
                        $this->setClientSyncIssue($client, 'missing_email', 'Stocky customer has no email. Add an email or manually link to a Woo customer.', 'push');
                        $processed++;
                        continue;
                    }

                    if ($wooId !== null) {
                        // ── If woocommerce_id exists → USE AS PRIMARY KEY → UPDATE ────────
                        // Email overwrite rules:
                        // - If Stocky email is empty: do NOT overwrite Woo email
                        // - If Stocky email is non-empty: only overwrite if unique in Woo (or belongs to same Woo customer)
                        if ($email !== '') {
                            $emailOwnerId = $this->findWooCustomerIdByEmail($normalizedEmail);
                            if ($emailOwnerId === -1 || ($emailOwnerId > 0 && $emailOwnerId !== $wooId)) {
                                unset($payload['email']);
                                if (isset($payload['billing']) && is_array($payload['billing'])) unset($payload['billing']['email']);
                                if (isset($payload['billing_address']) && is_array($payload['billing_address'])) unset($payload['billing_address']['email']);
                                $this->log('customers.push', 'warning', 'Skipped email overwrite due to Woo email uniqueness conflict (woocommerce_id match)', [
                                    'client_id' => $client->id,
                                    'woocommerce_id' => $wooId,
                                    'email' => $email,
                                    'email_owner_id' => $emailOwnerId,
                                ]);
                                $hadIssue = true;
                                if ($emailOwnerId === -1) {
                                    $this->setClientSyncIssue($client, 'ambiguous_email', 'Multiple WooCommerce customers match this email. Manual link required.', 'push');
                                } else {
                                    $this->setClientSyncIssue($client, 'id_email_mismatch', 'Woo email belongs to a different Woo customer than the linked woocommerce_id. Manual review required.', 'push');
                                }
                            }
                        } else {
                            unset($payload['email']);
                            if (isset($payload['billing']) && is_array($payload['billing'])) unset($payload['billing']['email']);
                            if (isset($payload['billing_address']) && is_array($payload['billing_address'])) unset($payload['billing_address']['email']);
                        }

                        $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                        $wasUpdate = true;
                        if ($res->status() === 404) {
                            // WooCommerce customer not found, clear the ID and try email matching
                            $client->woocommerce_id = null;
                            $client->save();
                            $wooId = null;
                            $hadIssue = true;
                            $this->setClientSyncIssue($client, 'woo_not_found', 'Linked WooCommerce customer was not found (404). Please re-link or sync again.', 'push');
                        }
                    }

                    if ($wooId === null) {
                        // ── If woocommerce_id IS null → Match by email ────────────────────
                        if ($email === '') {
                            $skipped++;
                            $this->log('customers.push', 'info', 'Skipped client without email after Woo ID cleared (requires manual link)', ['client_id' => $client->id]);
                            $this->setClientSyncIssue($client, 'missing_email', 'Stocky customer has no email. Add an email or manually link to a Woo customer.', 'push');
                            $processed++;
                            continue;
                        }

                        $foundId = $this->findWooCustomerIdByEmail($normalizedEmail);
                        
                        if ($foundId === -1) {
                            // Multiple Woo customers share same email → ambiguous, require manual link
                            $skipped++;
                            $this->log('customers.push', 'warning', 'Ambiguous email match: multiple WooCommerce customers found', [
                                'client_id' => $client->id,
                                'email' => $email,
                            ]);
                            $hadIssue = true;
                            $this->setClientSyncIssue($client, 'ambiguous_email', 'Multiple WooCommerce customers match this email. Manual link required.', 'push');
                            $processed++;
                            continue;
                        } elseif ($foundId > 0) {
                            // If a Woo customer with that email exists → link it by saving woocommerce_id locally
                            $client->woocommerce_id = $foundId;
                            $client->save();
                            $linkedByEmail++;
                            $this->log('customers.push', 'info', 'Linked existing Woo customer by email', [
                                'client_id' => $client->id,
                                'email' => $email,
                                'woocommerce_id' => $foundId,
                            ]);
                            $wooId = $foundId;
                            $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                            $wasUpdate = true;
                        } else {
                            // $foundId === 0 means no match found, create new customer
                            // If not found → CREATE → save returned woocommerce_id
                            $res = $this->client->postNoRetry('customers', $payload, 20, 5);
                            $wasUpdate = false;
                        }
                    }

                    if (! $res->successful()) {
                        $status = $res->status();
                        $body = $res->body();
                        
                        // Check if error is due to duplicate email (enforce email uniqueness)
                        if ($status === 400 || $status === 422) {
                            $errorData = json_decode($body, true);
                            $errorMessage = is_array($errorData) ? ($errorData['message'] ?? $errorData['error'] ?? $body) : $body;
                            
                            if (stripos($errorMessage, 'email') !== false && (stripos($errorMessage, 'already') !== false || stripos($errorMessage, 'exists') !== false || stripos($errorMessage, 'duplicate') !== false)) {
                                // Email already exists in WooCommerce, try to find and update
                                $foundId = $this->findWooCustomerIdByEmail($normalizedEmail);
                                if ($foundId === -1) {
                                    // Ambiguous - multiple matches
                                    $skipped++;
                                    $this->log('customers.push', 'warning', 'Ambiguous email match during error recovery', [
                                        'client_id' => $client->id,
                                        'email' => $email,
                                    ]);
                                    $processed++;
                                    continue;
                                } elseif ($foundId > 0) {
                                    $client->woocommerce_id = $foundId;
                                    $client->save();
                                    $linkedByEmail++;
                                    $wooId = $foundId;
                                    $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                                    $wasUpdate = true;
                                    
                                    if ($res->successful()) {
                                        $body = $res->json();
                                        $data = is_array($body) ? ($body['customer'] ?? $body) : [];
                                        $remoteId = is_array($data) ? (int) ($data['id'] ?? 0) : 0;
                                        if ($remoteId > 0) {
                                            if ($client->woocommerce_id !== $remoteId) {
                                                $client->woocommerce_id = $remoteId;
                                                $client->save();
                                            }
                                            $wasUpdate ? $updated++ : $created++;
                                        }
                                        $processed++;
                                        continue;
                                    }
                                }
                                // $foundId === 0 means no match found, continue to error handling
                            }
                        }
                        
                        $errors++;
                        $this->log('customers.push', 'error', 'Woo request failed', [
                            'status' => $status,
                            'body' => $body,
                            'client_id' => $client->id,
                            'email' => $email,
                        ]);
                        $hadIssue = true;
                        $this->setClientSyncIssue($client, 'woo_request_failed', 'WooCommerce request failed. Check logs / credentials and retry sync.', 'push');
                        $processed++;
                        continue;
                    }

                    $body = $res->json();
                    $data = is_array($body) ? ($body['customer'] ?? $body) : [];
                    $remoteId = is_array($data) ? (int) ($data['id'] ?? 0) : 0;

                    if ($remoteId > 0) {
                        if ($client->woocommerce_id !== $remoteId) {
                            $client->woocommerce_id = $remoteId;
                            $client->save();
                        }
                        $wasUpdate ? $updated++ : $created++;
                        if (!$hadIssue) {
                            $this->clearClientSyncIssue($client);
                        }
                    } else {
                        $errors++;
                        $this->log('customers.push', 'error', 'Missing id in Woo response', [
                            'body' => $body,
                            'client_id' => $client->id,
                        ]);
                        $hadIssue = true;
                        $this->setClientSyncIssue($client, 'woo_request_failed', 'WooCommerce response missing customer id. Retry sync.', 'push');
                    }
                } catch (Throwable $e) {
                    $errors++;
                    $this->log('customers.push', 'error', $e->getMessage(), ['client_id' => $client->id]);
                    $this->setClientSyncIssue($client, 'woo_request_failed', 'Unexpected error during sync. Retry sync.', 'push');
                } finally {
                    $processed++;
                }
            }

            if ($progress) {
                $progress(['processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors, 'skipped' => $skipped, 'linked_by_email' => $linkedByEmail, 'linked_by_phone' => $linkedByPhone]);
            }
        });

        $this->log('customers.push', 'info', 'Customers push completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'linked_by_email' => $linkedByEmail,
            'linked_by_phone' => $linkedByPhone,
        ]);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'skipped' => $skipped, 'linked_by_email' => $linkedByEmail, 'linked_by_phone' => $linkedByPhone];
    }

    /**
     * Push a single Stocky customer to WooCommerce
     */
    public function pushSingleCustomer(int $customerId): array
    {
        $client = PosClient::whereNull('deleted_at')->find($customerId);
        if (!$client) {
            return ['ok' => false, 'error' => 'Customer not found'];
        }

        $email = trim((string) ($client->email ?? ''));
        $normalizedEmail = $this->normalizeEmail($email);

        try {
            // Warm country/state index once (used to convert Stocky names -> Woo codes)
            $this->wooCountriesIndex();

            $name = trim((string) ($client->name ?? ''));
            $firstName = trim((string) ($client->firstname ?? ''));
            $lastName = trim((string) ($client->lastname ?? ''));
            if ($firstName === '' && $lastName === '' && $name !== '') {
                if (preg_match('/^(.+?)\s+(.+)$/u', $name, $m)) {
                    $firstName = $m[1];
                    $lastName = $m[2];
                } else {
                    $firstName = $name;
                }
            }

            $wooCountry = $this->resolveWooCountryCode((string) ($client->country ?? ''));
            $wooState = $this->resolveWooStateCode((string) ($client->state ?? ''), $wooCountry);

            $billing = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => '',
                'address_1' => $this->splitAddressLines((string) ($client->adresse ?? ''))[0],
                'address_2' => $this->splitAddressLines((string) ($client->adresse ?? ''))[1],
                'city' => (string) ($client->city ?? ''),
                'state' => $wooState,
                'postcode' => (string) ($client->zip ?? ''),
                'phone' => (string) ($client->phone ?? ''),
            ];
            if ($wooCountry !== '') {
                $billing['country'] = $wooCountry;
            } else {
                $this->setClientSyncIssue($client, 'country_unmapped', 'Could not map Stocky country to a valid WooCommerce country code. Use a supported country name (Woo language) or ISO code.', 'push');
            }
            if ($wooState === '' && trim((string) ($client->state ?? '')) !== '') {
                $this->setClientSyncIssue($client, 'state_unmapped', 'Could not map Stocky state to a valid WooCommerce state code for the selected country.', 'push');
            }
            if ($email !== '') {
                $billing['email'] = $email;
            }
            $shipping = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => '',
                'address_1' => $this->splitAddressLines((string) ($client->adresse ?? ''))[0],
                'address_2' => $this->splitAddressLines((string) ($client->adresse ?? ''))[1],
                'city' => (string) ($client->city ?? ''),
                'state' => $wooState,
                'postcode' => (string) ($client->zip ?? ''),
            ];
            if ($wooCountry !== '') {
                $shipping['country'] = $wooCountry;
            }

            $payload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                // WooCommerce REST v3 uses `billing`/`shipping`.
                // Keep legacy keys too (some older integrations used *_address).
                'billing' => $billing,
                'shipping' => $shipping,
                'billing_address' => $billing,
                'shipping_address' => $shipping,
            ];
            if ($email !== '') {
                $payload['email'] = $email;
            }
            if ($name !== '' && empty($client->woocommerce_id)) {
                $payload['username'] = $name;
            }
            // Map extra fields to Woo meta (best-effort; doesn't require plugins)
            $meta = [];
            if ($name !== '') {
                $meta[] = ['key' => 'nickname', 'value' => (string) $name];
            }
            // If tax_number is explicitly set (even empty), propagate to Woo.
            // This allows clearing the value in Woo when user clears it in Stocky.
            if ($client->tax_number !== null) {
                $meta[] = ['key' => 'tax_number', 'value' => (string) $client->tax_number];
                $meta[] = ['key' => 'vat_number', 'value' => (string) $client->tax_number];
            }
            if (!empty($meta)) {
                $payload['meta_data'] = $meta;
            }

            $wooId = !empty($client->woocommerce_id) ? (int) $client->woocommerce_id : null;
            $wasUpdate = false;
            $hadIssue = false;

            // If email is empty and we don't have a Woo ID → can't match/create (manual link required)
            if ($email === '' && $wooId === null) {
                $this->setClientSyncIssue($client, 'missing_email', 'Stocky customer has no email. Add an email or manually link to a Woo customer.', 'push');
                return ['ok' => false, 'error' => 'Customer must have an email to sync (requires manual link)'];
            }

            if ($wooId !== null) {
                // If woocommerce_id exists → USE AS PRIMARY KEY → UPDATE
                // Email overwrite rules:
                // - If Stocky email is empty: do NOT overwrite Woo email
                // - If Stocky email is non-empty: only overwrite if unique in Woo (or belongs to same Woo customer)
                if ($email !== '') {
                    $emailOwnerId = $this->findWooCustomerIdByEmail($normalizedEmail);
                    if ($emailOwnerId === -1 || ($emailOwnerId > 0 && $emailOwnerId !== $wooId)) {
                        unset($payload['email']);
                        if (isset($payload['billing']) && is_array($payload['billing'])) unset($payload['billing']['email']);
                        if (isset($payload['billing_address']) && is_array($payload['billing_address'])) unset($payload['billing_address']['email']);
                        $this->log('customers.push', 'warning', 'Skipped email overwrite due to Woo email uniqueness conflict (woocommerce_id match)', [
                            'client_id' => $client->id,
                            'woocommerce_id' => $wooId,
                            'email' => $email,
                            'email_owner_id' => $emailOwnerId,
                        ]);
                        $hadIssue = true;
                        if ($emailOwnerId === -1) {
                            $this->setClientSyncIssue($client, 'ambiguous_email', 'Multiple WooCommerce customers match this email. Manual link required.', 'push');
                        } else {
                            $this->setClientSyncIssue($client, 'id_email_mismatch', 'Woo email belongs to a different Woo customer than the linked woocommerce_id. Manual review required.', 'push');
                        }
                    }
                } else {
                    unset($payload['email']);
                    if (isset($payload['billing']) && is_array($payload['billing'])) unset($payload['billing']['email']);
                    if (isset($payload['billing_address']) && is_array($payload['billing_address'])) unset($payload['billing_address']['email']);
                }

                $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                $wasUpdate = true;
                if ($res->status() === 404) {
                    // WooCommerce customer not found, clear the ID and try email matching
                    $client->woocommerce_id = null;
                    $client->save();
                    $wooId = null;
                    $hadIssue = true;
                    $this->setClientSyncIssue($client, 'woo_not_found', 'Linked WooCommerce customer was not found (404). Please re-link or sync again.', 'push');
                }
            }

            if ($wooId === null) {
                // If woocommerce_id IS null → Match by email (normalize: trim + lowercase)
                if ($email === '') {
                    $this->setClientSyncIssue($client, 'missing_email', 'Stocky customer has no email. Add an email or manually link to a Woo customer.', 'push');
                    return ['ok' => false, 'error' => 'Customer must have an email to sync (requires manual link)'];
                }

                $foundId = $this->findWooCustomerIdByEmail($normalizedEmail);
                
                if ($foundId === -1) {
                    // Multiple Woo customers share same email → ambiguous, require manual link
                    $this->setClientSyncIssue($client, 'ambiguous_email', 'Multiple WooCommerce customers match this email. Manual link required.', 'push');
                    return ['ok' => false, 'error' => 'Ambiguous email match: multiple WooCommerce customers found with this email. Please link manually.'];
                } elseif ($foundId > 0) {
                    // If a Woo customer with that email exists → link it by saving woocommerce_id locally
                    $client->woocommerce_id = $foundId;
                    $client->save();
                    $wooId = $foundId;
                    $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                    $wasUpdate = true;
                } else {
                    // If not found → CREATE → save returned woocommerce_id
                    $res = $this->client->postNoRetry('customers', $payload, 20, 5);
                    $wasUpdate = false;
                }
            }

            if (!$res->successful()) {
                $status = $res->status();
                $body = $res->body();
                
                // Check if error is due to duplicate email
                if ($status === 400 || $status === 422) {
                    $errorData = json_decode($body, true);
                    $errorMessage = is_array($errorData) ? ($errorData['message'] ?? $errorData['error'] ?? $body) : $body;
                    
                    if (stripos($errorMessage, 'email') !== false && (stripos($errorMessage, 'already') !== false || stripos($errorMessage, 'exists') !== false || stripos($errorMessage, 'duplicate') !== false)) {
                        // Email already exists, try to find and update
                        $foundId = $this->findWooCustomerIdByEmail($normalizedEmail);
                        if ($foundId === -1) {
                            return ['ok' => false, 'error' => 'Ambiguous email match: multiple WooCommerce customers found with this email. Please link manually.'];
                        } elseif ($foundId > 0) {
                            $client->woocommerce_id = $foundId;
                            $client->save();
                            $wooId = $foundId;
                            $res = $this->client->putNoRetry('customers/'.$wooId, $payload, 20, 5);
                            $wasUpdate = true;
                            
                            if ($res->successful()) {
                                $body = $res->json();
                                $data = is_array($body) ? ($body['customer'] ?? $body) : [];
                                $remoteId = is_array($data) ? (int) ($data['id'] ?? 0) : 0;
                                if ($remoteId > 0) {
                                    if ($client->woocommerce_id !== $remoteId) {
                                        $client->woocommerce_id = $remoteId;
                                        $client->save();
                                    }
                                    return ['ok' => true, 'created' => $wasUpdate ? 0 : 1, 'updated' => $wasUpdate ? 1 : 0];
                                }
                            }
                        }
                        // $foundId === 0 means no match found
                        return ['ok' => false, 'error' => 'Email already exists in WooCommerce but could not be updated'];
                    }
                }
                
                return ['ok' => false, 'error' => 'WooCommerce request failed', 'status' => $status, 'body' => $body];
            }

            $body = $res->json();
            $data = is_array($body) ? ($body['customer'] ?? $body) : [];
            $remoteId = is_array($data) ? (int) ($data['id'] ?? 0) : 0;

            if ($remoteId > 0) {
                if ($client->woocommerce_id !== $remoteId) {
                    $client->woocommerce_id = $remoteId;
                    $client->save();
                }
                if (!$hadIssue) {
                    $this->clearClientSyncIssue($client);
                }
                return ['ok' => true, 'created' => $wasUpdate ? 0 : 1, 'updated' => $wasUpdate ? 1 : 0];
            }

            $this->setClientSyncIssue($client, 'woo_request_failed', 'WooCommerce response missing customer id. Retry sync.', 'push');
            return ['ok' => false, 'error' => 'Missing id in WooCommerce response'];
        } catch (Throwable $e) {
            $this->setClientSyncIssue($client, 'woo_request_failed', 'Unexpected error during sync. Retry sync.', 'push');
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pull customers from WooCommerce → Stocky
     * Uses email as the unique identifier to match customers and prevent duplicates
     */
    public function pullCustomers(?callable $progress = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = 0;
        $skipped = 0;
        $linkedByEmail = 0;

        try {
            // Warm country/state index once (used to convert Woo codes -> Stocky names)
            $this->wooCountriesIndex();

            $page = 1;
            $perPage = 100;

            while (true) {
                if ($progress) {
                    $progress(['stage' => 'fetching', 'page' => $page, 'processed' => $processed]);
                }

                $res = $this->client->getNoRetry('customers', [
                    'page' => $page,
                    'per_page' => $perPage,
                    'orderby' => 'id',
                    'order' => 'asc',
                ], 20, 5);

                if (!$res->successful()) {
                    $this->log('customers.pull', 'error', 'Failed to fetch WooCommerce customers', [
                        'status' => $res->status(),
                        'body' => $res->body(),
                        'page' => $page,
                    ]);
                    break;
                }

                $body = $res->json();
                $customers = is_array($body) ? ($body['customers'] ?? $body) : [];

                if (!is_array($customers) || empty($customers)) {
                    break;
                }

                foreach ($customers as $wooCustomer) {
                    try {
                        $processed++;

                        $wooId = (int) ($wooCustomer['id'] ?? 0);
                        if ($wooId <= 0) {
                            $skipped++;
                            continue;
                        }

                        // Email is optional for woocommerce_id-based updates.
                        // If no local match exists by woocommerce_id, we require email to match/create.
                        $email = trim((string) ($wooCustomer['email'] ?? ''));
                        $normalizedEmail = $this->normalizeEmail($email);

                        // Extract customer data from WooCommerce
                        // firstname/lastname map directly; name (username) prefers Woo username
                        $firstName = trim((string) ($wooCustomer['first_name'] ?? ''));
                        $lastName = trim((string) ($wooCustomer['last_name'] ?? ''));
                        $username = trim((string) ($wooCustomer['username'] ?? ''));
                        $wooName = trim((string) ($wooCustomer['name'] ?? ''));
                        $name = $username !== '' ? $username : ($wooName !== '' ? $wooName : trim($firstName . ' ' . $lastName));
                        if ($name === '') {
                            $name = $email;
                        }

                        $billing = $wooCustomer['billing'] ?? $wooCustomer['billing_address'] ?? [];
                        $shipping = $wooCustomer['shipping'] ?? $wooCustomer['shipping_address'] ?? [];

                        // Use billing address as primary, fallback to shipping
                        $address1 = is_array($billing) ? (string) ($billing['address_1'] ?? '') : '';
                        $address2 = is_array($billing) ? (string) ($billing['address_2'] ?? '') : '';
                        $address = $this->joinAddressLines($address1, $address2);
                        $city = is_array($billing) ? ($billing['city'] ?? '') : '';
                        $stateRaw = is_array($billing) ? ($billing['state'] ?? '') : '';
                        $zip = is_array($billing) ? ($billing['postcode'] ?? '') : '';
                        $countryRaw = is_array($billing) ? ($billing['country'] ?? '') : '';
                        $phone = is_array($billing) ? ($billing['phone'] ?? '') : '';
                        $taxInfo = $this->extractWooCustomerTaxNumber($wooCustomer, $billing);
                        $taxNumber = (string) ($taxInfo['value'] ?? '');
                        $taxPresent = (bool) ($taxInfo['present'] ?? false);
                        $country = $this->resolveWooCountryName((string) $countryRaw);
                        $state = $this->resolveWooStateName((string) $stateRaw, (string) $countryRaw);

                        // If billing is empty, try shipping
                        if ($address === '' && is_array($shipping)) {
                            $s1 = (string) ($shipping['address_1'] ?? '');
                            $s2 = (string) ($shipping['address_2'] ?? '');
                            $address = $this->joinAddressLines($s1, $s2);
                            $city = $city === '' ? ($shipping['city'] ?? '') : $city;
                            $stateShipRaw = (string) ($shipping['state'] ?? '');
                            $zip = $zip === '' ? ($shipping['postcode'] ?? '') : $zip;
                            $countryShipRaw = (string) ($shipping['country'] ?? '');
                            // Only fill if missing
                            if (trim((string) $countryRaw) === '' && $countryShipRaw !== '') {
                                $countryRaw = $countryShipRaw;
                                $country = $this->resolveWooCountryName($countryShipRaw);
                            }
                            if (trim((string) $stateRaw) === '' && $stateShipRaw !== '') {
                                $stateRaw = $stateShipRaw;
                                $state = $this->resolveWooStateName($stateShipRaw, (string) $countryRaw);
                            }
                            $country = $country === '' ? $this->resolveWooCountryName($countryShipRaw) : $country;
                            if ($phone === '' && isset($shipping['phone'])) {
                                $phone = (string) ($shipping['phone'] ?? '');
                            }
                        }

                        // Find existing customer by email (normalize: trim + lowercase)
                        // Enforce email uniqueness: unique('clients', 'email')->whereNull('deleted_at')
                        // First check if a Stocky customer already has this woocommerce_id
                        $client = PosClient::where('woocommerce_id', $wooId)
                            ->whereNull('deleted_at')
                            ->first();
                        
                        if (!$client) {
                            // If email is empty → don't auto-match by email; require manual link
                            if ($email === '') {
                                $skipped++;
                                $this->log('customers.pull', 'info', 'Skipped WooCommerce customer without email (requires manual link)', [
                                    'woocommerce_id' => $wooCustomer['id'] ?? null,
                                ]);
                                continue;
                            }

                            // If woocommerce_id not found, match by normalized email
                            $client = PosClient::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                                ->whereNull('deleted_at')
                                ->first();
                        }

                        $wasUpdate = false;
                        $hadIssue = false;
                        if ($client) {
                            if ($client->woocommerce_id === $wooId) {
                                // If woocommerce_id exists → USE AS PRIMARY KEY → UPDATE
                                $client->name = $name;
                                $client->firstname = $firstName !== '' ? $firstName : null;
                                $client->lastname = $lastName !== '' ? $lastName : null;
                                $client->adresse = $address;
                                $client->city = $city;
                                $client->state = (string) $state;
                                $client->zip = (string) $zip;
                                $client->country = $country;
                                $client->phone = $phone;
                                if ($taxPresent) {
                                    $client->tax_number = $taxNumber !== '' ? $taxNumber : null;
                                }
                                // Email update rules:
                                // - If Woo email is empty: do NOT overwrite Stocky email
                                // - If Woo email is non-empty: only overwrite if unique in clients (exclude soft-deleted, exclude this client)
                                if ($normalizedEmail !== '') {
                                    $emailConflict = PosClient::whereNull('deleted_at')
                                        ->where('id', '!=', $client->id)
                                        ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                                        ->exists();

                                    if (!$emailConflict) {
                                        $client->email = $email;
                                    } else {
                                        $this->log('customers.pull', 'warning', 'Skipped email overwrite due to uniqueness conflict (woocommerce_id match)', [
                                            'client_id' => $client->id,
                                            'woocommerce_id' => $wooId,
                                            'email' => $email,
                                        ]);
                                        $hadIssue = true;
                                        $this->setClientSyncIssue($client, 'email_conflict', 'Woo email conflicts with an existing Stocky client email. Manual review required.', 'pull');
                                    }
                                } else {
                                    $hadIssue = true;
                                    $this->setClientSyncIssue($client, 'missing_email', 'WooCommerce customer has no email. Email was not overwritten; other fields updated.', 'pull');
                                }
                            } else {
                                // Matched by email, link by saving woocommerce_id
                                $client->name = $name;
                                $client->firstname = $firstName !== '' ? $firstName : null;
                                $client->lastname = $lastName !== '' ? $lastName : null;
                                $client->adresse = $address;
                                $client->city = $city;
                                $client->state = (string) $state;
                                $client->zip = (string) $zip;
                                $client->country = $country;
                                $client->phone = $phone;
                                if ($taxPresent) {
                                    $client->tax_number = $taxNumber !== '' ? $taxNumber : null;
                                }
                                $client->woocommerce_id = $wooId;
                                $linkedByEmail++;
                            }
                            $client->save();
                            $updated++;
                            $wasUpdate = true;
                            if (!$hadIssue) {
                                $this->clearClientSyncIssue($client);
                            }
                        } else {
                            // Validate email uniqueness before creating
                            $validator = Validator::make(
                                ['email' => $email],
                                [
                                    'email' => [
                                        'required',
                                        'email',
                                        Rule::unique('clients', 'email')->whereNull('deleted_at'),
                                    ],
                                ]
                            );

                            if ($validator->fails()) {
                                $errors++;
                                $skipped++;
                                $this->log('customers.pull', 'warning', 'Skipped customer due to email uniqueness violation', [
                                    'woocommerce_id' => $wooId,
                                    'email' => $email,
                                    'error' => $validator->errors()->first('email'),
                                ]);
                                $processed++;
                                continue;
                            }

                            // Create new customer
                            // Generate a unique code for new customers
                            $maxCode = PosClient::max('code') ?? 0;
                            $newCode = $maxCode + 1;

                            try {
                                $client = PosClient::create([
                                    'name' => $name,
                                    'firstname' => $firstName !== '' ? $firstName : null,
                                    'lastname' => $lastName !== '' ? $lastName : null,
                                    'code' => $newCode,
                                    'email' => $email,
                                    'phone' => $phone,
                                    'adresse' => $address,
                                    'city' => $city,
                                    'state' => (string) $state,
                                    'zip' => (string) $zip,
                                    'country' => $country,
                                    'tax_number' => $taxPresent ? ($taxNumber !== '' ? $taxNumber : null) : null,
                                    'woocommerce_id' => $wooId,
                                ]);
                                $created++;
                                $this->clearClientSyncIssue($client);
                            } catch (\Illuminate\Database\QueryException $e) {
                                // Handle database constraint violations
                                if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                                    $errors++;
                                    $skipped++;
                                    $this->log('customers.pull', 'warning', 'Skipped customer due to database email uniqueness constraint', [
                                        'woocommerce_id' => $wooId,
                                        'email' => $email,
                                        'error' => $e->getMessage(),
                                    ]);
                                    $processed++;
                                    continue;
                                }
                                throw $e;
                            }
                        }

                        $this->log('customers.pull', 'info', ($wasUpdate ? 'Updated' : 'Created') . ' customer from WooCommerce', [
                            'client_id' => $client->id,
                            'woocommerce_id' => $wooId,
                            'email' => $email,
                        ]);

                    } catch (Throwable $e) {
                        $errors++;
                        $this->log('customers.pull', 'error', $e->getMessage(), [
                            'woocommerce_id' => $wooCustomer['id'] ?? null,
                            'email' => $email ?? null,
                        ]);
                    }
                }

                // If we got fewer than perPage, we're done
                if (count($customers) < $perPage) {
                    break;
                }

                $page++;
            }

        } catch (Throwable $e) {
            $this->log('customers.pull', 'error', 'Fatal error during customers pull: ' . $e->getMessage(), [
                'processed' => $processed,
            ]);
        }

        if ($progress) {
            $progress(['stage' => 'completed', 'processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors, 'skipped' => $skipped, 'linked_by_email' => $linkedByEmail]);
        }

        $this->log('customers.pull', 'info', 'Customers pull completed', [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'linked_by_email' => $linkedByEmail,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'linked_by_email' => $linkedByEmail,
        ];
    }

    /**
     * Pull a single WooCommerce customer to Stocky
     */
    public function pullSingleCustomer(int $wooCustomerId): array
    {
        try {
            // Warm country/state index once (used to convert Woo codes -> Stocky names)
            $this->wooCountriesIndex();

            $res = $this->client->getNoRetry('customers/'.$wooCustomerId, [], 20, 5);

            if (!$res->successful()) {
                return ['ok' => false, 'error' => 'Failed to fetch WooCommerce customer', 'status' => $res->status()];
            }

            $wooCustomer = $res->json();
            if (!is_array($wooCustomer)) {
                return ['ok' => false, 'error' => 'Invalid response from WooCommerce'];
            }

            $email = trim((string) ($wooCustomer['email'] ?? ''));
            $normalizedEmail = $this->normalizeEmail($email);

            $wooId = (int) ($wooCustomer['id'] ?? 0);
            if ($wooId <= 0) {
                return ['ok' => false, 'error' => 'Invalid WooCommerce customer ID'];
            }

            // firstname/lastname map directly; name (username) prefers Woo username
            $firstName = trim((string) ($wooCustomer['first_name'] ?? ''));
            $lastName = trim((string) ($wooCustomer['last_name'] ?? ''));
            $username = trim((string) ($wooCustomer['username'] ?? ''));
            $wooName = trim((string) ($wooCustomer['name'] ?? ''));
            $name = $username !== '' ? $username : ($wooName !== '' ? $wooName : trim($firstName . ' ' . $lastName));
            if ($name === '') {
                $name = $email;
            }

            $billing = $wooCustomer['billing'] ?? $wooCustomer['billing_address'] ?? [];
            $shipping = $wooCustomer['shipping'] ?? $wooCustomer['shipping_address'] ?? [];

            $address1 = is_array($billing) ? (string) ($billing['address_1'] ?? '') : '';
            $address2 = is_array($billing) ? (string) ($billing['address_2'] ?? '') : '';
            $address = $this->joinAddressLines($address1, $address2);
            $city = is_array($billing) ? ($billing['city'] ?? '') : '';
            $stateRaw = is_array($billing) ? ($billing['state'] ?? '') : '';
            $zip = is_array($billing) ? ($billing['postcode'] ?? '') : '';
            $countryRaw = is_array($billing) ? ($billing['country'] ?? '') : '';
            $country = $this->resolveWooCountryName((string) $countryRaw);
            $state = $this->resolveWooStateName((string) $stateRaw, (string) $countryRaw);
            $phone = is_array($billing) ? ($billing['phone'] ?? '') : '';
            $taxInfo = $this->extractWooCustomerTaxNumber($wooCustomer, $billing);
            $taxNumber = (string) ($taxInfo['value'] ?? '');
            $taxPresent = (bool) ($taxInfo['present'] ?? false);

            if ($address === '' && is_array($shipping)) {
                $s1 = (string) ($shipping['address_1'] ?? '');
                $s2 = (string) ($shipping['address_2'] ?? '');
                $address = $this->joinAddressLines($s1, $s2);
                $city = $city === '' ? ($shipping['city'] ?? '') : $city;
                $stateShipRaw = (string) ($shipping['state'] ?? '');
                $zip = $zip === '' ? ($shipping['postcode'] ?? '') : $zip;
                $countryShipRaw = (string) ($shipping['country'] ?? '');
                if (trim((string) $countryRaw) === '' && $countryShipRaw !== '') {
                    $countryRaw = $countryShipRaw;
                    $country = $this->resolveWooCountryName($countryShipRaw);
                }
                if (trim((string) $stateRaw) === '' && $stateShipRaw !== '') {
                    $stateRaw = $stateShipRaw;
                    $state = $this->resolveWooStateName($stateShipRaw, (string) $countryRaw);
                }
                if ($phone === '' && isset($shipping['phone'])) {
                    $phone = (string) ($shipping['phone'] ?? '');
                }
            }

            // First check if a Stocky customer already has this woocommerce_id (primary key)
            $client = PosClient::where('woocommerce_id', $wooId)
                ->whereNull('deleted_at')
                ->first();
            
            if ($client) {
                // If woocommerce_id exists → USE AS PRIMARY KEY → UPDATE
                $hadIssue = false;
                $client->name = $name;
                $client->firstname = $firstName !== '' ? $firstName : null;
                $client->lastname = $lastName !== '' ? $lastName : null;
                $client->adresse = $address;
                $client->city = $city;
                $client->state = (string) $state;
                $client->zip = (string) $zip;
                $client->country = $country;
                $client->phone = $phone;
                if ($taxPresent) {
                    $client->tax_number = $taxNumber !== '' ? $taxNumber : null;
                }
                // Email update rules:
                // - If Woo email is empty: do NOT overwrite Stocky email
                // - If Woo email is non-empty: only overwrite if unique in clients (exclude soft-deleted, exclude this client)
                if ($normalizedEmail !== '') {
                    $emailConflict = PosClient::whereNull('deleted_at')
                        ->where('id', '!=', $client->id)
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                        ->exists();

                    if (!$emailConflict) {
                        $client->email = $email;
                    } else {
                        $this->log('customers.pull', 'warning', 'Skipped email overwrite due to uniqueness conflict (woocommerce_id match)', [
                            'client_id' => $client->id,
                            'woocommerce_id' => $wooId,
                            'email' => $email,
                        ]);
                        $hadIssue = true;
                        $this->setClientSyncIssue($client, 'email_conflict', 'Woo email conflicts with an existing Stocky client email. Manual review required.', 'pull');
                    }
                } else {
                    $hadIssue = true;
                    $this->setClientSyncIssue($client, 'missing_email', 'WooCommerce customer has no email. Email was not overwritten; other fields updated.', 'pull');
                }
                $client->save();
                if (!$hadIssue) {
                    $this->clearClientSyncIssue($client);
                }
                return ['ok' => true, 'created' => 0, 'updated' => 1];
            }
            
            // If email is empty and we didn't match by woocommerce_id → don't auto-match; require manual link
            if ($email === '') {
                return ['ok' => false, 'error' => 'WooCommerce customer has no email (requires manual link)'];
            }

            // If woocommerce_id not found, match by normalized email (trim + lowercase)
            // Enforce email uniqueness: unique('clients', 'email')->whereNull('deleted_at')
            $client = PosClient::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                ->whereNull('deleted_at')
                ->first();

            if ($client) {
                // If a Stocky customer with that email exists → link it by saving woocommerce_id
                $client->name = $name;
                $client->firstname = $firstName !== '' ? $firstName : null;
                $client->lastname = $lastName !== '' ? $lastName : null;
                $client->adresse = $address;
                $client->city = $city;
                $client->state = (string) $state;
                $client->zip = (string) $zip;
                $client->country = $country;
                $client->phone = $phone;
                if ($taxPresent) {
                    $client->tax_number = $taxNumber !== '' ? $taxNumber : null;
                }
                $client->woocommerce_id = $wooId;
                $client->save();
                return ['ok' => true, 'created' => 0, 'updated' => 1];
            } else {
                // If not found → CREATE → store that Woo ID in the new row
                // Validate email uniqueness before creating
                $validator = Validator::make(
                    ['email' => $email],
                    [
                        'email' => [
                            'required',
                            'email',
                            Rule::unique('clients', 'email')->whereNull('deleted_at'),
                        ],
                    ]
                );

                if ($validator->fails()) {
                    return ['ok' => false, 'error' => 'Email already exists: ' . $validator->errors()->first('email')];
                }

                $maxCode = PosClient::max('code') ?? 0;
                $newCode = $maxCode + 1;

                try {
                    PosClient::create([
                        'name' => $name,
                        'firstname' => $firstName !== '' ? $firstName : null,
                        'lastname' => $lastName !== '' ? $lastName : null,
                        'code' => $newCode,
                        'email' => $email,
                        'phone' => $phone,
                        'adresse' => $address,
                        'city' => $city,
                        'state' => (string) $state,
                        'zip' => (string) $zip,
                        'country' => $country,
                        'tax_number' => $taxPresent ? ($taxNumber !== '' ? $taxNumber : null) : null,
                        'woocommerce_id' => $wooId,
                    ]);
                    return ['ok' => true, 'created' => 1, 'updated' => 0];
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle database constraint violations
                    if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                        return ['ok' => false, 'error' => 'Email already exists in database'];
                    }
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Split Stocky single-line address into Woo address_1/address_2.
     * Best-effort: supports newline-separated values if present.
     *
     * @return array{0:string,1:string}
     */
    private function splitAddressLines(string $address): array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return ['', ''];
        }

        // If user stored newlines, map first line to address_1 and the rest to address_2.
        $normalized = str_replace(["\r\n", "\r"], "\n", $address);
        if (str_contains($normalized, "\n")) {
            $parts = array_values(array_filter(array_map('trim', explode("\n", $normalized)), fn ($v) => $v !== ''));
            if (empty($parts)) {
                return ['', ''];
            }
            $a1 = (string) array_shift($parts);
            $a2 = trim(implode(' ', $parts));
            return [$a1, $a2];
        }

        return [$address, ''];
    }

    /**
     * Join Woo address lines into a Stocky single-line address.
     */
    private function joinAddressLines(string $address1, string $address2): string
    {
        $a1 = trim((string) $address1);
        $a2 = trim((string) $address2);
        if ($a1 === '') {
            return $a2;
        }
        if ($a2 === '') {
            return $a1;
        }
        return trim($a1.' '.$a2);
    }

    /**
     * Extract tax/VAT number from Woo customer payload (billing + meta_data).
     * Stores/plugins vary widely, so we try common keys.
     */
    private function extractWooCustomerTaxNumber(array $wooCustomer, $billing = null): array
    {
        $present = false;

        // 1) Billing-level fields (rare, plugin-dependent)
        if (is_array($billing)) {
            foreach (['tax_number', 'vat_number', 'vat', 'company_vat', 'billing_vat', '_billing_vat'] as $k) {
                if (array_key_exists($k, $billing)) {
                    $present = true;
                }
                $v = trim((string) ($billing[$k] ?? ''));
                if ($v !== '') {
                    return ['present' => true, 'value' => $v];
                }
            }
        }

        // 2) Meta data array: [{id,key,value}, ...]
        $meta = $wooCustomer['meta_data'] ?? null;
        if (is_array($meta)) {
            $keys = [
                'tax_number',
                'vat_number',
                'vat',
                '_vat_number',
                '_billing_vat',
                'billing_vat',
                'eu_vat_number',
                'wcpdf_billing_vat_number',
            ];
            $wanted = array_fill_keys($keys, true);

            foreach ($meta as $m) {
                if (!is_array($m)) continue;
                $key = trim((string) ($m['key'] ?? ''));
                if ($key === '') continue;
                $keyLower = mb_strtolower($key);
                if (!isset($wanted[$keyLower])) continue;
                $present = true;
                $val = trim((string) ($m['value'] ?? ''));
                if ($val !== '') {
                    return ['present' => true, 'value' => $val];
                }
            }
        }

        return ['present' => $present, 'value' => ''];
    }

    private function normKey(string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = preg_replace('/\s+/u', ' ', $s);
        // Keep letters/numbers/spaces only (works across locales)
        $s = preg_replace('/[^\p{L}\p{N} ]/u', '', $s);
        return trim((string) $s);
    }

    /**
     * Build country/state indexes from WooCommerce `/data/countries`.
     *
     * Returns:
     * - countriesByCode: [ 'MA' => 'Morocco', ... ]
     * - countryCodeByName: [ 'morocco' => 'MA', ... ] (normalized)
     * - statesByCountryCode: [ 'US' => [ 'CA' => 'California', ... ], ... ]
     * - stateCodeByName: [ 'US' => [ 'california' => 'CA', ... ], ... ] (normalized)
     */
    private function wooCountriesIndex(): array
    {
        static $idx = null;
        if (is_array($idx)) {
            return $idx;
        }

        $idx = [
            'countriesByCode' => [],
            'countryCodeByName' => [],
            'statesByCountryCode' => [],
            'stateCodeByName' => [],
        ];

        try {
            $res = $this->client->getNoRetry('data/countries', [], 20, 5);
            if (!$res->successful()) {
                return $idx;
            }

            $rows = $res->json();
            if (!is_array($rows)) {
                return $idx;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code === '') continue;
                if ($name !== '') {
                    $idx['countriesByCode'][$code] = $name;
                    $idx['countryCodeByName'][$this->normKey($name)] = $code;
                }

                $states = $row['states'] ?? null;
                if (is_array($states) && !empty($states)) {
                    foreach ($states as $st) {
                        if (!is_array($st)) continue;
                        $sc = strtoupper(trim((string) ($st['code'] ?? '')));
                        $sn = trim((string) ($st['name'] ?? ''));
                        if ($sc === '' || $sn === '') continue;
                        if (!isset($idx['statesByCountryCode'][$code])) {
                            $idx['statesByCountryCode'][$code] = [];
                        }
                        if (!isset($idx['stateCodeByName'][$code])) {
                            $idx['stateCodeByName'][$code] = [];
                        }
                        $idx['statesByCountryCode'][$code][$sc] = $sn;
                        $idx['stateCodeByName'][$code][$this->normKey($sn)] = $sc;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore: fallback to raw values (codes)
        }

        return $idx;
    }

    private function resolveWooCountryName(string $countryCodeOrName): string
    {
        $raw = trim((string) $countryCodeOrName);
        if ($raw === '') return '';

        $idx = $this->wooCountriesIndex();
        $upper = strtoupper($raw);
        if (strlen($upper) === 2 && isset($idx['countriesByCode'][$upper])) {
            return (string) $idx['countriesByCode'][$upper];
        }

        $code = $idx['countryCodeByName'][$this->normKey($raw)] ?? null;
        if (is_string($code) && $code !== '' && isset($idx['countriesByCode'][$code])) {
            return (string) $idx['countriesByCode'][$code];
        }

        // Unknown: keep original
        return $raw;
    }

    private function resolveWooCountryCode(string $countryNameOrCode): string
    {
        $raw = trim((string) $countryNameOrCode);
        if ($raw === '') return '';

        $idx = $this->wooCountriesIndex();
        $upper = strtoupper($raw);
        if (strlen($upper) === 2 && isset($idx['countriesByCode'][$upper])) {
            return $upper;
        }

        $code = $idx['countryCodeByName'][$this->normKey($raw)] ?? null;
        if (is_string($code) && $code !== '') {
            return $code;
        }

        // Unknown: do NOT guess. Returning '' prevents overwriting Woo with invalid values.
        return '';
    }

    private function resolveWooStateName(string $stateCodeOrName, string $countryCodeOrName): string
    {
        $rawState = trim((string) $stateCodeOrName);
        if ($rawState === '') return '';

        $countryCode = $this->resolveWooCountryCode($countryCodeOrName);
        if ($countryCode === '') return $rawState;

        $idx = $this->wooCountriesIndex();
        $stateUpper = strtoupper($rawState);
        $byCode = $idx['statesByCountryCode'][$countryCode] ?? null;
        if (is_array($byCode) && isset($byCode[$stateUpper])) {
            return (string) $byCode[$stateUpper];
        }

        // If already a name, keep it
        return $rawState;
    }

    private function resolveWooStateCode(string $stateNameOrCode, string $countryCodeOrName): string
    {
        $rawState = trim((string) $stateNameOrCode);
        if ($rawState === '') return '';

        $countryCode = $this->resolveWooCountryCode($countryCodeOrName);
        if ($countryCode === '') return '';

        $idx = $this->wooCountriesIndex();
        $stateUpper = strtoupper($rawState);
        $byCode = $idx['statesByCountryCode'][$countryCode] ?? null;
        if (is_array($byCode) && !empty($byCode) && isset($byCode[$stateUpper])) {
            return $stateUpper;
        }

        // If Woo doesn't define states for this country, accept free text (Woo stores it as-is).
        if (!is_array($byCode) || empty($byCode)) {
            return $rawState;
        }

        $byName = $idx['stateCodeByName'][$countryCode] ?? null;
        if (is_array($byName)) {
            $code = $byName[$this->normKey($rawState)] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        // Country has a known state list but we couldn't resolve -> don't overwrite.
        return '';
    }

    /**
     * Normalize email for matching: trim + lowercase
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Search WooCommerce customers by email with optimized early-exit pagination.
     * Returns:
     *   - WooCommerce customer ID (int > 0) if exactly one match found
     *   - -1 if multiple customers found (ambiguous case)
     *   - 0 if no match found
     * Uses normalized email (trim + lowercase) for matching.
     * Stops immediately once ambiguity is detected to avoid heavy queries.
     */
    private function findWooCustomerIdByEmail(string $email): int
    {
        try {
            $normalizedEmail = $this->normalizeEmail($email);
            if ($normalizedEmail === '') {
                return 0;
            }

            $page = 1;
            $perPage = 100;
            $matches = [];
            $totalPages = null;

            while (true) {
                // Request customers by normalized email (trim + lowercase)
                $findRes = $this->client->getNoRetry('customers', [
                    'search' => $email, // WooCommerce search will match email
                    'per_page' => $perPage,
                    'page' => $page,
                ], 20, 5);

                if (! $findRes->successful()) {
                    // If first page fails, return 0 (no match)
                    // If later page fails, return what we have so far
                    break;
                }

                $list = $findRes->json();
                if (! is_array($list)) {
                    break;
                }

                $items = $list['customers'] ?? $list;
                if (! is_array($items) || empty($items)) {
                    // No more items, stop pagination
                    break;
                }

                // Get total pages info if available (first page only)
                if ($page === 1) {
                    $totalPages = (int) ($list['total_pages'] ?? 1);
                }

                // Check each customer in this page
                foreach ($items as $remote) {
                    $remoteEmail = $this->normalizeEmail((string) ($remote['email'] ?? ''));
                    if ($remoteEmail === $normalizedEmail && ! empty($remote['id'])) {
                        $matches[] = (int) $remote['id'];
                        
                        // Early exit: if more than one match found, return -1 immediately (ambiguous)
                        if (count($matches) > 1) {
                            return -1;
                        }
                    }
                }

                // Early exit: if we already found one match and this is the last page, return it
                if (count($matches) === 1) {
                    // Check if there are more pages that might contain additional matches
                    // If total_pages is known and we're on the last page, we're safe
                    if ($totalPages !== null && $page >= $totalPages) {
                        return $matches[0];
                    }
                    // If we don't know total pages, continue to next page to check for ambiguity
                    // But if current page has fewer items than per_page, we're on the last page
                    if (count($items) < $perPage) {
                        return $matches[0];
                    }
                }

                // If no matches found yet and we're on the last page, exit
                if (count($matches) === 0) {
                    if ($totalPages !== null && $page >= $totalPages) {
                        break;
                    }
                    if (count($items) < $perPage) {
                        break;
                    }
                }

                $page++;

                // Safety limit: don't paginate forever
                if ($page > 100) {
                    break;
                }
            }

            // Return results based on match count
            if (count($matches) > 1) {
                return -1; // Ambiguous
            } elseif (count($matches) === 1) {
                return $matches[0]; // Single match
            } else {
                return 0; // No match
            }
        } catch (\Throwable $e) {
            $this->log('customers.find_by_email', 'error', 'Error finding WooCommerce customer by email: ' . $e->getMessage(), [
                'email' => $email,
            ]);
        }

        return 0;
    }

    /**
     * Search WooCommerce customers by phone (optional fallback).
     * Compares normalized digits-only to handle format differences.
     * Returns Woo customer ID or null.
     */
    private function findWooCustomerIdByPhone(string $phone): ?int
    {
        $phoneNorm = $this->normalizePhoneForMatching($phone);
        if ($phoneNorm === '') {
            return null;
        }

        try {
            $findRes = $this->client->getNoRetry('customers', [
                'search' => $phone,
                'per_page' => 50,
            ], 20, 5);

            if (! $findRes->successful()) {
                return null;
            }

            $list = $findRes->json();
            if (! is_array($list)) {
                return null;
            }

            $items = $list['customers'] ?? $list;
            if (! is_array($items)) {
                return null;
            }

            foreach ($items as $remote) {
                if (empty($remote['id'])) {
                    continue;
                }
                $billing = $remote['billing_address'] ?? $remote['billing'] ?? [];
                $remotePhone = trim((string) (is_array($billing) ? ($billing['phone'] ?? '') : ''));
                if ($remotePhone === '') {
                    continue;
                }
                if ($this->normalizePhoneForMatching($remotePhone) === $phoneNorm) {
                    return (int) $remote['id'];
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Normalize phone for matching: digits only (ignore spaces, dashes, +, etc.).
     */
    private function normalizePhoneForMatching(string $phone): string
    {
        $s = preg_replace('/\D/', '', $phone);

        return $s === '' ? '' : $s;
    }

    // ---------------------------------------------------------------------
    // Order line item -> local Product/Variant resolution
    // ---------------------------------------------------------------------
    /**
     * Resolve a Woo order line item (product_id, variation_id, sku) to a local
     * Stocky Product (and optional ProductVariant). Used by both bulk and
     * single-order pull paths so the lookup logic stays in one place.
     *
     * Returns ['product' => Product|null, 'variant' => ProductVariant|null].
     * If product is null the caller should treat the line as unmapped.
     *
     * Lookup order:
     *   1. ProductVariant by woocommerce_variation_id (with parent product
     *      cross-check against woo product_id, soft-deleted excluded).
     *   2. Product by woocommerce_id (soft-deleted excluded).
     *   3. SKU fallback: ProductVariant.code first (variation SKUs in Woo are
     *      stored on the variation, not the parent), then Product.code. Only
     *      matches when exactly one row matches — ambiguous matches return null.
     */
    private function resolveLineItemMapping(int $wooProductId, int $wooVariationId, string $sku): array
    {
        $product = null;
        $variant = null;

        if ($wooVariationId > 0) {
            $candidate = ProductVariant::where('woocommerce_variation_id', $wooVariationId)
                ->whereNull('deleted_at')
                ->first();
            if ($candidate) {
                $candidateProduct = Product::where('id', $candidate->product_id)
                    ->whereNull('deleted_at')
                    ->first();
                // Sanity check: the variant's parent must agree with the line
                // item's woo product_id. If they disagree the mapping is stale
                // (e.g. a Woo product was deleted and its ID was reused).
                if ($candidateProduct
                    && ($wooProductId <= 0
                        || (int) $candidateProduct->woocommerce_id === $wooProductId)) {
                    $variant = $candidate;
                    $product = $candidateProduct;
                }
            }
        }

        if (!$product && $wooProductId > 0) {
            $product = Product::where('woocommerce_id', $wooProductId)
                ->whereNull('deleted_at')
                ->first();
        }

        if (!$product && $sku !== '') {
            // Try variant SKU first — Woo stores variation SKUs on the variation,
            // and resolving at the variant level lets us decrement the right
            // stock row instead of the parent.
            $variantHits = ProductVariant::where('product_variants.code', $sku)
                ->whereNull('product_variants.deleted_at')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('products')
                        ->whereColumn('products.id', 'product_variants.product_id')
                        ->whereNull('products.deleted_at');
                })
                ->limit(2)
                ->get();

            if ($variantHits->count() === 1) {
                $variant = $variantHits->first();
                $product = Product::where('id', $variant->product_id)
                    ->whereNull('deleted_at')
                    ->first();
            } elseif ($variantHits->count() === 0) {
                // No variant match — try parent product code, but only if the
                // match is unambiguous.
                $productHits = Product::where('code', $sku)
                    ->whereNull('deleted_at')
                    ->limit(2)
                    ->get();
                if ($productHits->count() === 1) {
                    $product = $productHits->first();
                }
            }
        }

        return ['product' => $product, 'variant' => $variant];
    }

    // ---------------------------------------------------------------------
    // Logger
    // ---------------------------------------------------------------------
    private function log(string $action, string $level, string $message, array $context = []): void
    {
        try {
            WooCommerceLog::create([
                'action' => $action,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            // never crash sync because of logging
        }
    }
}
