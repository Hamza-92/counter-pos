<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    // GET /admin/store/collections
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Collection::class);

        $q = trim((string) $request->get('q', ''));
        $query = Collection::query()->withCount('products');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        // Return plain array (your Vue handles both array or {data:[]})
        $items = $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json($items);
    }

    // GET /admin/store/collections/{collection}
    public function show(Request $request, Collection $collection)
    {
        $this->authorizeForUser($request->user('api'), 'view', Collection::class);
        $collection->load(['products' => function ($q) {
            $q->withPivot(['sort_order', 'pinned'])
                ->orderBy('collection_product.sort_order');
        }]);

        return response()->json($collection);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', \App\Models\Collection::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'alpha_dash', 'unique:collections,slug'],
            'description' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
            // removed: 'is_active'
        ]);

        $data['limit'] = $data['limit'] ?? 8;
        $data['sort_order'] = $data['sort_order'] ?? ((\App\Models\Collection::max('sort_order') ?? 0) + 10);

        return DB::transaction(function () use ($data) {
            // 1) Create collection
            $c = \App\Models\Collection::create($data);

            // 2) Insert into homepage_lineup (before first "newsletter"), de-duping by slug
            $settings = \App\Models\StoreSetting::query()->first(); // adjust for multi-tenant if needed
            if ($settings) {
                $lineup = $settings->homepage_lineup ?? [];

                if ($lineup instanceof \Illuminate\Support\Collection) {
                    $lineup = $lineup->toArray();
                }

                // De-duplicate any existing block for this slug
                $lineup = array_values(array_filter($lineup, function ($item) use ($c) {
                    return ! is_array($item)
                        || ($item['type'] ?? null) !== 'collection'
                        || ($item['slug'] ?? null) !== $c->slug;
                }));

                $block = [
                    'slug' => $c->slug,
                    'type' => 'collection',
                    'limit' => $c->limit,
                    'layout' => 'grid',
                    'title_override' => '',
                ];

                // Find first "newsletter" index
                $insertIndex = null;
                foreach ($lineup as $i => $item) {
                    if (is_array($item) && ($item['type'] ?? null) === 'newsletter') {
                        $insertIndex = $i;
                        break;
                    }
                }

                if ($insertIndex === null) {
                    $lineup[] = $block;
                } else {
                    array_splice($lineup, $insertIndex, 0, [$block]);
                }

                $settings->homepage_lineup = $lineup;
                $settings->save();
            }

            return response()->json($c->fresh(), 201);
        });
    }

    // PUT /admin/store/collections/{collection}
    public function update(Request $request, \App\Models\Collection $collection)
    {
        $this->authorizeForUser($request->user('api'), 'view', \App\Models\Collection::class);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'slug' => ['sometimes', 'string', 'max:190', 'alpha_dash',
                Rule::unique('collections', 'slug')->ignore($collection->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($collection, $data) {
            $oldSlug = $collection->slug;

            // Update the collection itself
            $collection->fill($data)->save();
            $collection->refresh();

            // Sync homepage_lineup
            $settings = \App\Models\StoreSetting::query()->first(); // scope for tenant if needed
            if ($settings) {
                $lineup = $settings->homepage_lineup ?? [];
                if ($lineup instanceof \Illuminate\Support\Collection) {
                    $lineup = $lineup->toArray();
                }

                $changed = false;

                // Find block for old or new slug
                $idx = null;
                foreach ($lineup as $i => $item) {
                    if (
                        is_array($item) &&
                        ($item['type'] ?? null) === 'collection' &&
                        in_array(($item['slug'] ?? null), [$oldSlug, $collection->slug], true)
                    ) {
                        $idx = $i;
                        break;
                    }
                }

                if ($idx === null) {
                    // Not found: insert before first "newsletter"
                    $block = [
                        'slug' => $collection->slug,
                        'type' => 'collection',
                        'limit' => $collection->limit,
                        'layout' => 'grid',
                        'title_override' => '',
                    ];

                    $insertIndex = null;
                    foreach ($lineup as $i => $item) {
                        if (is_array($item) && ($item['type'] ?? null) === 'newsletter') {
                            $insertIndex = $i;
                            break;
                        }
                    }

                    if ($insertIndex === null) {
                        $lineup[] = $block;
                    } else {
                        array_splice($lineup, $insertIndex, 0, [$block]);
                    }
                    $changed = true;
                } else {
                    // Update existing block (slug/limit)
                    if (($lineup[$idx]['slug'] ?? null) !== $collection->slug) {
                        $lineup[$idx]['slug'] = $collection->slug;
                        $changed = true;
                    }
                    if (($lineup[$idx]['limit'] ?? null) !== $collection->limit) {
                        $lineup[$idx]['limit'] = $collection->limit;
                        $changed = true;
                    }
                }

                // De-duplicate any other blocks with the same slug (keep first occurrence)
                $seen = [];
                $dedup = [];
                foreach ($lineup as $item) {
                    if (is_array($item) && ($item['type'] ?? null) === 'collection') {
                        $slug = $item['slug'] ?? null;
                        if ($slug) {
                            if (isset($seen[$slug])) {
                                $changed = true;

                                continue;
                            }
                            $seen[$slug] = true;
                        }
                    }
                    $dedup[] = $item;
                }
                $lineup = array_values($dedup);

                if ($changed) {
                    $settings->homepage_lineup = $lineup; // cast -> json
                    $settings->save();
                }
            }

            return response()->json($collection);
        });
    }

    // DELETE /admin/store/collections/{collection}

    public function destroy(Request $request, \App\Models\Collection $collection)
    {
        $this->authorizeForUser($request->user('api'), 'view', \App\Models\Collection::class);

        return DB::transaction(function () use ($collection) {
            $slug = $collection->slug;

            // 1) Clean pivot, then delete the collection
            $collection->products()->detach();
            $collection->delete();

            // 2) Remove from homepage_lineup if present
            $settings = \App\Models\StoreSetting::query()->first(); // scope for tenant if needed
            if ($settings) {
                $lineup = $settings->homepage_lineup ?? [];

                if ($lineup instanceof \Illuminate\Support\Collection) {
                    $lineup = $lineup->toArray();
                }

                $before = is_array($lineup) ? count($lineup) : 0;

                // Drop any 'collection' block with this slug
                $lineup = array_values(array_filter($lineup, function ($item) use ($slug) {
                    return ! is_array($item)
                        || ($item['type'] ?? null) !== 'collection'
                        || ($item['slug'] ?? null) !== $slug;
                }));

                if ($before !== count($lineup)) {
                    $settings->homepage_lineup = $lineup; // cast -> json
                    $settings->save();
                }
            }

            return response()->json(['ok' => true]);
        });
    }

    /**
     * POST /admin/store/collections/{collection}/products
     * Payload: { items: [{ product_id, sort_order, pinned }] }
     * Syncs manual items as the source of truth.
     */
    public function syncProducts(Request $request, Collection $collection)
    {
        $this->authorizeForUser($request->user('api'), 'view', Collection::class);
        $payload = $request->validate([
            'items' => ['array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'items.*.pinned' => ['nullable', 'boolean'],
        ]);

        $items = $payload['items'] ?? [];

        // Build sync array: [product_id => ['sort_order'=>..., 'pinned'=>...]]
        $sync = [];
        foreach ($items as $i => $row) {
            $pid = (int) $row['product_id'];
            $sync[$pid] = [
                'sort_order' => array_key_exists('sort_order', $row) ? (int) $row['sort_order'] : (($i + 1) * 10),
                'pinned' => ! empty($row['pinned']) ? 1 : 0,
            ];
        }

        // Transactional sync
        DB::transaction(function () use ($collection, $sync) {
            $collection->products()->sync($sync);
        });

        // Return with products after sync
        $collection->load(['products' => function ($q) {
            $q->withPivot(['sort_order', 'pinned'])
                ->orderBy('collection_product.sort_order');
        }]);

        return response()->json($collection);
    }

    // (Optional) Lightweight product search if you don’t already have one
    public function searchProducts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Collection::class);
        $q = trim((string) $request->get('q', ''));
        $limit = min((int) $request->get('limit', 20), 50);

        $query = Product::query()
            ->select('id', 'name', 'code')
            ->where('is_active', 1)
            ->whereNull('deleted_at');

        if ($q !== '') {
            $query->where(function ($s) use ($q) {
                $s->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        return response()->json($query->orderBy('name')->limit($limit)->get());
    }
}
