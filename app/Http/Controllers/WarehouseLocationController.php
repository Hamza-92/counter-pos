<?php

namespace App\Http\Controllers;

use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseLocationController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WarehouseLocation::class);

        $perPage = $request->integer('limit', 10);
        $pageStart = (int) ($request->get('page', 1));
        $offSet = ($pageStart * max($perPage, 1)) - max($perPage, 1);
        $order = $request->get('SortField', 'id');
        $dir = $request->get('SortType', 'desc');
        $search = $request->get('search', '');

        // Assigned warehouses (user scope)
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $allowedWarehouseIds = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
        } else {
            $allowedWarehouseIds = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $allowedWarehouseIds)->get(['id', 'name']);
        }

        $warehouseId = $request->integer('warehouse_id');
        if ($warehouseId && ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            $warehouseId = null;
        }

        $query = WarehouseLocation::query()
            ->whereNull('deleted_at')
            ->whereIn('warehouse_id', $allowedWarehouseIds);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        $totalRows = (clone $query)->count();
        if ((string) $perPage === '-1') {
            $perPage = $totalRows;
            $offSet = 0;
        }

        $locations = $query
            ->with(['warehouse:id,name'])
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get()
            ->map(function (WarehouseLocation $loc) {
                return [
                    'id' => $loc->id,
                    'warehouse_id' => $loc->warehouse_id,
                    'warehouse' => $loc->warehouse ? $loc->warehouse->name : null,
                    'code' => $loc->code,
                    'name' => $loc->name,
                    'is_active' => (bool) $loc->is_active,
                ];
            });

        return response()->json([
            'locations' => $locations,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', WarehouseLocation::class);

        $allowedWarehouseIds = $this->allowedWarehouseIds();

        $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::in($allowedWarehouseIds)],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('warehouse_locations')->where(function ($q) use ($request) {
                    return $q->whereNull('deleted_at')
                        ->where('warehouse_id', $request->warehouse_id);
                }),
            ],
            'name' => ['nullable', 'string', 'max:192'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $loc = WarehouseLocation::create([
            'warehouse_id' => $request->warehouse_id,
            'code' => trim($request->code),
            'name' => $request->name ? trim($request->name) : null,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'location' => $loc,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', WarehouseLocation::class);

        $loc = WarehouseLocation::whereNull('deleted_at')->findOrFail($id);

        $allowedWarehouseIds = $this->allowedWarehouseIds();

        $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::in($allowedWarehouseIds)],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('warehouse_locations')->ignore($loc->id)->where(function ($q) use ($request) {
                    return $q->whereNull('deleted_at')
                        ->where('warehouse_id', $request->warehouse_id);
                }),
            ],
            'name' => ['nullable', 'string', 'max:192'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $loc->warehouse_id = $request->warehouse_id;
        $loc->code = trim($request->code);
        $loc->name = $request->name ? trim($request->name) : null;
        $loc->is_active = $request->has('is_active') ? (bool) $request->is_active : (bool) $loc->is_active;
        $loc->save();

        return response()->json([
            'success' => true,
            'location' => $loc,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', WarehouseLocation::class);

        $loc = WarehouseLocation::whereNull('deleted_at')->findOrFail($id);
        $loc->deleted_at = Carbon::now();
        $loc->save();

        return response()->json(['success' => true]);
    }

    public function by_warehouse(Request $request, $warehouseId)
    {
        $this->authorizeForUser($request->user('api'), 'view', WarehouseLocation::class);

        $allowedWarehouseIds = $this->allowedWarehouseIds();
        if (! in_array((int) $warehouseId, $allowedWarehouseIds, true)) {
            return response()->json([]);
        }

        $rows = WarehouseLocation::whereNull('deleted_at')
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'warehouse_id', 'code', 'name']);

        return response()->json($rows);
    }

    private function allowedWarehouseIds(): array
    {
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            return Warehouse::whereNull('deleted_at')->pluck('id')->toArray();
        }

        return UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
    }
}

