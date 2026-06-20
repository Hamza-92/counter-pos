<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingV2\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class ChartOfAccountsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        if (! Schema::hasTable('acc_chart_of_accounts')) {
            return response()->json(['data' => [], 'totalRows' => 0]);
        }
        $query = ChartOfAccount::query();
        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->has('active')) {
            $query->where('is_active', (int) $request->get('active') === 1);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        $totalRows = $query->count();
        $perPage = (int) ($request->get('limit') ?: 10);
        if ($perPage === -1) {
            $perPage = $totalRows > 0 ? $totalRows : 10;
        }
        $page = max(1, (int) ($request->get('page') ?: 1));
        $offSet = ($page * $perPage) - $perPage;
        $order = $request->get('SortField', 'code');
        $dir = $request->get('SortType', 'asc');

        $rows = $query->orderBy($order, $dir)->offset($offSet)->limit($perPage)->get();

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $this->validate($request, [
            'code' => 'required|max:64',
            'name' => 'required|max:192',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);
        $coa = ChartOfAccount::create([
            'account_id' => $request->get('account_id'),
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'parent_id' => $request->get('parent_id'),
            'level' => (int) $request->get('level', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($coa, 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $coa = ChartOfAccount::findOrFail($id);
        $this->validate($request, [
            'code' => 'sometimes|max:64',
            'name' => 'sometimes|max:192',
            'type' => 'sometimes|in:asset,liability,equity,income,expense',
        ]);
        $coa->update($request->only(['account_id', 'code', 'name', 'type', 'parent_id', 'level', 'is_active']));

        return response()->json($coa);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $coa = ChartOfAccount::findOrFail($id);
        $coa->delete();

        return response()->json(['success' => true]);
    }
}
