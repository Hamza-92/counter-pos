<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class AccountingCloseYear extends Command
{
    protected $signature = 'accounting:close-year {--year=}';

    protected $description = 'Create year-end closing entry transferring P&L to retained earnings';

    public function handle(): int
    {
        if (! config('accounting_v2.enabled', true)) {
            $this->info('Accounting V2 is disabled. Nothing to do.');

            return 0;
        }
        if (! Schema::hasTable('acc_journal_entry_lines')) {
            $this->warn('Accounting tables not installed. Skipping.');

            return 0;
        }

        $year = (int) ($this->option('year') ?: (int) date('Y') - 1);
        $periodEnd = Carbon::create($year, 12, 31)->toDateString();
        $periodStart = Carbon::create($year, 1, 1)->toDateString();

        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->whereBetween('j.date', [$periodStart, $periodEnd]);

        $incomeRows = (clone $base)
            ->where('c.type', 'income')
            ->select('c.id as coa_id', DB::raw('SUM(l.credit - l.debit) as amount'))
            ->groupBy('c.id')->get();
        $expenseRows = (clone $base)
            ->where('c.type', 'expense')
            ->select('c.id as coa_id', DB::raw('SUM(l.debit - l.credit) as amount'))
            ->groupBy('c.id')->get();

        $totalIncome = (float) $incomeRows->sum('amount');
        $totalExpense = (float) $expenseRows->sum('amount');
        $net = $totalIncome - $totalExpense; // profit positive

        if (abs($totalIncome) < 0.0001 && abs($totalExpense) < 0.0001) {
            $this->info("No P&L activity for {$year}. Nothing to close.");

            return 0;
        }

        $retainedCode = Config::get('accounting_v2.codes.retained_earnings', '3100');
        $retained = DB::table('acc_chart_of_accounts')->where('code', $retainedCode)->first();
        if (! $retained) {
            $retainedId = DB::table('acc_chart_of_accounts')->insertGetId([
                'account_id' => null,
                'code' => $retainedCode,
                'name' => 'Retained Earnings',
                'type' => 'equity',
                'parent_id' => null,
                'level' => 0,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $retained = DB::table('acc_chart_of_accounts')->where('id', $retainedId)->first();
        }

        $journalId = DB::table('acc_journal_entries')->insertGetId([
            'date' => $periodEnd,
            'description' => "Year-end closing {$year}",
            'reference_type' => 'year_close',
            'reference_id' => $year,
            'status' => 'posted',
            'posted_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Reverse out income (debit) and expenses (credit)
        foreach ($incomeRows as $r) {
            if ((float) $r->amount > 0) {
                DB::table('acc_journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'coa_id' => $r->coa_id,
                    'debit' => (float) $r->amount,
                    'credit' => 0,
                    'memo' => 'Close income',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
        foreach ($expenseRows as $r) {
            if ((float) $r->amount > 0) {
                DB::table('acc_journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'coa_id' => $r->coa_id,
                    'debit' => 0,
                    'credit' => (float) $r->amount,
                    'memo' => 'Close expense',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // Offset to retained earnings
        if ($net >= 0) {
            // Profit: credit retained earnings
            DB::table('acc_journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'coa_id' => $retained->id,
                'debit' => 0,
                'credit' => (float) $net,
                'memo' => 'Transfer net profit',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } else {
            // Loss: debit retained earnings
            DB::table('acc_journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'coa_id' => $retained->id,
                'debit' => (float) abs($net),
                'credit' => 0,
                'memo' => 'Transfer net loss',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->info("Year {$year} closed with net ".number_format($net, 2));

        return 0;
    }
}





