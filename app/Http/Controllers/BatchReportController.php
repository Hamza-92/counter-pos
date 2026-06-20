<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use App\Models\PurchaseDetailBatch;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\Setting;
use App\Services\BatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacy: dedicated reports over the per-batch ledger.
 *
 *  - register: cross-batch list + supplier/PO context (Report 1)
 *  - history:  unified movement log + reconciliation totals for a single batch (Report 2)
 *
 * Permission: any of `view_batches`, `batch_view`, `expiry_report`.
 */
class BatchReportController extends BaseController
{
    /**
     * GET /reports/batches/register
     *
     * Filters: page, limit, search, warehouse_id, supplier_id, status,
     *          expiry_window (expired|near|valid|expired_or_near|all),
     *          purchase_date_from, purchase_date_to, SortField, SortType.
     */
    public function register(Request $request)
    {
        $emptyResponse = [
            'batches' => [],
            'totalRows' => 0,
            'warehouses' => [],
            'suppliers' => [],
            'expiry_warning_days' => 90,
            'supported' => false,
        ];

        if (! Schema::hasTable('product_batches') || ! Schema::hasTable('purchase_detail_batches')) {
            return response()->json($emptyResponse);
        }

        $user = $request->user('api');
        if (! $user) {
            abort(401);
        }
        $this->ensurePermission($user);

        $perPage = (int) ($request->input('limit', 25));
        $perPage = $perPage === -1 ? -1 : min(max($perPage, 1), 500);
        $page = max(1, (int) ($request->input('page', 1)));

        $sortField = (string) $request->input('SortField', 'expiry_date');
        $allowedSorts = ['expiry_date', 'batch_no', 'qty', 'status', 'id', 'created_at'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'expiry_date';
        }
        $sortType = strtolower((string) $request->input('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';

        $search = trim((string) $request->input('search', ''));
        $warehouseId = $request->input('warehouse_id');
        $supplierId = $request->input('supplier_id');
        $status = $request->input('status');
        $window = (string) $request->input('expiry_window', 'all');
        $purchaseFrom = $request->input('purchase_date_from');
        $purchaseTo = $request->input('purchase_date_to');

        $warningDays = (int) (Setting::value('expiry_warning_days') ?? 90);
        $today = Carbon::today()->toDateString();
        $nearEnd = Carbon::today()->addDays(max(0, $warningDays))->toDateString();

        $accessibleWarehouseIds = null;
        if (! $user->is_all_warehouses) {
            $accessibleWarehouseIds = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->all();
        }

        // Pull originating purchase via the *first* purchase_detail_batch row for each
        // ProductBatch. This is the cleanest "register" view; the per-batch history
        // page surfaces the full list of purchase receipts when one batch was topped up.
        $originatingSubquery = PurchaseDetailBatch::query()
            ->selectRaw('MIN(id) as id, product_batch_id')
            ->groupBy('product_batch_id');

        $base = ProductBatch::query()
            ->select([
                'product_batches.*',
                \DB::raw('purchases.id as origin_purchase_id'),
                \DB::raw('purchases.Ref as origin_purchase_ref'),
                \DB::raw('purchases.date as origin_purchase_date'),
                \DB::raw('pdb.unit_cost as origin_unit_cost'),
                \DB::raw('providers.id as origin_supplier_id'),
                \DB::raw('providers.name as origin_supplier_name'),
            ])
            ->with([
                'product:id,name,code,generic_name,strength,dosage_form',
                'variant:id,name,code',
                'warehouse:id,name',
            ])
            ->leftJoinSub($originatingSubquery, 'origin_pdb', function ($j) {
                $j->on('origin_pdb.product_batch_id', '=', 'product_batches.id');
            })
            ->leftJoin('purchase_detail_batches as pdb', 'pdb.id', '=', 'origin_pdb.id')
            ->leftJoin('purchase_details as pd', 'pd.id', '=', 'pdb.purchase_detail_id')
            ->leftJoin('purchases', 'purchases.id', '=', 'pd.purchase_id')
            ->leftJoin('providers', 'providers.id', '=', 'purchases.provider_id')
            ->whereNull('product_batches.deleted_at');

        if ($accessibleWarehouseIds !== null) {
            $base->whereIn('product_batches.warehouse_id', $accessibleWarehouseIds);
        }
        if (! empty($warehouseId)) {
            $base->where('product_batches.warehouse_id', (int) $warehouseId);
        }
        if (! empty($supplierId)) {
            $base->where('providers.id', (int) $supplierId);
        }
        if (! empty($status) && $status !== 'all') {
            $base->where('product_batches.status', (string) $status);
        }
        if ($window === 'expired') {
            $base->whereNotNull('product_batches.expiry_date')
                ->whereDate('product_batches.expiry_date', '<', $today);
        } elseif ($window === 'near') {
            $base->whereNotNull('product_batches.expiry_date')
                ->whereDate('product_batches.expiry_date', '>=', $today)
                ->whereDate('product_batches.expiry_date', '<=', $nearEnd);
        } elseif ($window === 'valid') {
            $base->where(function ($q) use ($nearEnd) {
                $q->whereNull('product_batches.expiry_date')
                    ->orWhereDate('product_batches.expiry_date', '>', $nearEnd);
            });
        } elseif ($window === 'expired_or_near') {
            $base->whereNotNull('product_batches.expiry_date')
                ->whereDate('product_batches.expiry_date', '<=', $nearEnd);
        }
        if (! empty($purchaseFrom)) {
            $base->whereDate('purchases.date', '>=', $purchaseFrom);
        }
        if (! empty($purchaseTo)) {
            $base->whereDate('purchases.date', '<=', $purchaseTo);
        }
        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function ($q) use ($like) {
                $q->where('product_batches.batch_no', 'like', $like)
                    ->orWhereHas('product', function ($pq) use ($like) {
                        $pq->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like)
                            ->orWhere('generic_name', 'like', $like);
                    })
                    ->orWhere('purchases.Ref', 'like', $like)
                    ->orWhere('providers.name', 'like', $like);
            });
        }

        $total = (clone $base)->count('product_batches.id');

        $q = (clone $base)
            ->orderBy('product_batches.'.$sortField, $sortType)
            ->orderBy('product_batches.id', 'asc');
        if ($perPage !== -1) {
            $q->offset(($page - 1) * $perPage)->limit($perPage);
        }
        $rows = $q->get();

        $batches = $rows->map(function ($b) use ($warningDays, $today) {
            $expiry = $b->expiry_date ? Carbon::parse($b->expiry_date)->format('Y-m-d') : null;
            $daysToExpiry = $expiry ? Carbon::parse($expiry)->diffInDays($today, false) * -1 : null;
            $bucket = 'valid';
            if ($expiry !== null) {
                if ($daysToExpiry < 0) {
                    $bucket = 'expired';
                } elseif ($daysToExpiry <= $warningDays) {
                    $bucket = 'near';
                }
            }

            $unitCost = $b->origin_unit_cost !== null ? (float) $b->origin_unit_cost : null;
            $value = $unitCost !== null ? (float) $b->qty * $unitCost : null;

            return [
                'id' => (int) $b->id,
                'batch_no' => (string) $b->batch_no,
                'product_id' => (int) $b->product_id,
                'product_name' => optional($b->product)->name ?? '',
                'product_code' => optional($b->product)->code ?? '',
                'generic_name' => optional($b->product)->generic_name ?? '',
                'strength' => optional($b->product)->strength ?? '',
                'dosage_form' => optional($b->product)->dosage_form ?? '',
                'variant_name' => optional($b->variant)->name ?? '',
                'warehouse_id' => (int) $b->warehouse_id,
                'warehouse_name' => optional($b->warehouse)->name ?? '',
                'expiry_date' => $expiry,
                'mfg_date' => $b->mfg_date ? Carbon::parse($b->mfg_date)->format('Y-m-d') : null,
                'qty' => (float) $b->qty,
                'unit_cost' => $unitCost,
                'value' => $value,
                'status' => (string) $b->status,
                'days_to_expiry' => $daysToExpiry,
                'expiry_bucket' => $bucket,
                // Originating-purchase context.
                'origin_purchase_id' => $b->origin_purchase_id ? (int) $b->origin_purchase_id : null,
                'origin_purchase_ref' => $b->origin_purchase_ref,
                'origin_purchase_date' => $b->origin_purchase_date
                    ? Carbon::parse($b->origin_purchase_date)->format('Y-m-d')
                    : null,
                'origin_supplier_id' => $b->origin_supplier_id ? (int) $b->origin_supplier_id : null,
                'origin_supplier_name' => $b->origin_supplier_name,
            ];
        })->values();

        $warehouses = $accessibleWarehouseIds === null
            ? Warehouse::whereNull('deleted_at')->get(['id', 'name'])
            : Warehouse::whereNull('deleted_at')->whereIn('id', $accessibleWarehouseIds)->get(['id', 'name']);

        // Suppliers list — limited to the suppliers actually present on batches the user can see.
        $suppliers = collect();
        if (Schema::hasTable('providers')) {
            $supQuery = \App\Models\Provider::query()
                ->select('providers.id', 'providers.name')
                ->whereNull('providers.deleted_at')
                ->whereExists(function ($sub) use ($accessibleWarehouseIds) {
                    $sub->select(\DB::raw(1))
                        ->from('purchase_detail_batches as pdb2')
                        ->join('purchase_details as pd2', 'pd2.id', '=', 'pdb2.purchase_detail_id')
                        ->join('purchases as p2', 'p2.id', '=', 'pd2.purchase_id')
                        ->join('product_batches as pb2', 'pb2.id', '=', 'pdb2.product_batch_id')
                        ->whereNull('pb2.deleted_at')
                        ->whereColumn('p2.provider_id', 'providers.id');
                    if ($accessibleWarehouseIds !== null) {
                        $sub->whereIn('pb2.warehouse_id', $accessibleWarehouseIds);
                    }
                })
                ->orderBy('providers.name');
            $suppliers = $supQuery->get();
        }

        return response()->json([
            'batches' => $batches,
            'totalRows' => $total,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'expiry_warning_days' => $warningDays,
            'supported' => true,
        ]);
    }

    /**
     * GET /reports/batches/{id}/history
     *
     * Returns the unified movement log + reconciliation totals.
     * Also includes the batch header so the frontend can render without a second call.
     */
    public function history(Request $request, $id)
    {
        if (! Schema::hasTable('product_batches')) {
            return response()->json(['supported' => false], 200);
        }

        $user = $request->user('api');
        if (! $user) {
            abort(401);
        }
        $this->ensurePermission($user);

        $batch = ProductBatch::with([
            'product:id,name,code,generic_name,strength,dosage_form,is_batch_tracked',
            'variant:id,name,code',
            'warehouse:id,name',
        ])
            ->whereNull('deleted_at')
            ->findOrFail($id);

        // Warehouse-restriction guard.
        if (! $user->is_all_warehouses) {
            $accessible = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->all();
            if (! in_array($batch->warehouse_id, $accessible, true)) {
                abort(403, 'Warehouse restriction.');
            }
        }

        $today = Carbon::today()->toDateString();
        $warningDays = (int) (Setting::value('expiry_warning_days') ?? 90);
        $expiry = $batch->expiry_date ? Carbon::parse($batch->expiry_date)->format('Y-m-d') : null;
        $daysToExpiry = $expiry ? Carbon::parse($expiry)->diffInDays($today, false) * -1 : null;
        $bucket = 'valid';
        if ($expiry !== null) {
            if ($daysToExpiry < 0) {
                $bucket = 'expired';
            } elseif ($daysToExpiry <= $warningDays) {
                $bucket = 'near';
            }
        }

        $batchPayload = [
            'id' => (int) $batch->id,
            'batch_no' => (string) $batch->batch_no,
            'product_id' => (int) $batch->product_id,
            'product_name' => optional($batch->product)->name ?? '',
            'product_code' => optional($batch->product)->code ?? '',
            'generic_name' => optional($batch->product)->generic_name ?? '',
            'strength' => optional($batch->product)->strength ?? '',
            'dosage_form' => optional($batch->product)->dosage_form ?? '',
            'variant_name' => optional($batch->variant)->name ?? '',
            'warehouse_id' => (int) $batch->warehouse_id,
            'warehouse_name' => optional($batch->warehouse)->name ?? '',
            'expiry_date' => $expiry,
            'mfg_date' => $batch->mfg_date ? Carbon::parse($batch->mfg_date)->format('Y-m-d') : null,
            'qty' => (float) $batch->qty,
            'status' => (string) $batch->status,
            'days_to_expiry' => $daysToExpiry,
            'expiry_bucket' => $bucket,
            'notes' => (string) ($batch->notes ?? ''),
        ];

        $payload = app(BatchService::class)->transactionsForBatch((int) $batch->id);
        $payload['batch'] = $batchPayload;

        return response()->json($payload);
    }

    /**
     * Permission gate — accept any of the existing pharmacy/batch permissions.
     */
    protected function ensurePermission($user): void
    {
        $perms = $user->roles()->with('permissions:id,name')->get()
            ->flatMap(function ($r) {
                return $r->permissions->pluck('name');
            })
            ->unique()
            ->values()
            ->all();

        $allowed = in_array('Batch_Register_Report', $perms, true)
            || in_array('view_batches', $perms, true)
            || in_array('batch_view', $perms, true)
            || in_array('expiry_report', $perms, true);

        if (! $allowed) {
            abort(403, 'Permission denied: batch reports require Batch_Register_Report.');
        }
    }
}
