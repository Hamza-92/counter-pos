<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingV2\JournalEntry;
use App\Models\AccountingV2\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class JournalEntriesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);

        if (! Schema::hasTable('acc_journal_entries')) {
            return response()->json(['data' => [], 'total' => 0]);
        }
        $q = JournalEntry::query()->orderByDesc('date')->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->get('status'));
        }
        if ($request->filled('reference_type')) {
            $q->where('reference_type', $request->get('reference_type'));
        }
        if ($request->filled('from')) {
            $q->where('date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->where('date', '<=', $request->get('to'));
        }
        $items = $q->with('lines')->paginate($request->get('limit', 20));

        return response()->json($items);
    }

    public function show(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);
        $entry = JournalEntry::with('lines')->findOrFail($id);

        return response()->json($entry);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);

        $this->validate($request, [
            'date' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.debit' => 'numeric',
            'lines.*.credit' => 'numeric',
        ]);

        $data = $request->only(['date', 'description', 'reference_type', 'reference_id']);
        $lines = $request->get('lines', []);
        // Allow saving unbalanced drafts; balance will be enforced on posting

        $entry = DB::transaction(function () use ($data, $lines) {
            $entry = JournalEntry::create([
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'reference_type' => $data['reference_type'] ?? 'manual',
                'reference_id' => $data['reference_id'] ?? null,
                'status' => 'draft',
                'posted_at' => null,
            ]);
            foreach ($lines as $l) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'coa_id' => $l['coa_id'] ?? null,
                    'account_id' => $l['account_id'] ?? null,
                    'debit' => (float) ($l['debit'] ?? 0),
                    'credit' => (float) ($l['credit'] ?? 0),
                    'memo' => $l['memo'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });

        return response()->json($entry, 201);
    }

    public function post(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);
        $entry = JournalEntry::findOrFail($id);
        if ($entry->status === 'posted') {
            return response()->json($entry);
        }

        // Ensure balanced before posting
        $totals = JournalEntryLine::where('journal_entry_id', $entry->id)
            ->select(DB::raw('SUM(debit) as debit'), DB::raw('SUM(credit) as credit'))
            ->first();
        $debit = (float) ($totals->debit ?? 0);
        $credit = (float) ($totals->credit ?? 0);
        if (round($debit - $credit, 4) !== 0.0) {
            return response()->json(['message' => 'Entry not balanced. Add lines so debits equal credits before posting.'], 422);
        }
        $entry->status = 'posted';
        $entry->posted_at = Carbon::now();
        $entry->save();

        return response()->json($entry);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);

        $entry = JournalEntry::with('lines')->findOrFail($id);
        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Posted entries cannot be edited. Create an adjusting entry instead.'], 422);
        }

        $this->validate($request, [
            'date' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.debit' => 'numeric',
            'lines.*.credit' => 'numeric',
        ]);

        $lines = $request->get('lines', []);
        // Allow saving unbalanced drafts; balance will be enforced on posting

        DB::transaction(function () use ($request, $entry, $lines) {
            $entry->update([
                'date' => $request->get('date'),
                'description' => $request->get('description'),
            ]);
            // Replace lines atomically
            JournalEntryLine::where('journal_entry_id', $entry->id)->delete();
            foreach ($lines as $l) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'coa_id' => $l['coa_id'] ?? null,
                    'account_id' => $l['account_id'] ?? null,
                    'debit' => (float) ($l['debit'] ?? 0),
                    'credit' => (float) ($l['credit'] ?? 0),
                    'memo' => $l['memo'] ?? null,
                ]);
            }
        });

        return response()->json($entry->fresh('lines'));
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'journal_entries', Account::class);

        $entry = JournalEntry::findOrFail($id);
        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Posted entries cannot be deleted. Create a reversing entry instead.'], 422);
        }
        DB::transaction(function () use ($entry) {
            JournalEntryLine::where('journal_entry_id', $entry->id)->delete();
            $entry->delete();
        });

        return response()->json(['success' => true]);
    }
}
