<?php

namespace App\Http\Controllers;

use App\Models\ServiceChecklistCategory;
use App\Models\ServiceChecklistItem;
use App\Models\ServiceJob;
use Illuminate\Http\Request;

class ServiceChecklistController extends BaseController
{
    // -------- Categories: list (with optional pagination & search) -------\\
    public function categoriesIndex(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $query = ServiceChecklistCategory::query()
            ->whereNull('deleted_at')
            ->withCount(['items' => function ($q) {
                $q->whereNull('deleted_at');
            }]);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) $request->get('limit', 0);
        if ($perPage > 0) {
            $pageStart = (int) $request->get('page', 1);
            $offSet = ($pageStart * $perPage) - $perPage;
            $total = $query->count();
            $rows = $query->orderBy('name')
                ->offset($offSet)
                ->limit($perPage)
                ->get();

            return response()->json([
                'categories' => $rows,
                'totalRows' => $total,
            ]);
        }

        return response()->json([
            'categories' => $query->orderBy('name')->get(),
        ]);
    }

    public function categoriesStore(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', ServiceJob::class);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category = ServiceChecklistCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function categoriesUpdate(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $category = ServiceChecklistCategory::whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json(['success' => true]);
    }

    public function categoriesDestroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ServiceJob::class);

        $category = ServiceChecklistCategory::whereNull('deleted_at')->findOrFail($id);
        $category->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }

    // -------- Items: list / CRUD -------\\
    public function itemsIndex(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $query = ServiceChecklistItem::query()
            ->whereNull('deleted_at');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->get('category_id'));
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) $request->get('limit', 0);
        if ($perPage > 0) {
            $pageStart = (int) $request->get('page', 1);
            $offSet = ($pageStart * $perPage) - $perPage;
            $total = $query->count();
            $rows = $query->orderBy('sort_order')
                ->orderBy('name')
                ->offset($offSet)
                ->limit($perPage)
                ->get();

            return response()->json([
                'items' => $rows,
                'totalRows' => $total,
            ]);
        }

        return response()->json([
            'items' => $query->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function itemsStore(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', ServiceJob::class);

        $validated = $request->validate([
            'category_id' => 'nullable|integer',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $item = ServiceChecklistItem::create([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function itemsUpdate(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $item = ServiceChecklistItem::whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|integer',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $item->update($validated);

        return response()->json(['success' => true]);
    }

    public function itemsDestroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ServiceJob::class);

        $item = ServiceChecklistItem::whereNull('deleted_at')->findOrFail($id);
        $item->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }

    // -------- Options for forms (categories + items) -------\\
    public function options(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $categories = ServiceChecklistCategory::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = ServiceChecklistItem::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'items' => $items->where('category_id', $category->id)
                    ->values()
                    ->map(function (ServiceChecklistItem $item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'description' => $item->description,
                            'sort_order' => $item->sort_order,
                        ];
                    })
                    ->all(),
            ];
        }

        return response()->json([
            'categories' => $result,
        ]);
    }
}

















