<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\StoreBanner;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Schema;
use DB;
use Illuminate\Http\Request;

class StoreFrontController extends Controller
{
    /**
     * Homepage — blocks driven by StoreSetting->homepage_lineup.
     */
    public function index(Request $request)
    {
        $s = StoreSetting::firstOrFail();

        // 1) Load lineup (already cast to array by StoreSetting::$casts)
        $lineup = is_array($s->homepage_lineup) ? $s->homepage_lineup : [];

        // 2) Legacy fallback (home_collections -> lineup)
        if (empty($lineup)) {
            $legacy = $s->home_collections ?? [];
            if (is_string($legacy)) {
                $legacy = json_decode($legacy, true) ?: [];
            }
            if ($legacy) {
                $rows = collect($legacy)
                    ->filter(fn ($r) => is_array($r) && ! empty($r['collection_id']) && (
                        ! array_key_exists('visible', $r)
                        || filter_var($r['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false
                    ))
                    ->sortBy(fn ($r) => (int) ($r['sort_order'] ?? 9999))
                    ->values();

                $ids = $rows->pluck('collection_id')->map(fn ($v) => (int) $v)->unique()->all();
                $idToSlug = $ids ? Collection::whereIn('id', $ids)->pluck('slug', 'id')->toArray() : [];

                $lineup = [];
                foreach ($rows as $r) {
                    $slug = (string) ($idToSlug[(int) $r['collection_id']] ?? '');
                    if ($slug === '') {
                        continue;
                    }
                    $limit = max(1, (int) ($r['limit'] ?? 8));
                    $lineup[] = [
                        'type' => 'collection',
                        'slug' => $slug,
                        'limit' => $limit,
                        'layout' => 'grid',
                        'title_override' => '',
                    ];
                }
            }
        }

        // ===== Shared price SQL (mirrors shop()) =====
        $minVariantSub = DB::table('product_variants')
            ->select('product_id', DB::raw('MIN(price) AS min_variant_price'))
            ->groupBy('product_id');

        // Base: if a product has variants, use MIN(variant.price); else use products.price
        $baseExpr = 'COALESCE(pvmin.min_variant_price, products.price)';

        // discount_method: '1' => percent, '2' => fixed
        $discValExpr = 'IFNULL(products.discount, 0)';
        $afterDiscountExpr = "GREATEST(0,
            CASE
                WHEN products.discount_method = '1' THEN $baseExpr - ($baseExpr * ($discValExpr/100))
                WHEN products.discount_method = '2' THEN $baseExpr - LEAST($discValExpr, $baseExpr)
                ELSE $baseExpr
            END
        )";

        // tax_method: '2' => Inclusive (leave as-is), otherwise treat as Exclusive and add tax
        $taxRateExpr = 'COALESCE(products.TaxNet, 0)';
        $finalExpr = "ROUND(
            CASE
                WHEN products.tax_method = '2' THEN $afterDiscountExpr
                ELSE $afterDiscountExpr * (1 + ($taxRateExpr/100))
            END, 2
        )";

        // 3) Build blocks
        $blocks = [];
        $defaultTaxRate = (float) ($s->default_tax_rate ?? 0);

        foreach ($lineup as $i => $item) {
            if (! is_array($item) || empty($item['type'])) {
                continue;
            }
            $type = strtolower((string) $item['type']);

            if ($type === 'hero') {
                $blocks[] = [
                    'type' => 'hero',
                    'title' => $s->hero_title ?? null,
                    'subtitle' => $s->hero_subtitle ?? null,
                    'image' => $s->hero_image_path ?? null,
                    'cfg' => ['index' => $i],
                ];

                continue;
            }

            if ($type === 'newsletter') {
                $blocks[] = [
                    'type' => 'newsletter',
                    'title' => __('Newsletter'),
                    'cfg' => ['index' => $i],
                ];

                continue;
            }

            if ($type === 'collection') {
                $slug = trim((string) ($item['slug'] ?? ($item['handle'] ?? '')));
                if ($slug === '') {
                    continue;
                }

                $limit = max(1, (int) ($item['limit'] ?? 8));
                $layout = in_array(($item['layout'] ?? 'grid'), ['grid', 'carousel'], true) ? $item['layout'] : 'grid';
                $titleOverride = trim((string) ($item['title_override'] ?? ''));

                $collection = Collection::where('slug', $slug)->first()
                    ?: (is_numeric($slug) ? Collection::find((int) $slug) : null);
                if (! $collection) {
                    continue;
                }

                $colTitle = $titleOverride !== '' ? $titleOverride : ($collection->title ?? $collection->name ?? $slug);

                // === Use the same SQL pipeline as shop(), scoped to this collection ===
                $products = Product::query()
                    ->where('products.is_active', 1)
                    ->where('products.hide_from_online_store', 0)
                    ->with([
                        'variants:id,product_id,name,price,image',
                        'images:id,product_id,image_path,is_main,sort_order',
                    ]) // QuickView / gallery + variant picker
                    ->join('collection_product', 'collection_product.product_id', '=', 'products.id')
                    ->where('collection_product.collection_id', $collection->id)
                    ->leftJoinSub($minVariantSub, 'pvmin', function ($join) {
                        $join->on('pvmin.product_id', '=', 'products.id');
                    })
                    ->addSelect(
                        'products.*',
                        DB::raw("$baseExpr AS base_price"),
                        DB::raw("$afterDiscountExpr AS after_discount"),
                        DB::raw("$finalExpr AS final_display_price")
                    )
                    ->orderBy('collection_product.sort_order')
                    ->orderBy('products.created_at', 'desc')
                    ->take($limit)
                    ->get();

                // Attach display_price to product (from SQL) AND compute each variant's display price (PHP)
                foreach ($products as $p) {
                    // Product display price from SQL
                    $p->display_price = (float) ($p->final_display_price ?? 0);

                    // Variant display prices computed with same rules as SQL
                    $taxRate = is_numeric($p->TaxNet) ? (float) $p->TaxNet : $defaultTaxRate;
                    $discVal = is_numeric($p->discount) ? (float) $p->discount : 0.0;
                    $isPercent = (string) $p->discount_method === '1';
                    $isInclusive = (string) $p->tax_method === '2';

                    if ($p->relationLoaded('variants') && $p->variants) {
                        foreach ($p->variants as $v) {
                            $price = (float) ($v->price ?? 0);
                            // discount
                            if ($discVal > 0) {
                                $price = $isPercent ? ($price - ($price * $discVal / 100)) : ($price - min($discVal, $price));
                                if ($price < 0) {
                                    $price = 0;
                                }
                            }
                            // tax
                            if (! $isInclusive && $taxRate > 0) {
                                $price = $price * (1 + $taxRate / 100);
                            }
                            $v->display_price = round($price, 2);
                        }
                    }
                }

                $this->attachStockToProducts($products, $s->default_warehouse_id);

                if ($s->hide_out_of_stock ?? false) {
                    $products = $products->filter(fn ($p) => $this->productHasStock($p));
                }

                $blocks[] = [
                    'type' => 'collection',
                    'title' => $colTitle,
                    'collection' => $collection,
                    'products' => $products, // each $p has display_price, stock; each variant has display_price, stock
                    'cfg' => [
                        'limit' => $limit,
                        'layout' => $layout,
                        'index' => $i,
                    ],
                ];
            }
        }

        // 4) Active banners
        $banners = StoreBanner::query()
            ->where('active', 1)
            ->whereIn('position', ['top_left', 'top_right', 'center_left', 'center_right', 'footer_left', 'footer_right'])
            ->orderBy('position')
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($banners as $b) {
            $b->image_url = asset($b->image ?: 'images/brands/no-image.png');
        }

        $categories = Category::with('subcategories')->orderBy('name')->get();

        $viewData = [
            's' => $s,
            'blocks' => $blocks,
            'categories' => $categories,
            'banners' => $banners,
            'showCategoryBar' => true,
        ];

        return view('store.index', $viewData);
    }

    /**
     * Shop — products with filters.
     * Sorting/filters use base "effective_price" (min variant or product price).
     * UI shows final display price (discount + tax) computed per item after fetch.
     */

     public function shop(Request $request)
    {
        $s = StoreSetting::firstOrFail();

        $q = trim((string) $request->get('q', ''));
        $cat = $request->get('category');
        $subCat = $request->get('sub_category');
        $minPrice = $request->get('min');
        $maxPrice = $request->get('max');
        $sort = $request->get('sort', 'latest');   // latest|price_asc|price_desc
        $coll = $request->get('collection');       // id or slug

        // 1) Subquery: MIN(variant.price) per product
        $minVariantSub = DB::table('product_variants')
            ->select('product_id', DB::raw('MIN(price) AS min_variant_price'))
            ->groupBy('product_id');

        // 2) SQL price pipeline (MySQL-compatible)
        $baseExpr = 'COALESCE(pvmin.min_variant_price, products.price)';

        // discount_method: '1'=percent, '2'=fixed (varchar)
        $discValExpr = 'IFNULL(products.discount, 0)';
        $afterDiscountExpr = "GREATEST(0,
            CASE
                WHEN products.discount_method = '1' THEN $baseExpr - ($baseExpr * ($discValExpr/100))
                WHEN products.discount_method = '2' THEN $baseExpr - LEAST($discValExpr, $baseExpr)
                ELSE $baseExpr
            END
        )";

        // tax_method: '1'=Exclusive, '2'=Inclusive (varchar);  TaxNet
        $taxRateExpr = 'COALESCE(products.TaxNet, 0)';
        $finalExpr = "ROUND(
            CASE
                WHEN products.tax_method = '2' THEN $afterDiscountExpr
                ELSE $afterDiscountExpr * (1 + ($taxRateExpr/100))
            END, 2
        )";

        $productsQuery = Product::query()
            ->where('deleted_at', '=', null)
            ->where('is_active', 1)
            ->where('hide_from_online_store', 0)
            // Note: product_variants table doesn't have a `qty` column; stock comes from product_warehouse.qte
            ->with([
                'variants:id,product_id,name,price,image',
                'images:id,product_id,image_path,is_main,sort_order',
            ]) // Quick View / gallery + picker
            ->leftJoinSub($minVariantSub, 'pvmin', function ($join) {
                $join->on('pvmin.product_id', '=', 'products.id');
            })
            ->addSelect(
                'products.*',
                DB::raw("$baseExpr AS base_price"),
                DB::raw("$afterDiscountExpr AS after_discount"),
                DB::raw("$finalExpr AS final_display_price")   // <= final price for filter/sort/UI
            );

        if ($s->hide_out_of_stock && $s->default_warehouse_id) {
            $inStockIds = $this->getInStockProductIds((int) $s->default_warehouse_id);
            $productsQuery->whereIn('products.id', $inStockIds);
        }

        $products = $productsQuery
            // Search
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where('products.name', 'like', "%{$q}%");
            })
            // Category (legacy column OR category_product pivot)
            ->when($cat, function ($qb) use ($cat) {
                $cid = (int) $cat;
                $qb->where(function ($q) use ($cid) {
                    $q->where('products.category_id', $cid);
                    if (Schema::hasTable('category_product')) {
                        $q->orWhereExists(function ($sub) use ($cid) {
                            $sub->select(DB::raw(1))
                                ->from('category_product')
                                ->whereColumn('category_product.product_id', 'products.id')
                                ->where('category_product.category_id', $cid);
                        });
                    }
                });
            })
            // Sub Category (legacy column OR product_subcategory pivot)
            ->when($subCat, function ($qb) use ($subCat) {
                $sid = (int) $subCat;
                $qb->where(function ($q) use ($sid) {
                    $q->where('products.sub_category_id', $sid);
                    if (Schema::hasTable('product_subcategory')) {
                        $q->orWhereExists(function ($sub) use ($sid) {
                            $sub->select(DB::raw(1))
                                ->from('product_subcategory')
                                ->whereColumn('product_subcategory.product_id', 'products.id')
                                ->where('product_subcategory.sub_category_id', $sid);
                        });
                    }
                });
            })
            // Price range (by final price)
            ->when(is_numeric($minPrice), function ($qb) use ($finalExpr, $minPrice) {
                $qb->whereRaw("$finalExpr >= ?", [(float) $minPrice]);
            })
            ->when(is_numeric($maxPrice), function ($qb) use ($finalExpr, $maxPrice) {
                $qb->whereRaw("$finalExpr <= ?", [(float) $maxPrice]);
            })
            // Collection: id or slug
            ->when($coll, function ($qb) use ($coll) {
                $qb->whereHas('collections', function ($rel) use ($coll) {
                    if (is_numeric($coll)) {
                        $rel->where('collections.id', (int) $coll);
                    } else {
                        $rel->where('collections.slug', (string) $coll);
                    }
                });
            });

        // Sort
        if ($sort === 'price_asc') {
            $products->orderByRaw("$finalExpr ASC");
        } elseif ($sort === 'price_desc') {
            $products->orderByRaw("$finalExpr DESC");
        } else {
            $products->orderBy('products.created_at', 'desc');
        }

        $products = $products->paginate(12)->withQueryString();
        $categories = Category::with('subcategories')->orderBy('name')->get(['id', 'name']);
        $collections = Collection::orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(function ($c) {
                $c->title = $c->title ?: ($c->name ?? '');

                return $c;
            });

        // Attach display_price for the Blade (use SQL-computed final_display_price)
        foreach ($products as $p) {
            $p->display_price = (float) ($p->final_display_price ?? 0);
        }
        $this->attachStockToProducts($products, $s->default_warehouse_id);

        return view('store.shop', [
            's' => $s,
            'products' => $products,
            'categories' => $categories,
            'collections' => $collections,
            'q' => $q,
            'cat' => $cat,
            'min' => $minPrice,
            'max' => $maxPrice,
            'sort' => $sort,
            'collection' => $coll,
            'showCategoryBar' => true,
        ]);
    }
 

    public function contact()
    {
        $s = StoreSetting::first();

        return view('store.contact', compact('s'));
    }

    /**
     * Attach stock (qty) to each product and its variants from product_warehouse for the given warehouse.
     * Product without variants: $p->stock. Variants: $v->stock (fallback to $v->qty if no warehouse row).
     */
    private function attachStockToProducts($products, ?int $warehouseId): void
    {
        if (! $warehouseId || ! $products) {
            foreach ($products as $p) {
                $p->stock = 0;
                if ($p->relationLoaded('variants') && $p->variants) {
                    foreach ($p->variants as $v) {
                        $v->stock = (float) ($v->qty ?? 0);
                    }
                }
            }

            return;
        }

        $items = $products instanceof \Illuminate\Pagination\AbstractPaginator ? $products->items() : $products;
        $productIds = collect($items)->pluck('id')->unique()->filter()->values()->all();
        if (empty($productIds)) {
            return;
        }

        $variantIds = [];
        foreach ($items as $p) {
            if ($p->relationLoaded('variants') && $p->variants) {
                foreach ($p->variants as $v) {
                    $variantIds[] = $v->id;
                }
            }
        }
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        $q = DB::table('product_warehouse')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds);
        if (count($variantIds) > 0) {
            $q->where(function ($qb) use ($variantIds) {
                $qb->whereNull('product_variant_id')
                    ->orWhereIn('product_variant_id', $variantIds);
            });
        } else {
            $q->whereNull('product_variant_id');
        }
        $rows = $q->when(Schema::hasColumn('product_warehouse', 'deleted_at'), fn ($qb) => $qb->whereNull('deleted_at'))
            ->select('product_id', 'product_variant_id', 'qte')
            ->get();

        $stockMap = [];
        foreach ($rows as $r) {
            $pid = (int) $r->product_id;
            $vid = $r->product_variant_id !== null ? (int) $r->product_variant_id : null;
            $key = $vid !== null ? "{$pid}:{$vid}" : "{$pid}:p";
            $stockMap[$key] = (float) $r->qte;
        }

        foreach ($items as $p) {
            $pid = (int) $p->id;
            if ($p->relationLoaded('variants') && $p->variants && $p->variants->isNotEmpty()) {
                // For products with variants, prefer variant-level stock rows.
                // If your DB only tracks product-level stock (product_variant_id NULL), use that as a fallback for each variant.
                $p->stock = null;
                $productFallback = $stockMap["{$pid}:p"] ?? null;
                foreach ($p->variants as $v) {
                    $key = "{$pid}:" . (int) $v->id;
                    if (array_key_exists($key, $stockMap)) {
                        $v->stock = (float) $stockMap[$key];
                    } elseif ($productFallback !== null) {
                        $v->stock = (float) $productFallback;
                    } else {
                        // Legacy fallback if a `qty` column exists on variants
                        $v->stock = (float) ($v->qty ?? 0);
                    }
                }
            } else {
                $p->stock = $stockMap["{$pid}:p"] ?? 0;
            }
        }
    }

    /**
     * Whether the product has at least one unit in stock (after attachStockToProducts).
     */
    private function productHasStock($p): bool
    {
        // Pre-order products should always be considered "available"
        if ($p->is_preorder) {
            return true;
        }

        if ($p->relationLoaded('variants') && $p->variants && $p->variants->isNotEmpty()) {
            return $p->variants->contains(fn ($v) => (float) ($v->stock ?? 0) > 0);
        }

        return (float) ($p->stock ?? 0) > 0;
    }

    /**
     * Product IDs that have at least one unit in stock in the given warehouse.
     * Used when hide_out_of_stock is enabled.
     */
    private function getInStockProductIds(int $warehouseId): array
    {
        $q = DB::table('product_warehouse')
            ->where('warehouse_id', $warehouseId)
            ->where('qte', '>', 0);
        if (Schema::hasColumn('product_warehouse', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        $inStockIds = $q->distinct()->pluck('product_id')->all();

        // Include pre-order products even when out of stock
        $preorderIds = DB::table('products')
            ->where('is_preorder', true)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($inStockIds, $preorderIds)));
    }
    /**
     * Search suggestions for autocomplete.
     */
    public function searchSuggestions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $s = StoreSetting::first();
        $warehouseId = $s->default_warehouse_id ?? null;

        $products = Product::query()
            ->where('is_active', 1)
            ->where('hide_from_online_store', 0)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%");
            })
            ->take(8)
            ->get(['id', 'name', 'code', 'image', 'price', 'tax_method', 'TaxNet', 'discount', 'discount_method']);

        foreach ($products as $p) {
            $p->loadMissing(['images' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
            $fn = $p->primaryProductImageFilename();
            $p->image_url = $fn ? asset('images/products/'.$fn) : asset('images/products/no-image.png');
            $p->display_price = $p->computeFinalPrice()['final'];
            $p->url = route('store.shop', ['q' => $p->name]); 
        }

        return response()->json($products);
    }
}
