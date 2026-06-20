<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'accounting_dashboard', Account::class);

        $result = [
            'kpi' => [
                'accounts' => 0,
                'journals_30d' => 0,
                'income_30d' => 0.0,
                'expense_30d' => 0.0,
            ],
            'chart' => [
                'labels' => [],
                'income' => [],
                'expense' => [],
            ],
        ];

        $requiredTables = [
            'acc_chart_of_accounts',
            'acc_journal_entries',
            'acc_journal_entry_lines',
        ];
        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return response()->json($result);
            }
        }

        $today = Carbon::today();
        $from = $today->copy()->subDays(29);
        $fromDate = $from->toDateString();
        $toDate = $today->toDateString();

        $result['kpi']['accounts'] = (int) DB::table('acc_chart_of_accounts')->count();
        $result['kpi']['journals_30d'] = (int) DB::table('acc_journal_entries')
            ->whereBetween('date', [$fromDate, $toDate])
            ->count();

        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->whereBetween('j.date', [$fromDate, $toDate]);

        $income = (clone $base)
            ->where('c.type', 'income')
            ->select(DB::raw('SUM(l.credit - l.debit) as amount'))
            ->value('amount');
        $expense = (clone $base)
            ->where('c.type', 'expense')
            ->select(DB::raw('SUM(l.debit - l.credit) as amount'))
            ->value('amount');

        $result['kpi']['income_30d'] = round((float) ($income ?? 0), 2);
        $result['kpi']['expense_30d'] = round((float) ($expense ?? 0), 2);

        $daily = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->whereBetween('j.date', [$fromDate, $toDate])
            ->whereIn('c.type', ['income', 'expense'])
            ->select(
                'j.date',
                DB::raw("SUM(CASE WHEN c.type = 'income' THEN (l.credit - l.debit) ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN c.type = 'expense' THEN (l.debit - l.credit) ELSE 0 END) as expense")
            )
            ->groupBy('j.date')
            ->orderBy('j.date')
            ->get()
            ->keyBy('date');

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            $key = $date->toDateString();
            $row = $daily->get($key);
            $result['chart']['labels'][] = $key;
            $result['chart']['income'][] = round((float) ($row->income ?? 0), 2);
            $result['chart']['expense'][] = round((float) ($row->expense ?? 0), 2);
        }

        return response()->json($result);
    }
}
