<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use Illuminate\Http\Request;

class CommissionProgramController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        $query = CommissionProgram::withCount('commissionRules', 'saleCommissions')
            ->where('deleted_at', null);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $totalRows = $query->count();
        if ($perPage === '-1') {
            $perPage = $totalRows;
        }
        $programs = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        return $this->sendResponse([
            'programs' => $programs,
            'totalRows' => $totalRows,
        ], '');
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', CommissionProgram::class);
        $request->validate([
            'name' => 'required|string|max:192',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $program = CommissionProgram::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
        ]);

        return $this->sendResponse($program, __('Created successfully'));
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $program = CommissionProgram::with(['commissionRules.salesAgent'])->findOrFail($id);
        return $this->sendResponse($program, '');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', CommissionProgram::class);
        $request->validate([
            'name' => 'required|string|max:192',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $program = CommissionProgram::findOrFail($id);
        $program->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
        ]);

        return $this->sendResponse($program->fresh(), __('Updated successfully'));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', CommissionProgram::class);
        $program = CommissionProgram::findOrFail($id);
        $program->delete();
        return $this->sendResponse(null, __('Deleted successfully'));
    }
}
