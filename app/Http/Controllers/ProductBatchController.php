<?php

namespace App\Http\Controllers;

use App\Models\product_warehouse;
use App\Models\ProductBatch;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProductBatchController
 *
 * Pharmacy-mode batch & expiry CRUD.
 * Endpoints are no-ops (return 404) if the pharmacy migration hasn't been run.
 */
class ProductBatchController extends BaseController
{
    /**
     * GET /product_batches
     *
     * Filters: page, perPage/limit, search (batch_no / product name / generic_name),
     *          warehouse_id, status, expiry_window (expired|near|valid|all), product_id.
     */
    public function index(Request $request)
    {
        if (! $this->supported()) {
            return response()->json(['batches' => [], 'totalRows' => 0, 'warehouses' => []]);
        }

        $this->ensurePermission('view_batches');

        $perPage = (int) ($request->input('limit', 10));
        $perPage = $perPage > 0 ? min($perPage, 200) : 10;
        $page = (int) ($request->input('page', 1));
        $search = trim((string) $request->input('search', ''));
        $sortField = (string) $request->input('SortField', 'expiry_date');
        $sortType = strtolower((string) $request->input('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'batch_no', 'expiry_date', 'mfg_date', 'qty', 'status', 'created_at'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'expiry_date';
        }

        $accessibleWarehouseIds = $this->accessibleWarehouseIds();

        $q = ProductBatch::query()
            ->with(['product:id,name,code,generic_name,strength,dosage_form', 'variant:id,name,code', 'warehouse:id,name'])
            ->whereNull('product_batches.deleted_at');

        if ($accessibleWarehouseIds !== null) {
            $q->whereIn('warehouse_id', $accessibleWarehouseIds);
        }

        if ($request->filled('warehouse_id')) {
            $q->where('warehouse_id', (int) $request->warehouse_id);
        }
        if ($request->filled('product_id')) {
            $q->where('product_id', (int) $request->product_id);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $q->where('status', (string) $request->status);
        }

        $window = (string) $request->input('expiry_window', 'all');
        $today = Carbon::today()->toDateString();
        $warningDays = (int) (\App\Models\Setting::value('expiry_warning_days') ?? 90);
        if ($window === 'expired') {
            $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today);
        } elseif ($window === 'near') {
            $end = Carbon::today()->addDays(max(0, $warningDays))->toDateString();
            $q->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $end);
        } elseif ($window === 'valid') {
            $end = Carbon::today()->addDays(max(0, $warningDays))->toDateString();
            $q->where(function ($qq) use ($end) {
                $qq->whereNull('expiry_date')->orWhereDate('expiry_date', '>', $end);
            });
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($qq) use ($like) {
                $qq->where('batch_no', 'like', $like)
                    ->orWhereHas('product', function ($pq) use ($like) {
                        $pq->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like)
                            ->orWhere('generic_name', 'like', $like);
                    });
            });
        }

        $total = (clone $q)->count();
        $rows = $q->orderBy($sortField, $sortType)
            ->orderBy('id', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $batches = $rows->map(function ($b) use ($warningDays, $today) {
            $expiry = $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null;
            $daysToExpiry = $expiry ? Carbon::parse($expiry)->diffInDays($today, false) * -1 : null;
            $bucket = 'valid';
            if ($expiry) {
                if ($daysToExpiry < 0) {
                    $bucket = 'expired';
                } elseif ($daysToExpiry <= $warningDays) {
                    $bucket = 'near';
                }
            }

            return [
                'id' => (int) $b->id,
                'batch_no' => (string) $b->batch_no,
                'product_id' => (int) $b->product_id,
                'product_name' => $b->product->name ?? '',
                'product_code' => $b->product->code ?? '',
                'generic_name' => $b->product->generic_name ?? '',
                'strength' => $b->product->strength ?? '',
                'dosage_form' => $b->product->dosage_form ?? '',
                'variant_name' => $b->variant->name ?? '',
                'warehouse_id' => (int) $b->warehouse_id,
                'warehouse_name' => $b->warehouse->name ?? '',
                'expiry_date' => $expiry,
                'mfg_date' => $b->mfg_date ? $b->mfg_date->format('Y-m-d') : null,
                'qty' => (float) $b->qty,
                'unit_cost' => $b->unit_cost !== null ? (float) $b->unit_cost : null,
                'status' => (string) $b->status,
                'days_to_expiry' => $daysToExpiry,
                'expiry_bucket' => $bucket,
                'notes' => (string) ($b->notes ?? ''),
            ];
        })->values();

        $warehouses = $accessibleWarehouseIds === null
            ? Warehouse::whereNull('deleted_at')->get(['id', 'name'])
            : Warehouse::whereNull('deleted_at')->whereIn('id', $accessibleWarehouseIds)->get(['id', 'name']);

        return response()->json([
            'batches' => $batches,
            'totalRows' => $total,
            'warehouses' => $warehouses,
            'expiry_warning_days' => $warningDays,
        ]);
    }

    /**
     * PUT /product_batches/{id}
     */
    public function update(Request $request, $id)
    {
        if (! $this->supported()) {
            abort(404);
        }
        $this->ensurePermission('manage_batches');

        $request->validate([
            'batch_no' => 'required|string|max:100',
            'expiry_date' => 'nullable|date',
            'mfg_date' => 'nullable|date',
            'qty' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,quarantined,expired,written_off',
            'notes' => 'nullable|string',
        ]);

        $batch = ProductBatch::findOrFail($id);
        $this->ensureWarehouseAccess((int) $batch->warehouse_id);

        $batch->batch_no = $request->batch_no;
        if ($request->has('expiry_date')) {
            $batch->expiry_date = $request->expiry_date ?: null;
        }
        if ($request->has('mfg_date')) {
            $batch->mfg_date = $request->mfg_date ?: null;
        }
        if ($request->filled('status')) {
            $batch->status = $request->status;
        }
        if ($request->has('unit_cost')) {
            $batch->unit_cost = $request->unit_cost !== null && $request->unit_cost !== '' ? (float) $request->unit_cost : null;
        }
        if ($request->has('notes')) {
            $batch->notes = $request->notes;
        }

        // qty edit applies a delta to product_warehouse so on-hand stays consistent.
        if ($request->has('qty')) {
            $newQty = (float) $request->qty;
            $delta = $newQty - (float) $batch->qty;
            if (abs($delta) > 0.0001) {
                $this->adjustWarehouseQty($batch, $delta);
            }
            $batch->qty = $newQty;
        }

        $batch->save();

        return response()->json(['success' => true, 'batch' => $batch]);
    }

    /**
     * POST /product_batches/{id}/writeoff
     *
     * Marks the batch written_off and decrements warehouse qty by the remaining batch qty.
     */
    public function writeOff(Request $request, $id)
    {
        if (! $this->supported()) {
            abort(404);
        }
        $this->ensurePermission('writeoff_batches');

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $batch = ProductBatch::findOrFail($id);
        $this->ensureWarehouseAccess((int) $batch->warehouse_id);

        if ($batch->status === 'written_off') {
            return response()->json(['success' => true, 'message' => 'Already written off']);
        }

        DB::transaction(function () use ($batch, $request) {
            if ($batch->qty > 0) {
                $this->adjustWarehouseQty($batch, -1 * (float) $batch->qty);
            }
            $batch->qty = 0;
            $batch->status = 'written_off';
            $existingNotes = trim((string) ($batch->notes ?? ''));
            $reason = trim((string) $request->input('reason', ''));
            if ($reason !== '') {
                $batch->notes = $existingNotes === ''
                    ? '[Write-off] '.$reason
                    : $existingNotes."\n[Write-off] ".$reason;
            }
            $batch->save();
        });

        return response()->json(['success' => true, 'message' => 'Batch written off']);
    }

    /**
     * DELETE /product_batches/{id}  (soft delete; warehouse qty is decremented).
     */
    public function destroy(Request $request, $id)
    {
        if (! $this->supported()) {
            abort(404);
        }
        $this->ensurePermission('writeoff_batches');

        $batch = ProductBatch::findOrFail($id);
        $this->ensureWarehouseAccess((int) $batch->warehouse_id);

        DB::transaction(function () use ($batch) {
            if ($batch->qty > 0) {
                $this->adjustWarehouseQty($batch, -1 * (float) $batch->qty);
            }
            $batch->qty = 0;
            $batch->status = 'written_off';
            $batch->save();
            $batch->delete();
        });

        return response()->json(['success' => true]);
    }

    // --------------------------------------------------------------------- helpers

    protected function supported(): bool
    {
        return Schema::hasTable('product_batches')
            && Schema::hasColumn('products', 'is_batch_tracked');
    }

    /**
     * Returns the warehouse IDs the current user can access, or null if all warehouses.
     */
    protected function accessibleWarehouseIds(): ?array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }
        if ($user->is_all_warehouses) {
            return null;
        }

        return UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->all();
    }

    protected function ensureWarehouseAccess(int $warehouseId): void
    {
        $ids = $this->accessibleWarehouseIds();
        if ($ids === null) {
            return;
        }
        if (! in_array($warehouseId, $ids, true)) {
            abort(403, 'Warehouse access denied');
        }
    }

    /**
     * Apply a +/- delta to product_warehouse.qte for this batch's product/variant/warehouse.
     */
    protected function adjustWarehouseQty(ProductBatch $batch, float $delta): void
    {
        $q = product_warehouse::whereNull('deleted_at')
            ->where('warehouse_id', $batch->warehouse_id)
            ->where('product_id', $batch->product_id);
        if ($batch->product_variant_id) {
            $q->where('product_variant_id', $batch->product_variant_id);
        } else {
            $q->whereNull('product_variant_id');
        }
        $row = $q->first();
        if ($row) {
            $row->qte = (float) $row->qte + $delta;
            $row->save();
        }
    }

    /**
     * Permission check that maps to the project's PermissionsSeeder names.
     * Resolves the user's permissions through their assigned roles.
     */
    protected function ensurePermission(string $perm): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }
        $perms = $user->roles()->with('permissions:id,name')->get()
            ->flatMap(function ($r) {
                return $r->permissions->pluck('name');
            })
            ->unique()
            ->values()
            ->all();
        $allowed = in_array($perm, $perms, true)
            || (in_array('batch_view', $perms, true) && $perm === 'view_batches')
            || (in_array('batch_manage', $perms, true) && $perm === 'manage_batches')
            || (in_array('batch_writeoff', $perms, true) && $perm === 'writeoff_batches');
        if (! $allowed) {
            abort(403, 'Permission denied: '.$perm);
        }
    }
}
