<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CategorieController extends BaseController
{
    /**
     * GET /categories
     * Query params: page, limit, SortField, SortType, search
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Category::class);

        // Inputs with sane defaults
        $page = (int) $request->input('page', 1);
        $perPage = (string) $request->input('limit', '10');  // may be "-1"
        $sortField = (string) $request->input('SortField', 'id');
        $sortType = strtolower((string) $request->input('SortType', 'desc')) === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->input('search', ''));

        // Whitelist fields to avoid SQL injection on orderBy
        $allowedSort = ['id', 'name', 'code', 'created_at', 'updated_at'];
        if (! in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }

        $query = Category::query()
            ->whereNull('deleted_at')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });

        // Total before paging (used by vue-good-table)
        $totalRows = (clone $query)->count();

        // Handle "show all" case
        if ($perPage === '-1') {
            $perPage = $totalRows > 0 ? $totalRows : 1;
        } else {
            $perPage = max(1, (int) $perPage);
        }

        $offSet = ($page * $perPage) - $perPage;

        $categories = $query
            ->orderBy($sortField, $sortType)
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        return response()->json([
            'categories' => $categories,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * POST /categories
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Category::class);

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'required|string|max:190',
            'icon' => 'nullable|string|max:64',
        ]);

        $category = Category::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
        ]);

        return response()->json([
            'success'  => true,
            'category' => $category,
        ], 201);
    }

    /**
     * GET /categories/{id}
     */
    public function show($id)
    {
        $category = Category::whereNull('deleted_at')->findOrFail($id);

        return response()->json($category);
    }

    /**
     * PUT /categories/{id}
     */
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Category::class);

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'required|string|max:190',
            'icon' => 'nullable|string|max:64',
        ]);

        Category::whereId($id)->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /categories/{id}
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Category::class);

        Category::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /categories/delete/by_selection
     * Body: { selectedIds: [1,2,3] }
     */
    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Category::class);

        $selectedIds = (array) $request->input('selectedIds', []);
        if (empty($selectedIds)) {
            return response()->json(['success' => true]); // nothing to do
        }

        Category::whereIn('id', $selectedIds)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);
    }
}
