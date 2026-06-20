<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Sales3DDashboardController extends Controller
{
    private const TOP_N_PRODUCTS = 12;
    private const TOP_N_CLIENTS = 10;
    private const CACHE_TTL_SECONDS = 60;

    public function data(Request $request)
    {
        $user = Auth::user();

        if ($user->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
            $allowed_warehouse_ids = $warehouses->pluck('id')->toArray();
        } else {
            $allowed_warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')
                ->whereIn('id', $allowed_warehouse_ids)
                ->get(['id', 'name']);
        }

        $warehouse_id = (int) $request->input('warehouse_id', 0);
        if ($warehouse_id !== 0 && ! in_array($warehouse_id, $allowed_warehouse_ids, true)) {
            $warehouse_id = 0;
        }

        [$start, $end] = $this->resolveDateRange($request->input('from'), $request->input('to'));

        $cacheKey = sprintf(
            'sales3d:%d:%s:%s:%d:%s',
            $user->id,
            $start->toDateString(),
            $end->toDateString(),
            $warehouse_id,
            md5(implode(',', $allowed_warehouse_ids))
        );

        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($warehouse_id, $allowed_warehouse_ids, $start, $end) {
            return [
                'sales_by_month_warehouse' => $this->salesByMonthWarehouse($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'top_products_by_month' => $this->topProductsByMonth($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'product_scatter' => $this->productScatter($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'payment_methods' => $this->paymentMethodsBreakdown($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'hour_dow_heatmap' => $this->hourDayOfWeekHeatmap($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'top_clients' => $this->topClients($warehouse_id, $allowed_warehouse_ids, $start, $end),
                'kpis' => $this->kpis($warehouse_id, $allowed_warehouse_ids, $start, $end),
            ];
        });

        return response()->json(array_merge($payload, [
            'warehouses' => $warehouses,
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ]));
    }

    private function resolveDateRange(?string $from, ?string $to): array
    {
        if (! empty($from) && ! empty($to)) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        }

        $end = Carbon::today()->endOfDay();
        $start = $end->copy()->subDays(29)->startOfDay();

        return [$start, $end];
    }

    private function applyWarehouseScope($query, int $warehouse_id, array $allowed_warehouse_ids, string $column = 'warehouse_id')
    {
        if ($warehouse_id !== 0) {
            return $query->where($column, $warehouse_id);
        }

        if (! empty($allowed_warehouse_ids)) {
            return $query->whereIn($column, $allowed_warehouse_ids);
        }

        return $query->whereRaw('1 = 0');
    }

    private function salesByMonthWarehouse(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $rows = $this->applyWarehouseScope(
            Sale::query()
                ->whereNull('deleted_at')
                ->whereBetween('date', [$start, $end])
                ->where('statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids
        )
            ->select(
                DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
                'warehouse_id',
                DB::raw('SUM(GrandTotal) as total')
            )
            ->groupBy('month', 'warehouse_id')
            ->orderBy('month')
            ->get();

        $warehouses = Warehouse::whereIn('id', $rows->pluck('warehouse_id')->unique())
            ->pluck('name', 'id');

        $months = $rows->pluck('month')->unique()->values();
        $warehouseIds = $rows->pluck('warehouse_id')->unique()->values();

        $matrix = [];
        foreach ($rows as $row) {
            $x = $months->search($row->month);
            $y = $warehouseIds->search($row->warehouse_id);
            $matrix[] = [$x, $y, round((float) $row->total, 2)];
        }

        return [
            'months' => $months->values(),
            'warehouses' => $warehouseIds->map(fn($id) => $warehouses[$id] ?? ('#' . $id))->values(),
            'data' => $matrix,
        ];
    }

    private function topProductsByMonth(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $saleIdsQuery = $this->applyWarehouseScope(
            Sale::query()
                ->whereNull('deleted_at')
                ->whereBetween('date', [$start, $end])
                ->where('statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids
        )->select('id');

        $topProductIds = SaleDetail::whereIn('sale_id', $saleIdsQuery)
            ->select('product_id', DB::raw('SUM(total) as revenue'))
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(self::TOP_N_PRODUCTS)
            ->pluck('product_id');

        if ($topProductIds->isEmpty()) {
            return ['months' => [], 'products' => [], 'data' => []];
        }

        $rows = SaleDetail::query()
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.date', [$start, $end])
            ->where('sales.statut', 'completed')
            ->when($warehouse_id !== 0, fn($q) => $q->where('sales.warehouse_id', $warehouse_id))
            ->when($warehouse_id === 0 && ! empty($allowed_warehouse_ids), fn($q) => $q->whereIn('sales.warehouse_id', $allowed_warehouse_ids))
            ->whereIn('sale_details.product_id', $topProductIds)
            ->select(
                DB::raw("DATE_FORMAT(sales.date, '%Y-%m') as month"),
                'sale_details.product_id',
                'products.name as product_name',
                DB::raw('SUM(sale_details.total) as revenue')
            )
            ->groupBy('month', 'sale_details.product_id', 'products.name')
            ->orderBy('month')
            ->get();

        $months = $rows->pluck('month')->unique()->values();
        $products = $rows->groupBy('product_id')->map(fn($g) => $g->first()->product_name)->values();
        $productIds = $rows->pluck('product_id')->unique()->values();

        $matrix = [];
        foreach ($rows as $row) {
            $x = $months->search($row->month);
            $y = $productIds->search($row->product_id);
            $matrix[] = [$x, $y, round((float) $row->revenue, 2)];
        }

        return [
            'months' => $months->values(),
            'products' => $products,
            'data' => $matrix,
        ];
    }

    private function productScatter(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $rows = SaleDetail::query()
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.date', [$start, $end])
            ->where('sales.statut', 'completed')
            ->when($warehouse_id !== 0, fn($q) => $q->where('sales.warehouse_id', $warehouse_id))
            ->when($warehouse_id === 0 && ! empty($allowed_warehouse_ids), fn($q) => $q->whereIn('sales.warehouse_id', $allowed_warehouse_ids))
            ->select(
                'sale_details.product_id',
                'products.name as product_name',
                DB::raw('SUM(sale_details.quantity) as quantity'),
                DB::raw('AVG(sale_details.price) as avg_price'),
                DB::raw('SUM(sale_details.total) as revenue')
            )
            ->groupBy('sale_details.product_id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        return $rows->map(fn($r) => [
            (float) $r->quantity,
            round((float) $r->avg_price, 2),
            round((float) $r->revenue, 2),
            $r->product_name,
        ])->values()->all();
    }

    private function paymentMethodsBreakdown(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $saleIdsQuery = $this->applyWarehouseScope(
            Sale::query()
                ->whereNull('deleted_at')
                ->whereBetween('date', [$start, $end])
                ->where('statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids
        )->select('id');

        $rows = DB::table('payment_sales')
            ->leftJoin('payment_methods', 'payment_sales.payment_method_id', '=', 'payment_methods.id')
            ->whereIn('payment_sales.sale_id', $saleIdsQuery)
            ->whereNull('payment_sales.deleted_at')
            ->select(
                DB::raw("COALESCE(payment_methods.name, 'Other') as method"),
                DB::raw('SUM(payment_sales.montant) as amount')
            )
            ->groupBy('payment_methods.name')
            ->get();

        return $rows->map(fn($r) => [
            'name' => $r->method ?: 'Other',
            'value' => round((float) $r->amount, 2),
        ])->values()->all();
    }

    private function hourDayOfWeekHeatmap(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $rows = $this->applyWarehouseScope(
            Sale::query()
                ->whereNull('deleted_at')
                ->whereBetween('date', [$start, $end])
                ->where('statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids
        )
            ->select(
                DB::raw('DAYOFWEEK(date) as dow'),
                DB::raw('HOUR(COALESCE(time, date)) as hour'),
                DB::raw('SUM(GrandTotal) as total')
            )
            ->groupBy('dow', 'hour')
            ->get();

        return $rows->map(fn($r) => [
            (int) $r->hour,
            ((int) $r->dow) - 1,
            round((float) $r->total, 2),
        ])->values()->all();
    }

    private function topClients(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $rows = $this->applyWarehouseScope(
            Sale::query()
                ->join('clients', 'sales.client_id', '=', 'clients.id')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sales.date', [$start, $end])
                ->where('sales.statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids,
            'sales.warehouse_id'
        )
            ->select(
                'clients.name as client_name',
                DB::raw('SUM(sales.GrandTotal) as total'),
                DB::raw('COUNT(sales.id) as orders')
            )
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total')
            ->limit(self::TOP_N_CLIENTS)
            ->get();

        return $rows->map(fn($r) => [
            'name' => $r->client_name,
            'value' => round((float) $r->total, 2),
            'orders' => (int) $r->orders,
        ])->values()->all();
    }

    private function kpis(int $warehouse_id, array $allowed_warehouse_ids, Carbon $start, Carbon $end): array
    {
        $row = $this->applyWarehouseScope(
            Sale::query()
                ->whereNull('deleted_at')
                ->whereBetween('date', [$start, $end])
                ->where('statut', 'completed'),
            $warehouse_id,
            $allowed_warehouse_ids
        )
            ->select(
                DB::raw('COUNT(id) as orders'),
                DB::raw('SUM(GrandTotal) as revenue'),
                DB::raw('AVG(GrandTotal) as avg_order'),
                DB::raw('COUNT(DISTINCT client_id) as customers')
            )
            ->first();

        return [
            'orders' => (int) ($row->orders ?? 0),
            'revenue' => round((float) ($row->revenue ?? 0), 2),
            'avg_order' => round((float) ($row->avg_order ?? 0), 2),
            'customers' => (int) ($row->customers ?? 0),
        ];
    }
}
