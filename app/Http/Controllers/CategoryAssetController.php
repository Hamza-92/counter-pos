<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class CategoryAssetController extends BaseController
{
    // List categories (with optional search, pagination similar to other category controllers)
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Asset::class);

        $query = AssetCategory::query()->whereNull('deleted_at');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // if no pagination requested, return all for selects
        $perPage = $request->integer('limit', 0);
        if ($perPage > 0) {
            $page = max(1, (int) $request->integer('page', 1));
            $total = $query->count();
            $data = $query->orderBy('name')->forPage($page, $perPage)->get();

            return response()->json([
                'data' => $data,
                'totalRows' => $total,
            ]);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Asset::class);
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
        ]);

        $category = AssetCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Asset::class);
        $category = AssetCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Asset::class);
        $category = AssetCategory::findOrFail($id);
        $category->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }
}
