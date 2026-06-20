<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\FeaturedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeaturedApiController extends Controller
{
    // List with optional filters: section_key, active; pagination (page, per_page)
    public function index(Request $req)
    {
        $section = $req->query('section_key', 'home');
        $active = $req->query('active'); // null|0|1
        $per = (int) $req->query('per_page', 50);

        $q = FeaturedProduct::with(['product' => function ($q) {
            $q->select('id', 'name', 'image', 'price', 'slug'); // adapt columns
        }])
            ->where('section_key', $section)
            ->when($active !== null, fn ($qq) => $qq->where('active', (bool) $active))
            ->orderBy('position')
            ->orderBy('id');

        $p = $q->paginate($per);

        return response()->json([
            'data' => $p->items(),
            'meta' => ['total' => $p->total(), 'page' => $p->currentPage(), 'pages' => $p->lastPage()],
        ]);
    }

    // Create
    public function store(Request $req)
    {
        $data = $req->validate([
            'product_id' => 'required|exists:products,id',
            'section_key' => 'nullable|string|max:64',
            'position' => 'nullable|integer|min:1',
            'active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data['section_key'] = $data['section_key'] ?? 'home';
        if (empty($data['position'])) {
            // default to last+1 in that section
            $max = FeaturedProduct::where('section_key', $data['section_key'])->max('position');
            $data['position'] = (int) $max + 1;
        }

        $fp = FeaturedProduct::create($data);

        return response()->json($fp->load('product:id,name,image,price,slug'), 201);
    }

    // Update
    public function update(Request $req, $id)
    {
        $fp = FeaturedProduct::findOrFail($id);
        $data = $req->validate([
            'product_id' => 'nullable|exists:products,id',
            'section_key' => 'nullable|string|max:64',
            'position' => 'nullable|integer|min:1',
            'active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $fp->fill($data)->save();

        return response()->json($fp->load('product:id,name,image,price,slug'));
    }

    // Delete
    public function destroy($id)
    {
        $fp = FeaturedProduct::findOrFail($id);
        $fp->delete();

        return response()->json(['ok' => true]);
    }

    // Bulk reorder: payload = [{id:1, position:1}, {id:5, position:2}, ...]
    public function reorder(Request $req)
    {
        $items = $req->validate([
            '*.id' => 'required|integer|exists:featured_products,id',
            '*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($items) {
            foreach ($items as $row) {
                FeaturedProduct::where('id', $row['id'])->update(['position' => $row['position']]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
