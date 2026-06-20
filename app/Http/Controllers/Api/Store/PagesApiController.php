<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PagesApiController extends Controller
{
    public function index(Request $req)
    {
        $q = trim((string) $req->query('q', ''));
        $sort = $req->query('sort', 'updated_at');
        $dir = $req->query('dir', 'desc');
        $per = (int) $req->query('per_page', 10);

        $pages = StorePage::query()
            ->when($q !== '', fn ($qq) => $qq->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"))
            ->orderBy($sort, $dir)
            ->paginate($per);

        return response()->json([
            'data' => $pages->items(),
            'meta' => ['total' => $pages->total()],
        ]);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'title' => 'required|string|max:190',
            'slug' => 'nullable|string|max:190|unique:store_pages,slug',
            'content' => 'nullable|string',
            'published' => 'boolean',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = str($data['title'])->slug('-');
        }

        $page = StorePage::create($data);

        return response()->json($page, 201);
    }

    public function show($id)
    {
        $page = StorePage::findOrFail($id);

        return response()->json($page);
    }

    public function update(Request $req, $id)
    {
        $page = StorePage::findOrFail($id);
        $data = $req->validate([
            'title' => 'required|string|max:190',
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('store_pages', 'slug')->ignore($page->id)],
            'content' => 'nullable|string',
            'published' => 'boolean',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = str($data['title'])->slug('-');
        }

        $page->fill($data)->save();

        return response()->json($page);
    }

    public function destroy($id)
    {
        $page = StorePage::findOrFail($id);
        $page->delete();

        return response()->json(['ok' => true]);
    }
}
