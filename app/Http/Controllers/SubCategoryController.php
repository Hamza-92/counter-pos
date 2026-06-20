<?php

namespace App\Http\Controllers;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubCategoryController extends BaseController
{
    /**
     * GET /subcategories
     * Query params: page, limit, SortField, SortType, search
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', SubCategory::class);

        $page = (int) $request->input('page', 1);
        $perPage = (string) $request->input('limit', '10');
        $sortField = (string) $request->input('SortField', 'id');
        $sortType = strtolower((string) $request->input('SortType', 'desc')) === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->input('search', ''));

        $allowedSort = ['id', 'name', 'status', 'created_at', 'updated_at'];
        if (! in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }

        $query = SubCategory::query()
            ->with('category:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });

        $totalRows = (clone $query)->count();

        if ($perPage === '-1') {
            $perPage = $totalRows > 0 ? $totalRows : 1;
        } else {
            $perPage = max(1, (int) $perPage);
        }

        $offSet = ($page * $perPage) - $perPage;

        $rows = $query
            ->orderBy($sortField, $sortType)
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        return response()->json([
            'subcategories' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * POST /subcategories
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', SubCategory::class);

        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => [
                'required',
                'string',
                'max:190',
                // unique per category
                Rule::unique('subcategories')->where(function ($q) use ($request) {
                    return $q->where('category_id', $request->category_id);
                }),
            ],
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        SubCategory::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /subcategories/{id}
     */
    public function show($id)
    {
        $subcategory = SubCategory::with('category:id,name')->findOrFail($id);

        return response()->json($subcategory);
    }

    /**
     * PUT /subcategories/{id}
     */
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', SubCategory::class);

        $subcategory = SubCategory::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('subcategories')
                    ->where(function ($q) use ($request) {
                        return $q->where('category_id', $request->category_id);
                    })
                    ->ignore($subcategory->id),
            ],
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $subcategory->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $subcategory->status,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /subcategories/{id}
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', SubCategory::class);

        SubCategory::whereId($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /subcategories/delete/by_selection
     * Body: { selectedIds: [1,2,3] }
     */
    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', SubCategory::class);

        $selectedIds = (array) $request->input('selectedIds', []);
        if (! empty($selectedIds)) {
            SubCategory::whereIn('id', $selectedIds)->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /subcategories/by-category/{category_id}
     */
    public function getByCategory($category_id)
    {
        $subcategories = SubCategory::where('category_id', $category_id)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return response()->json($subcategories);
    }
}


