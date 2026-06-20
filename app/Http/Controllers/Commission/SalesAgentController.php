<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use App\Models\SalesAgent;
use Illuminate\Http\Request;

class SalesAgentController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        $query = SalesAgent::with('user:id,firstname,lastname,email')
            ->withCount('saleCommissions')
            ->where('deleted_at', null);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $totalRows = $query->count();
        if ($perPage === '-1') {
            $perPage = $totalRows;
        }
        $agents = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        return $this->sendResponse([
            'agents' => $agents,
            'totalRows' => $totalRows,
        ], '');
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', CommissionProgram::class);
        $request->validate([
            'name' => 'required|string|max:192',
            'code' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:192',
            'phone' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $agent = SalesAgent::create([
            'name' => $request->name,
            'code' => $request->code,
            'email' => $request->email,
            'phone' => $request->phone,
            'user_id' => $request->user_id,
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->notes,
        ]);

        return $this->sendResponse($agent, __('Created successfully'));
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $agent = SalesAgent::with('user:id,firstname,lastname,email')->findOrFail($id);
        return $this->sendResponse($agent, '');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', CommissionProgram::class);
        $request->validate([
            'name' => 'required|string|max:192',
            'code' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:192',
            'phone' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $agent = SalesAgent::findOrFail($id);
        $agent->update([
            'name' => $request->name,
            'code' => $request->code,
            'email' => $request->email,
            'phone' => $request->phone,
            'user_id' => $request->user_id,
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->notes,
        ]);

        return $this->sendResponse($agent->fresh(), __('Updated successfully'));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', CommissionProgram::class);
        $agent = SalesAgent::findOrFail($id);
        $agent->delete();
        return $this->sendResponse(null, __('Deleted successfully'));
    }

    /** List agents for dropdowns (id, name, code). */
    public function listForSelect(Request $request)
    {
        $agents = SalesAgent::where('deleted_at', null)->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        return $this->sendResponse($agents, '');
    }
}
