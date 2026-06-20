<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Models\Client;
use App\Models\PaymentSale;
use App\Traits\CalculatesCogsAndAverageCost;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReportQuestionService
{
    use CalculatesCogsAndAverageCost;

    /**
     * Execute daily sales summary report
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @param int|null $warehouseId
     * @return array
     */
    public function dailySalesSummary(string $dateFrom, string $dateTo, ?int $warehouseId = null): array
    {
        $user = Auth::user();
        $viewRecords = $user ? $user->hasRecordView() : false;

        // Get user's accessible warehouses
        $warehouseIds = $this->getUserWarehouseIds($user, $warehouseId);

        // Build query
        $query = Sale::whereNull('deleted_at')
            ->where('statut', 'completed')
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        if (!$viewRecords) {
            $query->where('user_id', $user->id);
        }

        // Get aggregates
        $sales = $query->get();

        $transactions = $sales->count();
        $revenue = $sales->sum('GrandTotal');
        $tax = $sales->sum('TaxNet');
        $discount = $sales->sum('discount');

        // Calculate profit using COGS
        $cogsPack = $this->calcCogsAndAvgCostFast($dateFrom, $dateTo, $warehouseId, $warehouseIds);
        $cogsFIFO = $cogsPack['fifo'] ?? 0.0;

        // Get expenses for the period
        $expenses = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($warehouseId, function ($q) use ($warehouseId) {
                return $q->where('warehouse_id', $warehouseId);
            }, function ($q) use ($warehouseIds) {
                return $q->whereIn('warehouse_id', $warehouseIds);
            })
            ->when(!$viewRecords, function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->sum('amount');

        $profit = $revenue - $cogsFIFO - $expenses;

        return [
            'transactions' => $transactions,
            'revenue' => (float) $revenue,
            'tax' => (float) $tax,
            'discount' => (float) $discount,
            'profit' => (float) $profit,
        ];
    }

    /**
     * Execute sales by product report
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @param int|null $warehouseId
     * @param array $filters
     * @return array
     */
    public function salesByProduct(string $dateFrom, string $dateTo, ?int $warehouseId = null, array $filters = []): array
    {
        $user = Auth::user();
        $viewRecords = $user ? $user->hasRecordView() : false;

        $warehouseIds = $this->getUserWarehouseIds($user, $warehouseId);
        $limit = $filters['limit'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'profit';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        // Build base query
        $query = SaleDetail::join('sales as s', 's.id', '=', 'sale_details.sale_id')
            ->join('products as p', 'p.id', '=', 'sale_details.product_id')
            ->whereNull('s.deleted_at')
            ->where('s.statut', 'completed')
            ->whereBetween('sale_details.date', [$dateFrom, $dateTo]);

        if ($warehouseId) {
            $query->where('s.warehouse_id', $warehouseId);
        } else {
            $query->whereIn('s.warehouse_id', $warehouseIds);
        }

        if (!$viewRecords) {
            $query->where('s.user_id', $user->id);
        }

        // Aggregate by product (COALESCE name so chart/table never get null)
        $results = $query
            ->select(
                'sale_details.product_id',
                DB::raw('COALESCE(NULLIF(TRIM(p.name), ""), CONCAT("Product #", sale_details.product_id)) as name'),
                DB::raw('SUM(sale_details.quantity) as qty'),
                DB::raw('SUM(sale_details.total) as revenue'),
                DB::raw('SUM(sale_details.quantity * p.cost) as cost')
            )
            ->groupBy('sale_details.product_id', 'p.name')
            ->get()
            ->map(function ($item) {
                $revenue = (float) $item->revenue;
                $cost = (float) $item->cost;
                $profit = $revenue - $cost;
                $marginPercent = $revenue > 0 ? (($profit / $revenue) * 100) : 0;
                $displayName = $item->name !== null && (string) $item->name !== '' ? (string) $item->name : 'Product #' . $item->product_id;

                return [
                    'product_id' => $item->product_id,
                    'name' => $displayName,
                    'qty' => (float) $item->qty,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin_percent' => round($marginPercent, 2),
                ];
            });

        // Sort
        $results = $results->sortBy(function ($item) use ($sortBy) {
            return $item[$sortBy];
        }, SORT_REGULAR, $sortDir === 'desc');

        // Limit
        return $results->take($limit)->values()->toArray();
    }

    /**
     * Execute late payments report
     *
     * @param array $filters
     * @return array
     */
    public function latePayments(array $filters = []): array
    {
        $minDaysOverdue = (int) ($filters['min_days_overdue'] ?? 30);
        $today = Carbon::today()->startOfDay();

        // Get sales with outstanding amounts (only invoice date on or before today)
        $sales = Sale::whereNull('deleted_at')
            ->where('statut', 'completed')
            ->whereRaw('(GrandTotal - paid_amount) > 0.01')
            ->whereDate('date', '<=', $today->toDateString())
            ->with('client')
            ->get();

        $customerData = [];

        foreach ($sales as $sale) {
            $dueAmount = $sale->GrandTotal - $sale->paid_amount;
            $saleDate = Carbon::parse($sale->date)->startOfDay();
            // Use absolute difference: past invoices => positive days overdue (we already filter date <= today in the query)
            $daysOverdue = (int) $today->diffInDays($saleDate, true);

            if ($daysOverdue >= $minDaysOverdue) {
                $customerId = $sale->client_id;
                $customerName = $sale->client ? $sale->client->name : 'Unknown';

                if (!isset($customerData[$customerId])) {
                    $customerData[$customerId] = [
                        'customer_id' => $customerId,
                        'name' => $customerName,
                        'invoices_count' => 0,
                        'outstanding_amount' => 0,
                        'max_days_overdue' => 0,
                    ];
                }

                $customerData[$customerId]['invoices_count']++;
                $customerData[$customerId]['outstanding_amount'] += $dueAmount;
                $customerData[$customerId]['max_days_overdue'] = max(
                    $customerData[$customerId]['max_days_overdue'],
                    $daysOverdue
                );
            }
        }

        // Sort by outstanding_amount descending
        usort($customerData, function ($a, $b) {
            return $b['outstanding_amount'] <=> $a['outstanding_amount'];
        });

        return array_values($customerData);
    }

    /**
     * Generate insights by comparing two periods
     *
     * @param array $currentData
     * @param array $compareData
     * @return string
     */
    public function generateInsights(array $currentData, array $compareData): string
    {
        // Placeholder for AI integration - simple comparison for now
        $currentProfit = $currentData['profit'] ?? 0;
        $compareProfit = $compareData['profit'] ?? 0;
        $profitDelta = $currentProfit - $compareProfit;
        $profitPercentChange = $compareProfit != 0 ? (($profitDelta / abs($compareProfit)) * 100) : 0;

        $currentRevenue = $currentData['revenue'] ?? 0;
        $compareRevenue = $compareData['revenue'] ?? 0;
        $revenueDelta = $currentRevenue - $compareRevenue;

        $currentDiscount = $currentData['discount'] ?? 0;
        $compareDiscount = $compareData['discount'] ?? 0;
        $discountDelta = $currentDiscount - $compareDiscount;

        $insights = [];
        
        if (abs($profitDelta) > 0.01) {
            $direction = $profitDelta > 0 ? 'increased' : 'decreased';
            $insights[] = sprintf(
                "Profit %s from %s to %s (change: %s, %.1f%%)",
                $direction,
                number_format($compareProfit, 2),
                number_format($currentProfit, 2),
                number_format($profitDelta, 2),
                abs($profitPercentChange)
            );
        }

        if (abs($revenueDelta) > 0.01) {
            $insights[] = sprintf(
                "Revenue changed by %s (from %s to %s)",
                number_format($revenueDelta, 2),
                number_format($compareRevenue, 2),
                number_format($currentRevenue, 2)
            );
        }

        if (abs($discountDelta) > 0.01) {
            $insights[] = sprintf(
                "Discount changed by %s (from %s to %s)",
                number_format($discountDelta, 2),
                number_format($compareDiscount, 2),
                number_format($currentDiscount, 2)
            );
        }

        if (empty($insights)) {
            return "No significant changes detected between periods.";
        }

        return implode('. ', $insights) . '.';
    }

    /**
     * Get user's accessible warehouse IDs
     *
     * @param mixed $user
     * @param int|null $warehouseId
     * @return array
     */
    private function getUserWarehouseIds($user, ?int $warehouseId = null): array
    {
        if ($warehouseId) {
            return [$warehouseId];
        }

        if ($user && $user->is_all_warehouses) {
            return \App\Models\Warehouse::whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
        }

        if ($user) {
            return \App\Models\UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }

        return [];
    }
}
