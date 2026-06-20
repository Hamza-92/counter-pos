<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class ReportsController extends Controller
{
    public function trialBalance(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'trial_balance', Account::class);

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json(['data' => [], 'totalRows' => 0]);
        }
        $from = $request->get('from');
        $to = $request->get('to');
        $search = trim((string) $request->get('search', ''));
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));
        $sortField = $request->get('SortField', 'code');
        $sortType = strtolower($request->get('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['code' => 'c.code', 'name' => 'c.name', 'type' => 'c.type', 'debit' => 'debit', 'credit' => 'credit'];
        $orderBy = $allowedSort[$sortField] ?? 'c.code';

        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted');
        if ($from) {
            $base->where('j.date', '>=', $from);
        }
        if ($to) {
            $base->where('j.date', '<=', $to);
        }
        if ($request->filled('type')) {
            $base->where('c.type', $request->get('type'));
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%$search%")->orWhere('c.name', 'like', "%$search%");
            });
        }

        $grouped = $base->select('c.id as coa_id', 'c.code', 'c.name', 'c.type', DB::raw('SUM(l.debit) as debit'), DB::raw('SUM(l.credit) as credit'))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type');

        // total groups
        $totalRows = (clone $grouped)->get()->count();

        // pagination + sorting
        $rows = (clone $grouped)
            ->orderBy($orderBy, $sortType)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    public function profitAndLoss(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'accounting_profit_loss', Account::class);

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json(['data' => [], 'totalRows' => 0, 'summary' => ['income' => 0, 'expense' => 0, 'net_profit' => 0]]);
        }
        $from = $request->get('from');
        $to = $request->get('to');
        $search = trim((string) $request->get('search', ''));
        $typeFilter = $request->get('type'); // income|expense optional
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));
        $sortField = $request->get('SortField', 'code');
        $sortType = strtolower($request->get('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['code' => 'c.code', 'name' => 'c.name', 'type' => 'c.type', 'amount' => 'amount'];
        $orderBy = $allowedSort[$sortField] ?? 'c.code';

        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->whereIn('c.type', ['income', 'expense']);
        if ($from) {
            $base->where('j.date', '>=', $from);
        }
        if ($to) {
            $base->where('j.date', '<=', $to);
        }
        if ($typeFilter) {
            $base->where('c.type', $typeFilter);
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%$search%")->orWhere('c.name', 'like', "%$search%");
            });
        }

        $amountExpr = "SUM(CASE WHEN c.type='income' THEN (l.credit - l.debit) ELSE (l.debit - l.credit) END)";
        $grouped = $base->select('c.id as coa_id', 'c.code', 'c.name', 'c.type', DB::raw("$amountExpr as amount"))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type');

        // Total rows (excluding zero-amount rows)
        $totalRows = (clone $grouped)->havingRaw("$amountExpr <> 0")
            ->get()
            ->count();

        // Page rows
        $rows = (clone $grouped)
            ->havingRaw("$amountExpr <> 0")
            ->orderBy($orderBy, $sortType)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Summary
        $income = (float) ((clone $base)->where('c.type', 'income')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0);
        $expense = (float) ((clone $base)->where('c.type', 'expense')->select(DB::raw('SUM(l.debit - l.credit) as amount'))->value('amount') ?? 0);
        $summary = ['income' => $income, 'expense' => $expense, 'net_profit' => $income - $expense];

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
            'summary' => $summary,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'balance_sheet', Account::class);

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json(['data' => []]);
        }
        $to = $request->get('to', Carbon::now()->toDateString());
        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->where('j.date', '<=', $to);
        $assets = (clone $base)->where('c.type', 'asset')->select(DB::raw('SUM(l.debit - l.credit) as amount'))->value('amount') ?? 0;
        $liabilities = (clone $base)->where('c.type', 'liability')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0;
        $equity = (clone $base)->where('c.type', 'equity')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0;

        return response()->json([
            'date' => $to,
            'assets' => (float) $assets,
            'liabilities' => (float) $liabilities,
            'equity' => (float) $equity,
            'balance' => (float) $assets - ((float) $liabilities + (float) $equity),
        ]);
    }

    public function taxSummary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'accounting_tax_report', Account::class);

        // Compute from legacy sales/purchases including returns
        $from = $request->get('from');
        $to = $request->get('to');

        $sales = 0.0;
        $outputTax = 0.0;
        $saleReturns = 0.0;
        $saleReturnTax = 0.0;
        $purchases = 0.0;
        $inputTax = 0.0;
        $purchaseReturns = 0.0;
        $purchaseReturnTax = 0.0;

        // Calculate Sales (Output Tax)
        if (Schema::hasTable('sales')) {
            $q = DB::table('sales')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending'); // Exclude draft/pending sales
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $sales = (float) ($q->sum('GrandTotal') ?? 0);
            $outputTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Sale Returns (Reduce Output Tax)
        if (Schema::hasTable('sale_returns')) {
            $q = DB::table('sale_returns')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending');
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $saleReturns = (float) ($q->sum('GrandTotal') ?? 0);
            $saleReturnTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Purchases (Input Tax)
        if (Schema::hasTable('purchases')) {
            $q = DB::table('purchases')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending'); // Exclude draft/pending purchases
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $purchases = (float) ($q->sum('GrandTotal') ?? 0);
            $inputTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Purchase Returns (Reduce Input Tax)
        if (Schema::hasTable('purchase_returns')) {
            $q = DB::table('purchase_returns')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending');
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $purchaseReturns = (float) ($q->sum('GrandTotal') ?? 0);
            $purchaseReturnTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Net calculations (sales - returns)
        $netSales = $sales - $saleReturns;
        $netOutputTax = $outputTax - $saleReturnTax;
        $netPurchases = $purchases - $purchaseReturns;
        $netInputTax = $inputTax - $purchaseReturnTax;

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'sales' => $sales,
            'sale_returns' => $saleReturns,
            'taxable_sales' => $netSales,
            'output_tax' => $netOutputTax,
            'purchases' => $purchases,
            'purchase_returns' => $purchaseReturns,
            'taxable_purchases' => $netPurchases,
            'input_tax' => $netInputTax,
            'net_tax' => $netOutputTax - $netInputTax,
        ]);
    }
}
