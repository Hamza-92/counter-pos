<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use App\Models\CommissionRule;
use Illuminate\Http\Request;

class CommissionRuleController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'priority';
        $dir = $request->SortType ?? 'desc';

        $query = CommissionRule::with(['commissionProgram:id,name', 'salesAgent:id,name,code'])
            ->where('deleted_at', null);

        if ($request->filled('commission_program_id')) {
            $query->where('commission_program_id', $request->commission_program_id);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $totalRows = $query->count();
        if ($perPage === '-1') {
            $perPage = $totalRows;
        }
        $rules = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        return $this->sendResponse([
            'rules' => $rules,
            'totalRows' => $totalRows,
        ], '');
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', CommissionProgram::class);
        $request->validate([
            'commission_program_id' => 'required|integer|exists:commission_programs,id',
            'name' => 'required|string|max:192',
            'type' => 'required|in:percentage,fixed',
            'source' => 'required|in:sale_total,paid_amount',
            'value' => 'required|numeric|min:0',
            'min_threshold' => 'nullable|numeric|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'applies_to' => 'required|in:all_agents,specific_agent',
            'sales_agent_id' => 'nullable|required_if:applies_to,specific_agent|integer|exists:sales_agents,id',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $rule = CommissionRule::create([
            'commission_program_id' => $request->commission_program_id,
            'name' => $request->name,
            'type' => $request->type,
            'source' => $request->source,
            'value' => $request->value,
            'min_threshold' => $request->min_threshold,
            'max_cap' => $request->max_cap,
            'applies_to' => $request->applies_to,
            'sales_agent_id' => $request->applies_to === 'specific_agent' ? $request->sales_agent_id : null,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->sendResponse($rule->load(['commissionProgram', 'salesAgent']), __('Created successfully'));
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $rule = CommissionRule::with(['commissionProgram', 'salesAgent'])->findOrFail($id);
        return $this->sendResponse($rule, '');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', CommissionProgram::class);
        $request->validate([
            'commission_program_id' => 'required|integer|exists:commission_programs,id',
            'name' => 'required|string|max:192',
            'type' => 'required|in:percentage,fixed',
            'source' => 'required|in:sale_total,paid_amount',
            'value' => 'required|numeric|min:0',
            'min_threshold' => 'nullable|numeric|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'applies_to' => 'required|in:all_agents,specific_agent',
            'sales_agent_id' => 'nullable|required_if:applies_to,specific_agent|integer|exists:sales_agents,id',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $rule = CommissionRule::findOrFail($id);
        $rule->update([
            'commission_program_id' => $request->commission_program_id,
            'name' => $request->name,
            'type' => $request->type,
            'source' => $request->source,
            'value' => $request->value,
            'min_threshold' => $request->min_threshold,
            'max_cap' => $request->max_cap,
            'applies_to' => $request->applies_to,
            'sales_agent_id' => $request->applies_to === 'specific_agent' ? $request->sales_agent_id : null,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->sendResponse($rule->fresh()->load(['commissionProgram', 'salesAgent']), __('Updated successfully'));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', CommissionProgram::class);
        $rule = CommissionRule::findOrFail($id);
        $rule->delete();
        return $this->sendResponse(null, __('Deleted successfully'));
    }
}
