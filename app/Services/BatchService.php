<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\AdjustmentDetailBatch;
use App\Models\Damage;
use App\Models\DamageDetail;
use App\Models\DamageDetailBatch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseDetailBatch;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetailBatch;
use App\Models\PurchaseReturnDetails;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailBatch;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleDetailBatch;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetailBatch;
use App\Models\SaleReturnDetails;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\TransferDetailBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * BatchService
 *
 * Handles batch + expiry side-effects for stock movements.
 *
 * Designed to be 100% opt-in:
 * - If the pharmacy migration hasn't run, all methods short-circuit without error.
 * - If a product doesn't have is_batch_tracked = true, its detail rows are ignored.
 * - Existing stock-on-hand (product_warehouse.qte) accounting is left to the callers
 *   — this service ONLY mirrors the same quantities into product_batches rows so
 *   reports and FEFO consumption have a per-batch breakdown.
 */
class BatchService
{
    /**
     * Whether the schema supports batch tracking. Cached per-request.
     */
    protected ?bool $supported = null;

    public function isSupported(): bool
    {
        if ($this->supported !== null) {
            return $this->supported;
        }

        $this->supported = Schema::hasTable('product_batches')
            && Schema::hasTable('purchase_detail_batches')
            && Schema::hasColumn('products', 'is_batch_tracked');

        return $this->supported;
    }

    /**
     * Whether a single product opted in to batch tracking.
     */
    public function productIsTracked($productId): bool
    {
        if (! $this->isSupported() || ! $productId) {
            return false;
        }

        return (bool) Product::whereKey($productId)->value('is_batch_tracked');
    }

    /**
     * Apply batch records for a freshly-stored purchase.
     *
     * @param  Purchase             $purchase  The saved Purchase.
     * @param  array                $inputDetails  The original $request['details'] array (line order matters).
     * @param  PurchaseDetail[]|\Illuminate\Support\Collection  $persistedDetails
     *         The PurchaseDetail rows in the same line order as $inputDetails.
     */
    public function applyForPurchase(Purchase $purchase, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $batches = $this->extractBatchesFromRow($row);
            if (empty($batches)) {
                continue;
            }

            if (! $this->productIsTracked($row['product_id'] ?? null)) {
                continue;
            }

            $this->applyBatchesToDetail($purchase, $detail, $batches);
        }
    }

    /**
     * Reverse batch effects for a list of PurchaseDetail rows that are about to be
     * deleted or whose quantities are about to be reset by a controller.
     *
     * @param  iterable|\Illuminate\Support\Collection  $purchaseDetails
     */
    public function reverseForPurchaseDetails($purchaseDetails): void
    {
        if (! $this->isSupported()) {
            return;
        }

        foreach ($purchaseDetails as $detail) {
            if (! $detail || ! isset($detail->id)) {
                continue;
            }

            $pivots = PurchaseDetailBatch::where('purchase_detail_id', $detail->id)->get();
            foreach ($pivots as $pivot) {
                $batch = ProductBatch::find($pivot->product_batch_id);
                if ($batch) {
                    $batch->qty = max(0, (float) $batch->qty - (float) $pivot->qty);
                    if ($batch->qty == 0.0 && $batch->purchaseDetailBatches()->count() <= 1) {
                        // No remaining sources for this batch — soft-delete it.
                        $batch->delete();
                    } else {
                        $batch->save();
                    }
                }
                $pivot->delete();
            }
        }
    }

    /**
     * Pull a clean batches array out of a raw detail row from the request.
     */
    protected function extractBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchNo = trim((string) ($b['batch_no'] ?? ''));
            $qty = (float) ($b['qty'] ?? 0);
            if ($batchNo === '' || $qty <= 0) {
                continue;
            }
            $out[] = [
                'batch_no' => $batchNo,
                'expiry_date' => $this->normalizeDate($b['expiry_date'] ?? null),
                'mfg_date' => $this->normalizeDate($b['mfg_date'] ?? null),
                'qty' => $qty,
                'unit_cost' => isset($b['unit_cost']) && $b['unit_cost'] !== '' ? (float) $b['unit_cost'] : null,
                'notes' => isset($b['notes']) ? (string) $b['notes'] : null,
            ];
        }

        return $out;
    }

    protected function normalizeDate($v): ?string
    {
        if ($v === null || $v === '' || $v === 'null') {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create or top-up ProductBatch rows for a given PurchaseDetail and
     * link them via the pivot.
     */
    protected function applyBatchesToDetail(Purchase $purchase, PurchaseDetail $detail, array $batches): void
    {
        $productId = (int) $detail->product_id;
        $variantId = $detail->product_variant_id ? (int) $detail->product_variant_id : null;
        $warehouseId = (int) $purchase->warehouse_id;

        foreach ($batches as $b) {
            $existing = ProductBatch::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('batch_no', $b['batch_no'])
                ->where(function ($q) use ($variantId) {
                    if ($variantId === null) {
                        $q->whereNull('product_variant_id');
                    } else {
                        $q->where('product_variant_id', $variantId);
                    }
                })
                ->first();

            if ($existing) {
                $existing->qty = (float) $existing->qty + (float) $b['qty'];
                if ($b['expiry_date'] && empty($existing->expiry_date)) {
                    $existing->expiry_date = $b['expiry_date'];
                }
                if ($b['mfg_date'] && empty($existing->mfg_date)) {
                    $existing->mfg_date = $b['mfg_date'];
                }
                if ($b['unit_cost'] !== null && empty($existing->unit_cost)) {
                    $existing->unit_cost = $b['unit_cost'];
                }
                if ($existing->status === 'expired' || $existing->status === 'written_off') {
                    $existing->status = 'active';
                }
                $existing->save();
                $batch = $existing;
            } else {
                $batch = ProductBatch::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'batch_no' => $b['batch_no'],
                    'expiry_date' => $b['expiry_date'],
                    'mfg_date' => $b['mfg_date'],
                    'qty' => (float) $b['qty'],
                    'unit_cost' => $b['unit_cost'] ?? (float) ($detail->cost ?? 0),
                    'provider_id' => $purchase->provider_id ?? null,
                    'source_purchase_id' => $purchase->id,
                    'status' => 'active',
                    'notes' => $b['notes'] ?? null,
                ]);
            }

            PurchaseDetailBatch::create([
                'purchase_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => (float) $b['qty'],
                'unit_cost' => $b['unit_cost'] ?? (float) ($detail->cost ?? 0),
            ]);
        }
    }

    /**
     * Available batches for a product (+ variant + warehouse), FEFO-ordered.
     * Returns only active batches with qty > 0. Expired or written-off batches are excluded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableBatchesForSale(int $productId, ?int $variantId, int $warehouseId): array
    {
        if (! $this->isSupported() || $productId <= 0 || $warehouseId <= 0) {
            return [];
        }

        $query = ProductBatch::active()
            ->forProduct($productId)
            ->forWarehouse($warehouseId)
            ->where('qty', '>', 0);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $rows = $query->fefo()->get();

        $out = [];
        foreach ($rows as $b) {
            $out[] = [
                'id' => (int) $b->id,
                'batch_no' => (string) $b->batch_no,
                'expiry_date' => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $b->mfg_date ? $b->mfg_date->format('Y-m-d') : null,
                'qty_available' => (float) $b->qty,
                'unit_cost' => $b->unit_cost !== null ? (float) $b->unit_cost : null,
                'status' => (string) $b->status,
            ];
        }

        return $out;
    }

    /**
     * Apply batch consumption for a freshly-stored sale.
     * Decrements product_batches.qty and creates sale_detail_batches pivot rows.
     * Expects each row in $inputDetails to optionally carry a 'batches' array with
     * [{ product_batch_id, qty, unit_price? }, ...].
     *
     * @param  Sale  $sale
     * @param  array  $inputDetails  Request details array (line order aligned with $persistedDetails).
     * @param  iterable  $persistedDetails  SaleDetail rows in the same order as $inputDetails.
     */
    public function applyForSale(Sale $sale, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $batches = $this->extractSaleBatchesFromRow($row);
            if (empty($batches)) {
                continue;
            }

            if (! $this->productIsTracked($row['product_id'] ?? null)) {
                continue;
            }

            $this->applySaleBatchesToDetail($sale, $detail, $batches);
        }
    }

    /**
     * Reverse batch consumption for a list of SaleDetail rows (e.g. sale deletion or cancellation).
     * Restores qty onto the ProductBatch and deletes pivot rows.
     *
     * @param  iterable  $saleDetails
     */
    public function reverseForSaleDetails($saleDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_detail_batches')) {
            return;
        }

        foreach ($saleDetails as $detail) {
            if (! $detail || ! isset($detail->id)) {
                continue;
            }

            $pivots = SaleDetailBatch::where('sale_detail_id', $detail->id)->get();
            foreach ($pivots as $pivot) {
                $batch = ProductBatch::find($pivot->product_batch_id);
                if ($batch) {
                    $batch->qty = (float) $batch->qty + (float) $pivot->qty;
                    // If the batch had been auto-expired/written-off earlier, leave status alone;
                    // only flip it back to active if it was soft-deleted (not handled here).
                    $batch->save();
                }
                $pivot->delete();
            }
        }
    }

    /**
     * Pull a clean sale-batches array out of a raw detail row from the request.
     * Each entry must have product_batch_id and qty > 0.
     */
    protected function extractSaleBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = (int) ($b['product_batch_id'] ?? 0);
            $qty = (float) ($b['qty'] ?? 0);
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $out[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
                'unit_price' => isset($b['unit_price']) && $b['unit_price'] !== '' ? (float) $b['unit_price'] : null,
            ];
        }

        return $out;
    }

    /**
     * Decrement product_batches.qty and create sale_detail_batches pivot rows.
     */
    protected function applySaleBatchesToDetail(Sale $sale, SaleDetail $detail, array $batches): void
    {
        $productId = (int) $detail->product_id;
        $variantId = $detail->product_variant_id ? (int) $detail->product_variant_id : null;
        $warehouseId = (int) $sale->warehouse_id;

        foreach ($batches as $b) {
            $batch = ProductBatch::where('id', $b['product_batch_id'])
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where(function ($q) use ($variantId) {
                    if ($variantId === null) {
                        $q->whereNull('product_variant_id');
                    } else {
                        $q->where('product_variant_id', $variantId);
                    }
                })
                ->first();

            if (! $batch) {
                // Silently skip rather than throw — caller is responsible for pre-validation
                // (the create-sale form does full batch validation before submit).
                continue;
            }

            $consume = min((float) $b['qty'], (float) $batch->qty);
            if ($consume <= 0) {
                continue;
            }

            $batch->qty = max(0, (float) $batch->qty - $consume);
            $batch->save();

            SaleDetailBatch::create([
                'sale_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $consume,
                'unit_price' => $b['unit_price'],
            ]);
        }
    }

    /**
     * Apply batch consumption for a sale, with automatic FEFO fallback per detail.
     * If a row carries explicit batch picks, those are used; otherwise the detail's
     * quantity is consumed from available batches in FEFO order. This is used on
     * edit_sale where the original UI may not collect per-line batch selections.
     *
     * @param  Sale  $sale
     * @param  array  $inputDetails  Request details array (line order aligned with $persistedDetails).
     * @param  iterable  $persistedDetails  SaleDetail rows in the same order as $inputDetails.
     */
    public function applyForSaleWithAutoFallback(Sale $sale, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $batches = $this->extractSaleBatchesFromRow($row);
            if (! empty($batches)) {
                $this->applySaleBatchesToDetail($sale, $detail, $batches);
            } else {
                $this->autoFefoForSaleDetail($sale, $detail);
            }
        }
    }

    /**
     * FEFO-auto-consume for a SaleDetail using its stored quantity.
     * Walks active batches for this product+warehouse(+variant) in expiry-ascending
     * order and decrements qty until the line is satisfied or batches run out.
     * If no batches exist the detail is left unchanged (the product_warehouse.qte
     * path is still authoritative for overall stock).
     */
    protected function autoFefoForSaleDetail(Sale $sale, SaleDetail $detail): void
    {
        $qtyNeeded = (float) ($detail->quantity ?? 0);
        if ($qtyNeeded <= 0) {
            return;
        }

        $productId = (int) $detail->product_id;
        $variantId = $detail->product_variant_id ? (int) $detail->product_variant_id : null;
        $warehouseId = (int) $sale->warehouse_id;

        $query = ProductBatch::active()
            ->forProduct($productId)
            ->forWarehouse($warehouseId)
            ->where('qty', '>', 0);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $batches = $query->fefo()->get();

        $remaining = $qtyNeeded;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $batch->qty, $remaining);
            if ($take <= 0) {
                continue;
            }

            $batch->qty = max(0, (float) $batch->qty - $take);
            $batch->save();

            SaleDetailBatch::create([
                'sale_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $take,
                'unit_price' => (float) ($detail->price ?? 0),
            ]);

            $remaining -= $take;
        }
    }

    /**
     * Eager-load batches for a SaleDetail collection (for edit_sale response).
     *
     * @return array<int, array<int, array<string, mixed>>>  detail_id => [ batch payload, ... ]
     */
    public function batchesForSaleDetails($saleDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_detail_batches')) {
            return [];
        }

        $detailIds = collect($saleDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = SaleDetailBatch::with('batch')
            ->whereIn('sale_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->sale_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_price' => $r->unit_price !== null ? (float) $r->unit_price : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    /**
     * Eager-load batches for a PurchaseDetail collection (for edit_purchase response).
     *
     * @return array<int, array<int, array<string, mixed>>>  detail_id => [ batch payload, ... ]
     */
    public function batchesForPurchaseDetails($purchaseDetails): array
    {
        if (! $this->isSupported()) {
            return [];
        }

        $detailIds = collect($purchaseDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = PurchaseDetailBatch::with('batch')
            ->whereIn('purchase_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->purchase_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_cost' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    // ============================================================
    // Purchase Return — qty out of warehouse (mirror of Sale flow)
    // ============================================================

    /**
     * Apply batch consumption for a purchase return. Mirrors the Sale flow:
     *  - if a row carries a non-empty `batches` array, consume those exact batches
     *  - else auto-FEFO from the return's warehouse
     */
    public function applyForPurchaseReturnWithAutoFallback(PurchaseReturn $return, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('purchase_return_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $batches = $this->extractPurchaseReturnBatchesFromRow($row);
            if (! empty($batches)) {
                $this->applyPurchaseReturnBatchesToDetail($return, $detail, $batches);
            } else {
                $this->autoFefoForPurchaseReturnDetail($return, $detail);
            }
        }
    }

    /**
     * Reverse batch consumption for a set of return details: add qty back to product_batches
     * and remove the pivot rows. Used on update / destroy.
     */
    public function reverseForPurchaseReturnDetails($returnDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('purchase_return_detail_batches')) {
            return;
        }

        $detailIds = collect($returnDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        $rows = PurchaseReturnDetailBatch::whereIn('purchase_return_detail_id', $detailIds)->get();
        foreach ($rows as $r) {
            $batch = ProductBatch::find($r->product_batch_id);
            if ($batch) {
                $batch->qty = (float) $batch->qty + (float) $r->qty;
                $batch->save();
            }
            $r->delete();
        }
    }

    /**
     * Display payload for a PurchaseReturnDetails collection (for show / detail page).
     *
     * @return array<int, array<int, array<string, mixed>>>  detail_id => [ batch payload, ... ]
     */
    public function batchesForPurchaseReturnDetails($returnDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('purchase_return_detail_batches')) {
            return [];
        }

        $detailIds = collect($returnDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = PurchaseReturnDetailBatch::with('batch')
            ->whereIn('purchase_return_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->purchase_return_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_cost' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    protected function extractPurchaseReturnBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
                'unit_cost' => isset($b['unit_cost']) ? (float) $b['unit_cost'] : null,
            ];
        }
        return $clean;
    }

    protected function applyPurchaseReturnBatchesToDetail(PurchaseReturn $return, PurchaseReturnDetails $detail, array $batches): void
    {
        foreach ($batches as $b) {
            $batch = ProductBatch::find($b['product_batch_id']);
            if (! $batch) {
                continue;
            }
            $take = (float) $b['qty'];
            if ($take <= 0) {
                continue;
            }
            $batch->qty = max(0, (float) $batch->qty - $take);
            $batch->save();

            PurchaseReturnDetailBatch::create([
                'purchase_return_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $take,
                'unit_cost' => $b['unit_cost'] ?? (float) ($detail->cost ?? 0),
            ]);
        }
    }

    protected function autoFefoForPurchaseReturnDetail(PurchaseReturn $return, PurchaseReturnDetails $detail): void
    {
        $qtyNeeded = (float) ($detail->quantity ?? 0);
        if ($qtyNeeded <= 0) {
            return;
        }

        $productId = (int) $detail->product_id;
        $variantId = $detail->product_variant_id ? (int) $detail->product_variant_id : null;
        $warehouseId = (int) $return->warehouse_id;

        $query = ProductBatch::active()
            ->forProduct($productId)
            ->forWarehouse($warehouseId)
            ->where('qty', '>', 0);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $batches = $query->fefo()->get();

        $remaining = $qtyNeeded;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $batch->qty, $remaining);
            if ($take <= 0) {
                continue;
            }
            $batch->qty = max(0, (float) $batch->qty - $take);
            $batch->save();

            PurchaseReturnDetailBatch::create([
                'purchase_return_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $take,
                'unit_cost' => (float) ($detail->cost ?? 0),
            ]);

            $remaining -= $take;
        }
    }

    // ============================================================
    // Sale Return — qty back into warehouse (mirror of Purchase flow)
    // ============================================================
    //
    // A sale return increases warehouse stock. To keep the per-batch ledger
    // consistent with product_warehouse.qte we credit batches symmetrically:
    //  - prefer explicit batches sent from the UI
    //  - else mirror the linked sale's SaleDetailBatch entries proportionally
    //  - else (no link / no original batches) skip per-batch — warehouse qte
    //    is still authoritative for total stock.

    public function applyForSaleReturnWithAutoFallback(SaleReturn $return, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_return_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $batches = $this->extractSaleReturnBatchesFromRow($row);
            if (! empty($batches)) {
                $this->applySaleReturnBatchesToDetail($return, $detail, $batches);
            } else {
                $this->autoMirrorSaleReturnDetail($return, $detail);
            }
        }
    }

    public function reverseForSaleReturnDetails($returnDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_return_detail_batches')) {
            return;
        }

        $detailIds = collect($returnDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        $rows = SaleReturnDetailBatch::whereIn('sale_return_detail_id', $detailIds)->get();
        foreach ($rows as $r) {
            $batch = ProductBatch::find($r->product_batch_id);
            if ($batch) {
                // Reverse a credit: subtract the qty we previously added back.
                $batch->qty = max(0, (float) $batch->qty - (float) $r->qty);
                $batch->save();
            }
            $r->delete();
        }
    }

    public function batchesForSaleReturnDetails($returnDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('sale_return_detail_batches')) {
            return [];
        }

        $detailIds = collect($returnDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = SaleReturnDetailBatch::with('batch')
            ->whereIn('sale_return_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->sale_return_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_price' => $r->unit_price !== null ? (float) $r->unit_price : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    protected function extractSaleReturnBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
                'unit_price' => isset($b['unit_price']) ? (float) $b['unit_price'] : null,
            ];
        }
        return $clean;
    }

    protected function applySaleReturnBatchesToDetail(SaleReturn $return, SaleReturnDetails $detail, array $batches): void
    {
        foreach ($batches as $b) {
            $batch = ProductBatch::find($b['product_batch_id']);
            if (! $batch) {
                continue;
            }
            $add = (float) $b['qty'];
            if ($add <= 0) {
                continue;
            }
            // Credit qty back to the batch.
            $batch->qty = (float) $batch->qty + $add;
            $batch->save();

            SaleReturnDetailBatch::create([
                'sale_return_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $add,
                'unit_price' => $b['unit_price'] ?? (float) ($detail->price ?? 0),
            ]);
        }
    }

    /**
     * Auto-credit a sale return detail by mirroring the original sale's batch
     * consumption (proportionally if the returned qty differs from the sold qty).
     * Falls through silently when no link / no batches exist on the linked sale.
     */
    protected function autoMirrorSaleReturnDetail(SaleReturn $return, SaleReturnDetails $detail): void
    {
        if (! $return->sale_id) {
            return;
        }
        // sale_detail_batches is required to mirror the original sale's allocation.
        if (! Schema::hasTable('sale_detail_batches')) {
            return;
        }

        $saleDetailQuery = SaleDetail::where('sale_id', $return->sale_id)
            ->where('product_id', $detail->product_id);

        if ($detail->product_variant_id !== null) {
            $saleDetailQuery->where('product_variant_id', $detail->product_variant_id);
        } else {
            $saleDetailQuery->whereNull('product_variant_id');
        }

        $saleDetail = $saleDetailQuery->first();
        if (! $saleDetail) {
            return;
        }

        $sdb = SaleDetailBatch::where('sale_detail_id', $saleDetail->id)->get();
        if ($sdb->isEmpty()) {
            return;
        }

        $totalSold = (float) $sdb->sum('qty');
        $returnQty = (float) ($detail->quantity ?? 0);
        if ($totalSold <= 0 || $returnQty <= 0) {
            return;
        }

        $allocated = 0.0;
        $rows = $sdb->values();
        $last = $rows->count() - 1;
        foreach ($rows as $i => $r) {
            // Distribute proportionally; correct rounding drift on the last share.
            if ($i === $last) {
                $share = $returnQty - $allocated;
            } else {
                $share = round(($r->qty / $totalSold) * $returnQty, 4);
                $allocated += $share;
            }
            if ($share <= 0) {
                continue;
            }

            $batch = ProductBatch::find($r->product_batch_id);
            if ($batch) {
                $batch->qty = (float) $batch->qty + $share;
                $batch->save();
            }

            SaleReturnDetailBatch::create([
                'sale_return_detail_id' => $detail->id,
                'product_batch_id' => $r->product_batch_id,
                'qty' => $share,
                'unit_price' => (float) ($detail->price ?? 0),
            ]);
        }
    }

    // ============================================================
    // Reports — unified per-batch activity log
    // ============================================================
    //
    // Returns a chronological list of every movement that touched a
    // single product_batch row, across all 4 pivot tables, plus
    // reconciliation totals so the UI can flag drift between
    // SUM(transactions) and the live ProductBatch.qty.

    public function transactionsForBatch(int $batchId): array
    {
        $empty = [
            'transactions' => [],
            'totals' => [
                'in' => 0.0,
                'out' => 0.0,
                'computed_qty' => 0.0,
                'actual_qty' => 0.0,
                'drift' => 0.0,
            ],
            'supported' => false,
        ];

        if (! $this->isSupported()) {
            return $empty;
        }

        $batch = ProductBatch::find($batchId);
        if (! $batch) {
            return $empty;
        }

        $movements = collect();

        // --- Purchases (qty IN) ---
        if (Schema::hasTable('purchase_detail_batches')) {
            $rows = PurchaseDetailBatch::with([
                'purchaseDetail.purchase.provider:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->purchaseDetail;
                $purchase = $detail ? $detail->purchase : null;
                if (! $purchase) {
                    continue;
                }
                $movements->push([
                    'type' => 'purchase',
                    'direction' => 'in',
                    'date' => $purchase->date,
                    'sort_key' => $this->buildSortKey($purchase->date, $purchase->id),
                    'ref' => $purchase->Ref,
                    'ref_id' => (int) $purchase->id,
                    'party_label' => 'Supplier',
                    'party_name' => optional($purchase->provider)->name,
                    'party_id' => $purchase->provider_id ? (int) $purchase->provider_id : null,
                    'qty_in' => (float) $r->qty,
                    'qty_out' => 0.0,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Sale Returns (qty IN) ---
        if (Schema::hasTable('sale_return_detail_batches')) {
            $rows = SaleReturnDetailBatch::with([
                'saleReturnDetail.SaleReturn.client:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->saleReturnDetail;
                $return = $detail ? $detail->SaleReturn : null;
                if (! $return) {
                    continue;
                }
                $movements->push([
                    'type' => 'sale_return',
                    'direction' => 'in',
                    'date' => $return->date,
                    'sort_key' => $this->buildSortKey($return->date, $return->id),
                    'ref' => $return->Ref,
                    'ref_id' => (int) $return->id,
                    'party_label' => 'Customer',
                    'party_name' => optional($return->client)->name,
                    'party_id' => $return->client_id ? (int) $return->client_id : null,
                    'qty_in' => (float) $r->qty,
                    'qty_out' => 0.0,
                    'unit_value' => $r->unit_price !== null ? (float) $r->unit_price : null,
                    'unit_label' => 'unit_price',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Sales (qty OUT) ---
        if (Schema::hasTable('sale_detail_batches')) {
            $rows = SaleDetailBatch::with([
                'saleDetail.sale.client:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->saleDetail;
                $sale = $detail ? $detail->sale : null;
                if (! $sale) {
                    continue;
                }
                $movements->push([
                    'type' => 'sale',
                    'direction' => 'out',
                    'date' => $sale->date,
                    'sort_key' => $this->buildSortKey($sale->date, $sale->id),
                    'ref' => $sale->Ref,
                    'ref_id' => (int) $sale->id,
                    'party_label' => 'Customer',
                    'party_name' => optional($sale->client)->name,
                    'party_id' => $sale->client_id ? (int) $sale->client_id : null,
                    'qty_in' => 0.0,
                    'qty_out' => (float) $r->qty,
                    'unit_value' => $r->unit_price !== null ? (float) $r->unit_price : null,
                    'unit_label' => 'unit_price',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Purchase Returns (qty OUT) ---
        if (Schema::hasTable('purchase_return_detail_batches')) {
            $rows = PurchaseReturnDetailBatch::with([
                'purchaseReturnDetail.PurchaseReturn.provider:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->purchaseReturnDetail;
                $return = $detail ? $detail->PurchaseReturn : null;
                if (! $return) {
                    continue;
                }
                $movements->push([
                    'type' => 'purchase_return',
                    'direction' => 'out',
                    'date' => $return->date,
                    'sort_key' => $this->buildSortKey($return->date, $return->id),
                    'ref' => $return->Ref,
                    'ref_id' => (int) $return->id,
                    'party_label' => 'Supplier',
                    'party_name' => optional($return->provider)->name,
                    'party_id' => $return->provider_id ? (int) $return->provider_id : null,
                    'qty_in' => 0.0,
                    'qty_out' => (float) $r->qty,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Stock Adjustments (qty IN or OUT — pivot.direction decides) ---
        if (Schema::hasTable('adjustment_detail_batches')) {
            $rows = AdjustmentDetailBatch::with([
                'adjustmentDetail.adjustment.warehouse:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->adjustmentDetail;
                $adjustment = $detail ? $detail->adjustment : null;
                if (! $adjustment) {
                    continue;
                }
                $direction = (string) ($r->direction ?? 'sub');
                $isIn = $direction === 'in' || $direction === 'add';
                $movements->push([
                    'type' => 'adjustment',
                    'direction' => $isIn ? 'in' : 'out',
                    'date' => $adjustment->date,
                    'sort_key' => $this->buildSortKey($adjustment->date, $adjustment->id),
                    'ref' => $adjustment->Ref,
                    'ref_id' => (int) $adjustment->id,
                    'party_label' => 'Warehouse',
                    'party_name' => optional($adjustment->warehouse)->name,
                    'party_id' => $adjustment->warehouse_id ? (int) $adjustment->warehouse_id : null,
                    'qty_in' => $isIn ? (float) $r->qty : 0.0,
                    'qty_out' => $isIn ? 0.0 : (float) $r->qty,
                    'unit_value' => null,
                    'unit_label' => null,
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Transfers OUT (this batch is the source) ---
        if (Schema::hasTable('transfer_detail_batches')) {
            $rows = TransferDetailBatch::with([
                'transferDetail.transfer.from_warehouse:id,name',
                'transferDetail.transfer.to_warehouse:id,name',
            ])
                ->where('source_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->transferDetail;
                $transfer = $detail ? $detail->transfer : null;
                if (! $transfer) {
                    continue;
                }
                $movements->push([
                    'type' => 'transfer_out',
                    'direction' => 'out',
                    'date' => $transfer->date,
                    'sort_key' => $this->buildSortKey($transfer->date, $transfer->id),
                    'ref' => $transfer->Ref ?? null,
                    'ref_id' => (int) $transfer->id,
                    'party_label' => 'To Warehouse',
                    'party_name' => optional($transfer->to_warehouse)->name,
                    'party_id' => $transfer->to_warehouse_id ? (int) $transfer->to_warehouse_id : null,
                    'qty_in' => 0.0,
                    'qty_out' => (float) $r->qty,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Transfers IN (this batch is the destination) ---
        if (Schema::hasTable('transfer_detail_batches')) {
            $rows = TransferDetailBatch::with([
                'transferDetail.transfer.from_warehouse:id,name',
                'transferDetail.transfer.to_warehouse:id,name',
            ])
                ->where('dest_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->transferDetail;
                $transfer = $detail ? $detail->transfer : null;
                if (! $transfer) {
                    continue;
                }
                $movements->push([
                    'type' => 'transfer_in',
                    'direction' => 'in',
                    'date' => $transfer->date,
                    'sort_key' => $this->buildSortKey($transfer->date, $transfer->id),
                    'ref' => $transfer->Ref ?? null,
                    'ref_id' => (int) $transfer->id,
                    'party_label' => 'From Warehouse',
                    'party_name' => optional($transfer->from_warehouse)->name,
                    'party_id' => $transfer->from_warehouse_id ? (int) $transfer->from_warehouse_id : null,
                    'qty_in' => (float) $r->qty,
                    'qty_out' => 0.0,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Damages (qty OUT) ---
        if (Schema::hasTable('damage_detail_batches')) {
            $rows = DamageDetailBatch::with([
                'damageDetail.damage.warehouse:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->damageDetail;
                $damage = $detail ? $detail->damage : null;
                if (! $damage) {
                    continue;
                }
                $movements->push([
                    'type' => 'damage',
                    'direction' => 'out',
                    'date' => $damage->date,
                    'sort_key' => $this->buildSortKey($damage->date, $damage->id),
                    'ref' => $damage->Ref,
                    'ref_id' => (int) $damage->id,
                    'party_label' => 'Warehouse',
                    'party_name' => optional($damage->warehouse)->name,
                    'party_id' => $damage->warehouse_id ? (int) $damage->warehouse_id : null,
                    'qty_in' => 0.0,
                    'qty_out' => (float) $r->qty,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // --- Quotations (informational — do NOT move stock) ---
        // Quotations are proposals; they reserve a batch but don't debit ProductBatch.qty.
        // We surface them in the history view so users can trace allocations, but report
        // qty_in / qty_out as 0 so the running balance and reconciliation totals stay correct.
        if (Schema::hasTable('quotation_detail_batches')) {
            $rows = QuotationDetailBatch::with([
                'quotationDetail.quotation.client:id,name',
            ])
                ->where('product_batch_id', $batchId)
                ->get();

            foreach ($rows as $r) {
                $detail = $r->quotationDetail;
                $quotation = $detail ? $detail->quotation : null;
                if (! $quotation) {
                    continue;
                }
                $movements->push([
                    'type' => 'quotation',
                    'direction' => 'info',
                    'date' => $quotation->date,
                    'sort_key' => $this->buildSortKey($quotation->date, $quotation->id),
                    'ref' => $quotation->Ref,
                    'ref_id' => (int) $quotation->id,
                    'party_label' => 'Customer',
                    'party_name' => optional($quotation->client)->name,
                    'party_id' => $quotation->client_id ? (int) $quotation->client_id : null,
                    'qty_in' => 0.0,
                    'qty_out' => 0.0,
                    'unit_value' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                    'unit_label' => 'unit_cost',
                    'reserved_qty' => (float) $r->qty,
                    'pivot_id' => (int) $r->id,
                ]);
            }
        }

        // Chronological sort with deterministic tie-break.
        $sorted = $movements
            ->sortBy('sort_key')
            ->values();

        $totalIn = 0.0;
        $totalOut = 0.0;
        $running = 0.0;
        $out = [];
        foreach ($sorted as $m) {
            $totalIn += $m['qty_in'];
            $totalOut += $m['qty_out'];
            $running += $m['qty_in'] - $m['qty_out'];
            $row = $m;
            $row['running_balance'] = $running;
            // sort_key + pivot_id are internal — drop from public payload.
            unset($row['sort_key']);
            $out[] = $row;
        }

        $actual = (float) $batch->qty;
        $computed = $totalIn - $totalOut;

        return [
            'transactions' => $out,
            'totals' => [
                'in' => $totalIn,
                'out' => $totalOut,
                'computed_qty' => $computed,
                'actual_qty' => $actual,
                'drift' => $actual - $computed,
            ],
            'supported' => true,
        ];
    }

    /**
     * Build a string sort key combining the parent record's date and id so
     * same-day movements always order deterministically and idempotently.
     */
    protected function buildSortKey($date, $id): string
    {
        try {
            $iso = Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $iso = '0000-00-00 00:00:00';
        }
        return $iso.'|'.str_pad((string) (int) $id, 12, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // Stock adjustments — bidirectional (add = qty IN, sub = qty OUT)
    // ============================================================
    //
    // Each AdjustmentDetail row carries a `type` of either 'add' or 'sub'.
    //  - add: warehouse qty goes UP — credit a chosen batch (or skip if none)
    //  - sub: warehouse qty goes DOWN — debit a chosen batch, or auto-FEFO
    //
    // The pivot row stores `direction` ('in'/'out') so reversal does not need
    // to consult the parent detail's type.

    public function applyForAdjustmentWithAutoFallback(Adjustment $adjustment, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('adjustment_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $type = (string) ($row['type'] ?? $detail->type ?? 'sub');
            $batches = $this->extractAdjustmentBatchesFromRow($row);

            if ($type === 'add') {
                // Credit batches when the user provided them; otherwise skip per-batch
                // tracking — for an "add" adjustment we cannot guess which batch
                // owns the unknown stock, and warehouse qte is still authoritative.
                if (! empty($batches)) {
                    $this->applyAdjustmentBatchesToDetail($adjustment, $detail, $batches, 'in');
                }
            } else {
                // Subtract: prefer explicit batches, else auto-FEFO from the warehouse.
                if (! empty($batches)) {
                    $this->applyAdjustmentBatchesToDetail($adjustment, $detail, $batches, 'out');
                } else {
                    $this->autoFefoForAdjustmentDetail($adjustment, $detail);
                }
            }
        }
    }

    public function reverseForAdjustmentDetails($adjustmentDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('adjustment_detail_batches')) {
            return;
        }

        $detailIds = collect($adjustmentDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        $rows = AdjustmentDetailBatch::whereIn('adjustment_detail_id', $detailIds)->get();
        foreach ($rows as $r) {
            $batch = ProductBatch::find($r->product_batch_id);
            if ($batch) {
                if ($r->direction === 'in') {
                    // Original was a credit — un-do by subtracting the same qty.
                    $batch->qty = max(0, (float) $batch->qty - (float) $r->qty);
                } else {
                    // Original was a debit — un-do by adding the qty back.
                    $batch->qty = (float) $batch->qty + (float) $r->qty;
                }
                $batch->save();
            }
            $r->delete();
        }
    }

    public function batchesForAdjustmentDetails($adjustmentDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('adjustment_detail_batches')) {
            return [];
        }

        $detailIds = collect($adjustmentDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = AdjustmentDetailBatch::with('batch')
            ->whereIn('adjustment_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->adjustment_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'direction' => (string) $r->direction,
                'qty' => (float) $r->qty,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    protected function extractAdjustmentBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
            ];
        }
        return $clean;
    }

    protected function applyAdjustmentBatchesToDetail(Adjustment $adjustment, AdjustmentDetail $detail, array $batches, string $direction): void
    {
        foreach ($batches as $b) {
            $batch = ProductBatch::find($b['product_batch_id']);
            if (! $batch) {
                continue;
            }
            $delta = (float) $b['qty'];
            if ($delta <= 0) {
                continue;
            }

            if ($direction === 'in') {
                $batch->qty = (float) $batch->qty + $delta;
            } else {
                $batch->qty = max(0, (float) $batch->qty - $delta);
            }
            $batch->save();

            AdjustmentDetailBatch::create([
                'adjustment_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'direction' => $direction,
                'qty' => $delta,
            ]);
        }
    }

    protected function autoFefoForAdjustmentDetail(Adjustment $adjustment, AdjustmentDetail $detail): void
    {
        $qtyNeeded = (float) ($detail->quantity ?? 0);
        if ($qtyNeeded <= 0) {
            return;
        }

        $productId = (int) $detail->product_id;
        $variantId = $detail->product_variant_id ? (int) $detail->product_variant_id : null;
        $warehouseId = (int) $adjustment->warehouse_id;

        $query = ProductBatch::active()
            ->forProduct($productId)
            ->forWarehouse($warehouseId)
            ->where('qty', '>', 0);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $batches = $query->fefo()->get();

        $remaining = $qtyNeeded;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $batch->qty, $remaining);
            if ($take <= 0) {
                continue;
            }
            $batch->qty = max(0, (float) $batch->qty - $take);
            $batch->save();

            AdjustmentDetailBatch::create([
                'adjustment_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'direction' => 'out',
                'qty' => $take,
            ]);

            $remaining -= $take;
        }
    }

    // ============================================================
    // Transfers — qty out of source warehouse, qty into destination warehouse
    // ============================================================
    //
    // For each batch-tracked detail:
    //   - DEBIT one or more batches in the source warehouse (user-picked or auto-FEFO)
    //   - When the transfer is "completed" (or approved+completed), find-or-create the
    //     matching batch in the destination warehouse and CREDIT it the same qty.
    //   - When the transfer is "sent" (in-transit), only debit source — destination
    //     credit happens when the transfer is later marked completed.
    //
    // The pivot row stores both source_batch_id and dest_batch_id (nullable for sent)
    // so reverseForTransferDetails can walk it directly without re-deriving.

    public function applyForTransferWithAutoFallback(Transfer $transfer, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('transfer_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            // Strict picker mode: only apply when the user explicitly chose source batches.
            // The frontend forbids saving a batch-tracked line without picks, so an empty
            // array here means a non-strict path (e.g. legacy approve replay) — skip it
            // rather than silently auto-FEFO behind the user's back.
            $userBatches = $this->extractTransferBatchesFromRow($row);
            if (! empty($userBatches)) {
                $this->applyTransferBatchesToDetail($transfer, $detail, $userBatches);
            }
        }
    }

    public function reverseForTransferDetails($transferDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('transfer_detail_batches')) {
            return;
        }

        $detailIds = collect($transferDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        $rows = TransferDetailBatch::whereIn('transfer_detail_id', $detailIds)->get();
        foreach ($rows as $r) {
            // Restore source: add qty back.
            $src = ProductBatch::find($r->source_batch_id);
            if ($src) {
                $src->qty = (float) $src->qty + (float) $r->qty;
                $src->save();
            }
            // Reverse destination credit: subtract qty back.
            if ($r->dest_batch_id) {
                $dst = ProductBatch::find($r->dest_batch_id);
                if ($dst) {
                    $dst->qty = max(0, (float) $dst->qty - (float) $r->qty);
                    $dst->save();
                }
            }
            $r->delete();
        }
    }

    public function batchesForTransferDetails($transferDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('transfer_detail_batches')) {
            return [];
        }

        $detailIds = collect($transferDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = TransferDetailBatch::with(['sourceBatch', 'destBatch'])
            ->whereIn('transfer_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $src = $r->sourceBatch;
            if (! $src) {
                continue;
            }
            $out[(int) $r->transfer_detail_id][] = [
                'source_batch_id' => (int) $r->source_batch_id,
                'dest_batch_id' => $r->dest_batch_id ? (int) $r->dest_batch_id : null,
                'batch_no' => (string) $src->batch_no,
                'expiry_date' => $src->expiry_date ? $src->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $src->mfg_date ? $src->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_cost' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                'status' => (string) $src->status,
            ];
        }

        return $out;
    }

    protected function extractTransferBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            // The picker stores its selected source batch under product_batch_id (mirrors
            // the create_sale picker). Source is the only ID the user picks; destination
            // is auto-resolved at apply time.
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'source_batch_id' => $batchId,
                'qty' => $qty,
                'unit_cost' => isset($b['unit_cost']) ? (float) $b['unit_cost'] : null,
            ];
        }
        return $clean;
    }

    protected function applyTransferBatchesToDetail(Transfer $transfer, TransferDetail $detail, array $batches): void
    {
        foreach ($batches as $b) {
            $src = ProductBatch::find($b['source_batch_id']);
            if (! $src) {
                continue;
            }
            $take = (float) $b['qty'];
            if ($take <= 0) {
                continue;
            }

            // Debit source.
            $src->qty = max(0, (float) $src->qty - $take);
            $src->save();

            // Credit destination only when the transfer is "completed" — for "sent"
            // transfers we leave dest_batch_id null until completion.
            $destId = null;
            if ($transfer->statut === 'completed') {
                $dst = $this->findOrCreateDestBatch($src, (int) $transfer->to_warehouse_id);
                if ($dst) {
                    $dst->qty = (float) $dst->qty + $take;
                    $dst->save();
                    $destId = (int) $dst->id;
                }
            }

            TransferDetailBatch::create([
                'transfer_detail_id' => $detail->id,
                'source_batch_id' => (int) $src->id,
                'dest_batch_id' => $destId,
                'qty' => $take,
                'unit_cost' => $b['unit_cost'] ?? ($src->unit_cost !== null ? (float) $src->unit_cost : null),
            ]);
        }
    }

    /**
     * Find an existing matching batch in the destination warehouse, or create one
     * by replicating the source row's identity (batch_no, mfg_date, expiry_date,
     * unit_cost, status, product_variant_id) with qty = 0.
     */
    protected function findOrCreateDestBatch(ProductBatch $src, int $destWarehouseId): ?ProductBatch
    {
        $existing = ProductBatch::query()
            ->whereNull('deleted_at')
            ->where('product_id', $src->product_id)
            ->where('warehouse_id', $destWarehouseId)
            ->where('batch_no', $src->batch_no);

        if ($src->product_variant_id !== null) {
            $existing->where('product_variant_id', $src->product_variant_id);
        } else {
            $existing->whereNull('product_variant_id');
        }

        $row = $existing->first();
        if ($row) {
            return $row;
        }

        $new = $src->replicate(['qty']);
        $new->warehouse_id = $destWarehouseId;
        $new->qty = 0;
        $new->save();

        return $new;
    }

    // ============================================================
    // Damages — qty out of warehouse (strict picker, no auto-FEFO)
    // ============================================================
    //
    // A damage entry is qty leaving the warehouse permanently. The picker on the
    // create/edit page is the source of truth for which batches were damaged —
    // we never auto-FEFO, since that would silently choose batches the user did
    // not intend (sale picker is the same model).

    public function applyForDamageWithAutoFallback(Damage $damage, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('damage_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $batches = $this->extractDamageBatchesFromRow($row);
            if (empty($batches)) {
                // Strict mode: skip the line if the user hasn't picked batches.
                // The frontend prevents this state for tracked products.
                continue;
            }

            $this->applyDamageBatchesToDetail($damage, $detail, $batches);
        }
    }

    public function reverseForDamageDetails($damageDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('damage_detail_batches')) {
            return;
        }

        $detailIds = collect($damageDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        $rows = DamageDetailBatch::whereIn('damage_detail_id', $detailIds)->get();
        foreach ($rows as $r) {
            $batch = ProductBatch::find($r->product_batch_id);
            if ($batch) {
                // Reverse a debit: add the qty back.
                $batch->qty = (float) $batch->qty + (float) $r->qty;
                $batch->save();
            }
            $r->delete();
        }
    }

    public function batchesForDamageDetails($damageDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('damage_detail_batches')) {
            return [];
        }

        $detailIds = collect($damageDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = DamageDetailBatch::with('batch')
            ->whereIn('damage_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->damage_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_cost' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    protected function extractDamageBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
                'unit_cost' => isset($b['unit_cost']) ? (float) $b['unit_cost'] : null,
            ];
        }
        return $clean;
    }

    protected function applyDamageBatchesToDetail(Damage $damage, DamageDetail $detail, array $batches): void
    {
        foreach ($batches as $b) {
            $batch = ProductBatch::find($b['product_batch_id']);
            if (! $batch) {
                continue;
            }
            $take = (float) $b['qty'];
            if ($take <= 0) {
                continue;
            }

            $batch->qty = max(0, (float) $batch->qty - $take);
            $batch->save();

            DamageDetailBatch::create([
                'damage_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $take,
                'unit_cost' => $b['unit_cost'] ?? ($batch->unit_cost !== null ? (float) $batch->unit_cost : null),
            ]);
        }
    }

    // ============================================================
    //  Quotations (no stock movement — record picks only)
    // ============================================================
    //
    // A quotation is a proposal, not a stock move. The picker captures the user's
    // intent ("if this is accepted, fulfill from these batches") so the same picks
    // can be displayed on the quote PDF and seeded into the sale when converted.
    // We never touch ProductBatch.qty for quotations.

    public function assignForQuotationWithAutoFallback(Quotation $quotation, array $inputDetails, $persistedDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('quotation_detail_batches')) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (! $detail) {
                continue;
            }

            $productId = $row['product_id'] ?? $detail->product_id ?? null;
            if (! $this->productIsTracked($productId)) {
                continue;
            }

            $batches = $this->extractQuotationBatchesFromRow($row);
            if (empty($batches)) {
                // Strict mode: the frontend prevents this state for tracked products.
                continue;
            }

            $this->applyQuotationBatchesToDetail($detail, $batches);
        }
    }

    public function clearForQuotationDetails($quotationDetails): void
    {
        if (! $this->isSupported() || ! Schema::hasTable('quotation_detail_batches')) {
            return;
        }

        $detailIds = collect($quotationDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return;
        }

        // Quotations don't debit ProductBatch.qty, so deletion alone is enough.
        QuotationDetailBatch::whereIn('quotation_detail_id', $detailIds)->delete();
    }

    public function batchesForQuotationDetails($quotationDetails): array
    {
        if (! $this->isSupported() || ! Schema::hasTable('quotation_detail_batches')) {
            return [];
        }

        $detailIds = collect($quotationDetails)->pluck('id')->filter()->all();
        if (empty($detailIds)) {
            return [];
        }

        $rows = QuotationDetailBatch::with('batch')
            ->whereIn('quotation_detail_id', $detailIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->batch) {
                continue;
            }
            $out[(int) $r->quotation_detail_id][] = [
                'product_batch_id' => (int) $r->product_batch_id,
                'batch_no' => (string) $r->batch->batch_no,
                'expiry_date' => $r->batch->expiry_date ? $r->batch->expiry_date->format('Y-m-d') : null,
                'mfg_date' => $r->batch->mfg_date ? $r->batch->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $r->qty,
                'unit_cost' => $r->unit_cost !== null ? (float) $r->unit_cost : null,
                'status' => (string) $r->batch->status,
            ];
        }

        return $out;
    }

    protected function extractQuotationBatchesFromRow(array $row): array
    {
        $raw = $row['batches'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $b) {
            if (! is_array($b)) {
                continue;
            }
            $batchId = isset($b['product_batch_id']) ? (int) $b['product_batch_id'] : 0;
            $qty = isset($b['qty']) ? (float) $b['qty'] : 0;
            if ($batchId <= 0 || $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_batch_id' => $batchId,
                'qty' => $qty,
                'unit_cost' => isset($b['unit_cost']) ? (float) $b['unit_cost'] : null,
            ];
        }
        return $clean;
    }

    protected function applyQuotationBatchesToDetail(QuotationDetail $detail, array $batches): void
    {
        foreach ($batches as $b) {
            $batch = ProductBatch::find($b['product_batch_id']);
            if (! $batch) {
                continue;
            }
            $take = (float) $b['qty'];
            if ($take <= 0) {
                continue;
            }

            // No stock debit — quotations are proposals, not movements.
            QuotationDetailBatch::create([
                'quotation_detail_id' => $detail->id,
                'product_batch_id' => $batch->id,
                'qty' => $take,
                'unit_cost' => $b['unit_cost'] ?? ($batch->unit_cost !== null ? (float) $batch->unit_cost : null),
            ]);
        }
    }
}
