<?php

namespace App\Http\Controllers;

use App\Models\ServiceJob;
use App\Models\ServiceTechnician;
use Illuminate\Http\Request;

class ServiceTechnicianController extends BaseController
{
    // -------------- Get All Technicians ---------------\\
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $perPage = $request->limit ?: 10;
        $pageStart = (int) $request->get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = strtolower($request->SortType ?: 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $sortableMap = [
            'id' => 'service_technicians.id',
            'name' => 'service_technicians.name',
            'is_active' => 'service_technicians.is_active',
        ];
        $order = $sortableMap[$order] ?? 'service_technicians.id';

        $query = ServiceTechnician::whereNull('deleted_at')
            ->where(function ($q) use ($request) {
                return $q->when($request->filled('search'), function ($q) use ($request) {
                    $s = $request->search;

                    return $q->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('phone', 'LIKE', "%{$s}%")
                        ->orWhere('email', 'LIKE', "%{$s}%");
                });
            });

        $totalRows = $query->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $rows = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        return response()->json([
            'technicians' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    // -------------- Store New Technician ---------------\\
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', ServiceJob::class);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        ServiceTechnician::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true], 201);
    }

    // -------------- Update Technician ---------------\\
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $technician = ServiceTechnician::whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $technician->update($validated);

        return response()->json(['success' => true]);
    }

    // -------------- Delete Technician ---------------\\
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ServiceJob::class);

        $technician = ServiceTechnician::findOrFail($id);
        $technician->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }
}

















