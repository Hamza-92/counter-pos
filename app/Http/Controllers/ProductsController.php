<?php

namespace App\Http\Controllers;

use App\Exports\StockExport;
use App\Imports\OpeningStockRowsImport;
use App\Imports\ProductImport;
use App\Models\Brand;
use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\CombinedProduct;
use App\Models\CountStock;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\ProductWarehouseLocation;
use App\Services\ProductGalleryService;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManagerStatic as Image;

class ProductsController extends BaseController
{
    // ------------ Get ALL Products --------------\\

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        $perPage = $request->integer('limit', 10);
        $pageStart = (int) ($request->get('page', 1));
        $offSet = ($pageStart * max($perPage, 1)) - max($perPage, 1);
        $order = $request->get('SortField', 'id');
        $dir = $request->get('SortType', 'desc');

        $helpers = new helpers;
        $warehouseId = $request->integer('warehouse_id');

          // ✅ assigned warehouses (user scope)
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
            $allowedWarehouseIds = $warehouses->pluck('id')->toArray();
        } else {
            $allowedWarehouseIds = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $allowedWarehouseIds)->get(['id', 'name']);
        }

        // ✅ if warehouse_id provided but not allowed => ignore it (don’t break anything)
        if ($warehouseId && ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            $warehouseId = null;
        }

        $columns = [0 => 'name', 1 => 'category_id', 2 => 'brand_id', 3 => 'code', 4 => 'sub_category_id'];
        $param = [0 => 'like', 1 => '=', 2 => '=', 3 => 'like', 4 => '='];

        $productsQuery = Product::with('unit', 'category', 'subCategory', 'brand', 'categories')
            ->whereNull('deleted_at');
         


        // Multiple Filter
        $filtered = $helpers->filter($productsQuery, $columns, $param, $request)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    $s = $request->search;

                    return $query->where('products.name', 'LIKE', "%{$s}%")
                        ->orWhere('products.code', 'LIKE', "%{$s}%")
                        ->orWhere(function ($q) use ($s) {
                            $q->whereHas('category', function ($cq) use ($s) {
                                $cq->where('name', 'LIKE', "%{$s}%");
                            });
                        })
                        ->orWhere(function ($q) use ($s) {
                            $q->whereHas('categories', function ($cq) use ($s) {
                                $cq->where('name', 'LIKE', "%{$s}%");
                            });
                        })
                        ->orWhere(function ($q) use ($s) {
                            $q->whereHas('brand', function ($bq) use ($s) {
                                $bq->where('name', 'LIKE', "%{$s}%");
                            });
                        });
                });
            });

        // Optional status filter: status=1 (active), status=0 (inactive)
        if ($request->filled('status') && $request->status !== '') {
            $status = $request->status;
            if ($status === '1' || $status === 1 || $status === 'active') {
                $filtered->where('is_active', 1);
            } elseif ($status === '0' || $status === 0 || $status === 'inactive') {
                $filtered->where('is_active', 0);
            }
        }

        // Optional warehouse filter: only products that have stock rows in the given warehouse
        if ($warehouseId) {
            $filtered->whereExists(function ($q) use ($warehouseId) {
                $q->select(DB::raw(1))
                    ->from('product_warehouse')
                    ->whereColumn('product_warehouse.product_id', 'products.id')
                    ->where('product_warehouse.warehouse_id', $warehouseId)
                    ->whereNull('product_warehouse.deleted_at');
            });
        }

        $totalRows = (clone $filtered)->count();
        if ($perPage === -1) {
            $perPage = $totalRows;
        }

        $products = $filtered->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($products as $product) {
            $item = [];

            $item['id'] = $product->id;
            $item['code'] = $product->code;
            $item['category'] = optional($product->category)->name;
            $item['sub_category'] = optional($product->subCategory)->name;
            $item['categories_display'] = $product->categories->isNotEmpty()
                ? $product->categories->pluck('name')->filter()->unique()->values()->implode("\n")
                : ($item['category'] ?? '');
            $item['brand'] = $product->brand ? $product->brand->name : 'N/D';

            $isActive = (int) ($product->is_active ?? 1) === 1;
            $item['status'] = $isActive ? __('Active') : __('Inactif');
            $item['is_active'] = $isActive;

            $firstimage = explode(',', (string) $product->image);
            $item['image'] = $firstimage[0] ?? '';

            if ($product->type === 'is_single') {

                $item['type'] = 'Single';
                $item['name'] = $product->name; // PLAIN TEXT
                // IMPORTANT: keep backend numeric formatting *machine‑friendly*:
                // - 2 decimals
                // - NO thousands separator (third param '.', fourth param '')
                // So the frontend priceFormat helper can safely re‑format.
                $item['cost'] = number_format((float) $product->cost, 2, '.', '');
                $item['price'] = number_format((float) $product->price, 2, '.', '');
                $item['unit'] = optional($product->unit)->ShortName;

                $qtyQuery = product_warehouse::where('product_id', $product->id)
                    ->whereNull('deleted_at');
                if ($warehouseId) {
                    $qtyQuery->where('warehouse_id', $warehouseId);
                } elseif (! $user_auth->is_all_warehouses) {
                    // ✅ if no warehouse filter selected, keep qty within allowed warehouses only
                    $qtyQuery->whereIn('warehouse_id', $allowedWarehouseIds);
                }

                $qty = $qtyQuery->sum('qte');

                $item['quantity'] = number_format((float) $qty, 2, '.', '').' '.$item['unit'];

            } elseif ($product->type === 'is_combo') {

                $item['type'] = 'Combo';
                $item['name'] = $product->name; // PLAIN TEXT
                $item['cost'] = number_format((float) $product->cost, 2, '.', '');
                $item['price'] = number_format((float) $product->price, 2, '.', '');
                $item['unit'] = optional($product->unit)->ShortName;

                $qtyQuery = product_warehouse::where('product_id', $product->id)
                    ->whereNull('deleted_at');

                if ($warehouseId) {
                    $qtyQuery->where('warehouse_id', $warehouseId);
                } elseif (! $user_auth->is_all_warehouses) {
                    $qtyQuery->whereIn('warehouse_id', $allowedWarehouseIds);
                }

                $qty = $qtyQuery->sum('qte');

                $item['quantity'] = number_format((float) $qty, 2, '.', '').' '.$item['unit'];

            } elseif ($product->type === 'is_variant') {

                $item['type'] = 'Variable';

                $variants = ProductVariant::where('product_id', $product->id)
                    ->whereNull('deleted_at')
                    ->get();

                // For variant products, display the parent product name
                $item['name'] = $product->name;
                $item['cost'] = $variants
                    ->map(fn ($v) => number_format((float) $v->cost, 2, '.', ''))
                    ->implode("\n");
                $item['price'] = $variants
                    ->map(fn ($v) => number_format((float) $v->price, 2, '.', ''))
                    ->implode("\n");
                $item['unit'] = optional($product->unit)->ShortName;

                $qtyQuery = product_warehouse::where('product_id', $product->id)
                    ->whereNull('deleted_at');

                if ($warehouseId) {
                    $qtyQuery->where('warehouse_id', $warehouseId);
                } elseif (! $user_auth->is_all_warehouses) {
                    $qtyQuery->whereIn('warehouse_id', $allowedWarehouseIds);
                }

                $qty = $qtyQuery->sum('qte');

                $item['quantity'] = number_format((float) $qty, 2, '.', '').' '.$item['unit'];

            } else {

                $item['type'] = 'Service';
                $item['name'] = $product->name; // PLAIN TEXT
                $item['cost'] = '----';
                $item['quantity'] = '----';
                $item['unit'] = '----';
                $item['price'] = number_format((float) $product->price, 2, '.', '');
            }

            $data[] = $item;
        }

        // warehouses for user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $categories = Category::whereNull('deleted_at')->get(['id', 'name']);
        $subcategories = SubCategory::orderBy('name')->get(['id', 'name', 'category_id']);
        $brands = Brand::whereNull('deleted_at')->get(['id', 'name']);

        return response()->json([
            'warehouses' => $warehouses,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'products' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    // ------------------------------- WAREHOUSE LOCATIONS (quick-add from product forms) --------------------------\\
    // This endpoint exists to allow creating locations from Create/Edit Product without relying on
    // WarehouseLocationController authorization.
    public function storeWarehouseLocation(Request $request)
    {
        // Allow users who can create products to add location hints
        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $allowedWarehouseIds = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();
        } else {
            $allowedWarehouseIds = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
        }

        $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::in($allowedWarehouseIds)],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('warehouse_locations')->where(function ($q) use ($request) {
                    return $q->whereNull('deleted_at')
                        ->where('warehouse_id', $request->warehouse_id);
                }),
            ],
            'name' => ['nullable', 'string', 'max:192'],
        ]);

        $loc = WarehouseLocation::create([
            'warehouse_id' => (int) $request->warehouse_id,
            'code' => trim((string) $request->code),
            'name' => $request->name ? trim((string) $request->name) : null,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'location' => $loc,
        ]);
    }

    // -------------- Store new  Product  ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        try {

            // define validation rules for product
            $productRules = [
                'code' => [
                    'required',
                    Rule::unique('products')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[^<>]*$/', // prevents <script>, <details>, etc.
                ],
                'Type_barcode' => 'required',
                'category_id' => 'required',
                'type' => 'required',
                'tax_method' => 'required',
                'discount_method' => 'required',
                'sub_category_id' => 'nullable|integer',
                'sub_category_id' => 'nullable|integer',
                'unit_id' => Rule::requiredIf($request->type != 'is_service'),
                'cost' => Rule::requiredIf($request->type == 'is_single' || $request->type == 'is_combo'),
                'price' => Rule::requiredIf($request->type != 'is_variant'),
            ];

            // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');

                            return;
                        }

                        // check for duplicate codes in variants array
                        $variants = json_decode($request->variants, true);

                        if ($variants) {
                            foreach ($variants as $variant) {
                                if (! array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('The variants data is invalid.');

                            return;
                        }

                        // check if variant name empty
                        $names = array_column($variants, 'text');
                        if ($names) {
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Name cannot be empty.');

                            return;
                        }

                        // check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if ($all_cost) {
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Cost cannot be empty.');

                            return;
                        }

                        // check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if ($all_price) {
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Price cannot be empty.');

                            return;
                        }

                        // check if code empty
                        $codes = array_column($variants, 'code');
                        if ($codes) {
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant code cannot be empty.');

                            return;
                        }

                        // check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');

                            return;
                        }

                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (! empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (! empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }

            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique' => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);

            \DB::transaction(function () use ($request) {

                // -- Create New Product
                $Product = new Product;

                // -- Field Required
                $Product->type = $request['type'];
                $Product->name = $request['name'];
                $Product->code = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id = $request['category_id'];
                $Product->sub_category_id = $request['sub_category_id'] ?? null;
                $Product->brand_id = $request['brand_id'];
                $Product->note = $request['note'];
                $Product->TaxNet = $request['TaxNet'] ? $request['TaxNet'] : 0;
                $Product->tax_method = $request['tax_method'];
                $Product->discount = $request['discount'] ? $request['discount'] : 0;
                $Product->discount_method = $request['discount_method'];

                $Product->points = $request['points'] ? $request['points'] : 0;

                // —————— Warranty & Guarantee ——————
                $Product->warranty_period = $request['warranty_period'] ?? null;
                $Product->warranty_unit = $request['warranty_unit'] ?? null;
                $Product->warranty_terms = $request['warranty_terms'] ?? null;

                // casted boolean
                $Product->has_guarantee = filter_var($request['has_guarantee'], FILTER_VALIDATE_BOOLEAN);
                $Product->guarantee_period = $request['guarantee_period'] ?? null;
                $Product->guarantee_unit = $request['guarantee_unit'] ?? null;

                // -- check if type is_single
                if ($request['type'] == 'is_single' || $request['type'] == 'is_combo') {
                    $Product->price = $request['price'];
                    // ✅ Handle new prices safely (default to 0)
                    $Product->wholesale_price = ! empty($request['wholesale_price']) ? $request['wholesale_price'] : 0;
                    $Product->min_price = ! empty($request['min_price']) ? $request['min_price'] : 0;

                    $Product->cost = $request['cost'];

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->weight = $request['weight'] ? $request['weight'] : null;
                    $Product->length = $request->filled('length') ? $request['length'] : null;
                    $Product->width = $request->filled('width') ? $request['width'] : null;
                    $Product->height = $request->filled('height') ? $request['height'] : null;

                    $manage_stock = 1;

                    // -- check if type is_variant
                } elseif ($request['type'] == 'is_variant') {

                    $Product->price = 0;
                    $Product->cost = 0;
                    $Product->wholesale_price = 0;
                    $Product->min_price = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->weight = $request['weight'] ? $request['weight'] : null;
                    $Product->length = $request->filled('length') ? $request['length'] : null;
                    $Product->width = $request->filled('width') ? $request['width'] : null;
                    $Product->height = $request->filled('height') ? $request['height'] : null;

                    $manage_stock = 1;

                    // -- check if type is_service
                } else {
                    $Product->price = $request['price'];
                    $Product->wholesale_price = ! empty($request['wholesale_price']) ? $request['wholesale_price'] : 0;
                    $Product->min_price = ! empty($request['min_price']) ? $request['min_price'] : 0;
                    $Product->cost = 0;

                    $Product->unit_id = null;
                    $Product->unit_sale_id = null;
                    $Product->unit_purchase_id = null;

                    $Product->stock_alert = 0;
                    $Product->weight = null;

                    $manage_stock = 0;

                }

                $Product->is_variant = $request['is_variant'] == 'true' ? 1 : 0;
                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;
                $Product->is_active = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $Product->is_featured = $request['is_featured'] == 'true' ? 1 : 0;
                $Product->hide_from_online_store = $request['hide_from_online_store'] == 'true' ? 1 : 0;

                $Product->is_preorder = filter_var($request->input('is_preorder', false), FILTER_VALIDATE_BOOLEAN);
                $Product->preorder_available_date = $Product->is_preorder && $request->filled('preorder_available_date') && $request['preorder_available_date'] !== 'null'
                    ? $request['preorder_available_date'] : null;
                $Product->preorder_limit = $Product->is_preorder && $request->filled('preorder_limit') && $request['preorder_limit'] !== '' && $request['preorder_limit'] !== 'null'
                    ? (int) $request['preorder_limit'] : null;
                $Product->preorder_note = $Product->is_preorder && $request->filled('preorder_note') && $request['preorder_note'] !== 'null'
                    ? $request['preorder_note'] : null;

                // Pharmacy / Batch & Expiry tracking (per-product opt-in; defaults preserve legacy behavior)
                $Product->is_batch_tracked = filter_var($request->input('is_batch_tracked', false), FILTER_VALIDATE_BOOLEAN);
                $Product->shelf_life_days = $request->filled('shelf_life_days') && $request['shelf_life_days'] !== 'null' && $request['shelf_life_days'] !== ''
                    ? (int) $request['shelf_life_days'] : null;
                $Product->generic_name = $request->filled('generic_name') && $request['generic_name'] !== 'null' ? $request['generic_name'] : null;
                $Product->strength = $request->filled('strength') && $request['strength'] !== 'null' ? $request['strength'] : null;
                $Product->dosage_form = $request->filled('dosage_form') && $request['dosage_form'] !== 'null' ? $request['dosage_form'] : null;
                $Product->pack_size = $request->filled('pack_size') && $request['pack_size'] !== 'null' ? $request['pack_size'] : null;
                $Product->manufacturer = $request->filled('manufacturer') && $request['manufacturer'] !== 'null' ? $request['manufacturer'] : null;
                $Product->prescription_required = filter_var($request->input('prescription_required', false), FILTER_VALIDATE_BOOLEAN);
                $Product->drug_schedule = $request->filled('drug_schedule') && $request['drug_schedule'] !== 'null' ? $request['drug_schedule'] : null;

                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    $filename = rand(11111111, 99999999).'_'.$image->getClientOriginalName();

                    $image_resize = Image::make($image->getRealPath());

                    // Resize to one standard size (800x800)
                    $image_resize->resize(800, 800, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->save(public_path('/images/products/'.$filename));

                } else {
                    $filename = 'no-image.png';
                }

                $Product->image = $filename;
                $Product->save();

                $this->syncProductMultiCategories($request, $Product);

                app(ProductGalleryService::class)->syncAfterCreate($request, $Product, $filename);

                if ($request['type'] == 'is_combo') {
                    $materiels = json_decode($request['materiels'], true);

                    $syncData = [];
                    foreach ($materiels as $materiel) {
                        $syncData[$materiel['product_id']] = ['quantity' => $materiel['quantity']];
                    }

                    // Sync the combined products
                    $Product->combinedProducts()->sync($syncData);
                }

                // Store Variants Product
                if ($request['type'] == 'is_variant') {
                    $variants = json_decode($request->variants);

                    $hasWholesaleColumn = Schema::hasColumn('product_variants', 'wholesale');
                    $hasMinPriceColumn = Schema::hasColumn('product_variants', 'min_price');
                    $Product_variants_data = [];
                    foreach ($variants as $variant) {
                        $row = [
                            'product_id' => $Product->id,
                            'name' => $variant->text,
                            'cost' => $variant->cost,
                            'price' => $variant->price,
                            'code' => $variant->code,
                        ];
                        if ($hasWholesaleColumn) {
                            $row['wholesale'] = isset($variant->wholesale) && $variant->wholesale !== ''
                                ? $variant->wholesale
                                : 0;
                        }
                        if ($hasMinPriceColumn) {
                            $row['min_price'] = isset($variant->min_price) && $variant->min_price !== ''
                                ? $variant->min_price
                                : 0;
                        }
                        $Product_variants_data[] = $row;
                    }
                    if (! empty($Product_variants_data)) {
                        ProductVariant::insert($Product_variants_data);
                    }
                }

                // 1) gather all warehouse IDs
                $warehouseIds = Warehouse::whereNull('deleted_at')
                    ->pluck('id')
                    ->toArray();

                if ($warehouseIds) {
                    $isSingle = $request->input('type') === 'is_single';
                    $isVariant = $request->input('is_variant') === 'true';

                    // fetch variants if needed
                    $variants = $isVariant
                        ? ProductVariant::where('product_id', $Product->id)
                            ->whereNull('deleted_at')
                            ->get()
                        : collect();

                    // decode the JSON blob you appended from the frontend
                    $payloadWs = json_decode($request->input('warehouses', '[]'), true);

                    $insertRows = [];

                    foreach ($warehouseIds as $wid) {
                        // grab or default
                        $whData = $payloadWs[$wid] ?? [];

                        $qty = $isSingle
                        ? (float) ($whData['qte'] ?? 0)
                        : 0;

                        if ($isVariant) {
                            foreach ($variants as $variant) {
                                $insertRows[] = [
                                    'product_id' => $Product->id,
                                    'warehouse_id' => $wid,
                                    'product_variant_id' => $variant->id,
                                    'manage_stock' => $manage_stock,
                                    'qte' => $qty,
                                ];
                            }
                        } else {
                            $insertRows[] = [
                                'product_id' => $Product->id,
                                'warehouse_id' => $wid,
                                'manage_stock' => $manage_stock,
                                'qte' => $qty,
                            ];
                        }
                    }

                    // bulk insert
                    product_warehouse::insert($insertRows);

                    // Store default warehouse location (Rack/Location) per warehouse (no stock-by-location)
                    $now = now();
                    $pwlRows = [];
                    foreach ($warehouseIds as $wid) {
                        $whData = $payloadWs[$wid] ?? [];
                        $locationId = $whData['warehouse_location_id'] ?? null;

                        $pwlRows[] = [
                            'product_id' => $Product->id,
                            'warehouse_id' => $wid,
                            'warehouse_location_id' => $locationId ?: null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (! empty($pwlRows)) {
                        ProductWarehouseLocation::insert($pwlRows);
                    }

                    /**
                     * Step 5: record opening stock as "virtual" adjustments for COGS/average-cost only.
                     *
                     * We:
                     *  - do NOT touch product_warehouse here (it was already populated above),
                     *  - create minimal Adjustment + AdjustmentDetail rows so ReportController::averageCostBulk
                     *    can see an initial quantity and value when there are no purchases yet.
                     *
                     * This keeps existing stock logic intact while giving the Profit & Loss report
                     * a proper cost basis for products created with opening stock.
                     */
                    if ($isSingle && $manage_stock && is_array($payloadWs) && ! empty($payloadWs)) {
                        foreach ($warehouseIds as $wid) {
                            $whData = $payloadWs[$wid] ?? null;
                            $openingQty = $whData && isset($whData['qte']) ? (float) $whData['qte'] : 0.0;

                            if ($openingQty <= 0) {
                                continue;
                            }

                            // Create a lightweight adjustment header per warehouse
                            $adjRef = app('App\Http\Controllers\AdjustmentController')->getNumberOrder();

                            $adjustment = Adjustment::create([
                                'date' => now()->toDateString(),
                                'time' => now()->toTimeString(),
                                'Ref' => $adjRef,
                                'warehouse_id' => $wid,
                                'items' => 1,
                                'notes' => 'Opening stock (auto)',
                                'user_id' => Auth::id(),
                            ]);

                            // Single detail row using type "add" so averageCostBulk values it as positive qty
                            AdjustmentDetail::create([
                                'adjustment_id' => $adjustment->id,
                                'product_id' => $Product->id,
                                'product_variant_id' => null,
                                'quantity' => $openingQty,
                                'type' => 'add',
                            ]);
                        }
                    }
                }

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    // -------------- Update Product  ---------------\\
    // -----------------------------------------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);
        try {

            // define validation rules for product
            $productRules = [
                'code' => [
                    'required',

                    Rule::unique('products')->ignore($id)->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->ignore($id, 'product_id')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[^<>]*$/', // prevents <script>, <details>, etc.
                ],
                'category_id' => 'required',
                'tax_method' => 'required',
                'discount_method' => 'required',
                'type' => 'required',
                'unit_id' => Rule::requiredIf($request->type != 'is_service'),
                'cost' => Rule::requiredIf($request->type == 'is_single' || $request->type == 'is_combo'),
                'price' => Rule::requiredIf($request->type != 'is_variant'),
            ];

            // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request, $id) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');

                            return;
                        }
                        // check for duplicate codes in variants array
                        $variants = $request->variants;

                        if ($variants) {
                            foreach ($variants as $variant) {
                                if (! array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');

                                    return;
                                } elseif (! array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('The variants data is invalid.');

                            return;
                        }

                        // check if variant name empty
                        $names = array_column($variants, 'text');
                        if ($names) {
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Name cannot be empty.');

                            return;
                        }

                        // check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if ($all_cost) {
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Cost cannot be empty.');

                            return;
                        }

                        // check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if ($all_price) {
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant Price cannot be empty.');

                            return;
                        }

                        // check if code empty
                        $codes = array_column($variants, 'code');
                        if ($codes) {
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');

                                    return;
                                }
                            }
                        } else {
                            $fail('Variant code cannot be empty.');

                            return;
                        }

                        // check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');

                            return;
                        }

                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->where(function ($query) use ($id) {
                                $query->where('product_id', '<>', $id);
                            })
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (! empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->where('id', '!=', $id)
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (! empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }

            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique' => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);

            \DB::transaction(function () use ($request, $id) {

                $Product = Product::where('id', $id)
                    ->where('deleted_at', '=', null)
                    ->first();

                // -- Update Product
                $Product->type = $request['type'];
                $Product->name = $request['name'];
                $Product->code = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id = $request['category_id'];
                // normalize optional sub-category (can be null/empty)
                $Product->sub_category_id = isset($request['sub_category_id']) && $request['sub_category_id'] !== '' && $request['sub_category_id'] !== 'null'
                    ? $request['sub_category_id']
                    : null;
                $Product->brand_id = $request['brand_id'] == 'null' ? null : $request['brand_id'];
                $Product->TaxNet = $request['TaxNet'];
                $Product->tax_method = $request['tax_method'];
                $Product->discount = $request['discount'];
                $Product->discount_method = $request['discount_method'];
                $Product->note = $request['note'];
                $Product->points = $request['points'];

                // ——— Warranty & Guarantee Tracking ———

                // Warranty
                $Product->warranty_period = $request['warranty_period'] !== null
                ? (int) $request['warranty_period']
                : null;
                $Product->warranty_unit = $request['warranty_unit'] ?? null;
                $Product->warranty_terms = $request['warranty_terms'] ?? null;

                // Guarantee
                // If your form posts 'has_guarantee' only when checked, you might need:
                $Product->has_guarantee = filter_var($request['has_guarantee'], FILTER_VALIDATE_BOOLEAN);

                $Product->guarantee_period = $request['guarantee_period'] !== null
                ? (int) $request['guarantee_period']
                : null;
                $Product->guarantee_unit = $request['guarantee_unit'] ?? null;

                // -- check if type is_single
                if ($request['type'] == 'is_single' || $request['type'] == 'is_combo') {
                    $Product->price = $request['price'];
                    $Product->cost = $request['cost'];
                    $Product->wholesale_price = ! empty($request['wholesale_price']) ? $request['wholesale_price'] : 0;
                    $Product->min_price = ! empty($request['min_price']) ? $request['min_price'] : 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->weight = $request['weight'] ? $request['weight'] : null;
                    $Product->length = $request->filled('length') ? $request['length'] : null;
                    $Product->width = $request->filled('width') ? $request['width'] : null;
                    $Product->height = $request->filled('height') ? $request['height'] : null;
                    $Product->is_variant = 0;

                    $manage_stock = 1;

                    // -- check if type is_variant
                } elseif ($request['type'] == 'is_variant') {

                    $Product->price = 0;
                    $Product->cost = 0;
                    $Product->wholesale_price = 0;
                    $Product->min_price = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->weight = $request['weight'] ? $request['weight'] : null;
                    $Product->length = $request->filled('length') ? $request['length'] : null;
                    $Product->width = $request->filled('width') ? $request['width'] : null;
                    $Product->height = $request->filled('height') ? $request['height'] : null;
                    $Product->is_variant = 1;
                    $manage_stock = 1;

                    // -- check if type is_service
                } else {
                    $Product->price = $request['price'];
                    $Product->cost = 0;
                    $Product->wholesale_price = ! empty($request['wholesale_price']) ? $request['wholesale_price'] : 0;
                    $Product->min_price = ! empty($request['min_price']) ? $request['min_price'] : 0;

                    $Product->unit_id = null;
                    $Product->unit_sale_id = null;
                    $Product->unit_purchase_id = null;

                    $Product->stock_alert = 0;
                    $Product->weight = null;
                    $Product->length = null;
                    $Product->width = null;
                    $Product->height = null;
                    $Product->is_variant = 0;
                    $manage_stock = 0;

                }

                if ($request['type'] == 'is_combo') {
                    $materiels = json_decode($request['materiels'], true);

                    $syncData = [];
                    foreach ($materiels as $materiel) {
                        $syncData[$materiel['product_id']] = ['quantity' => $materiel['quantity']];
                    }

                    // Sync the combined products
                    $Product->combinedProducts()->sync($syncData);
                }

                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;
                $Product->is_active = filter_var($request->input('is_active', $Product->is_active ?? 1), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $Product->is_featured = $request['is_featured'] == 'true' ? 1 : 0;
                $Product->hide_from_online_store = $request['hide_from_online_store'] == 'true' ? 1 : 0;

                $Product->is_preorder = filter_var($request->input('is_preorder', false), FILTER_VALIDATE_BOOLEAN);
                $Product->preorder_available_date = $Product->is_preorder && $request->filled('preorder_available_date') && $request['preorder_available_date'] !== 'null'
                    ? $request['preorder_available_date'] : null;
                $Product->preorder_limit = $Product->is_preorder && $request->filled('preorder_limit') && $request['preorder_limit'] !== '' && $request['preorder_limit'] !== 'null'
                    ? (int) $request['preorder_limit'] : null;
                $Product->preorder_note = $Product->is_preorder && $request->filled('preorder_note') && $request['preorder_note'] !== 'null'
                    ? $request['preorder_note'] : null;

                // Pharmacy / Batch & Expiry tracking (per-product opt-in; defaults preserve legacy behavior)
                $Product->is_batch_tracked = filter_var($request->input('is_batch_tracked', false), FILTER_VALIDATE_BOOLEAN);
                $Product->shelf_life_days = $request->filled('shelf_life_days') && $request['shelf_life_days'] !== 'null' && $request['shelf_life_days'] !== ''
                    ? (int) $request['shelf_life_days'] : null;
                $Product->generic_name = $request->filled('generic_name') && $request['generic_name'] !== 'null' ? $request['generic_name'] : null;
                $Product->strength = $request->filled('strength') && $request['strength'] !== 'null' ? $request['strength'] : null;
                $Product->dosage_form = $request->filled('dosage_form') && $request['dosage_form'] !== 'null' ? $request['dosage_form'] : null;
                $Product->pack_size = $request->filled('pack_size') && $request['pack_size'] !== 'null' ? $request['pack_size'] : null;
                $Product->manufacturer = $request->filled('manufacturer') && $request['manufacturer'] !== 'null' ? $request['manufacturer'] : null;
                $Product->prescription_required = filter_var($request->input('prescription_required', false), FILTER_VALIDATE_BOOLEAN);
                $Product->drug_schedule = $request->filled('drug_schedule') && $request['drug_schedule'] !== 'null' ? $request['drug_schedule'] : null;

                // Store Variants Product
                $oldVariants = ProductVariant::where('product_id', $id)
                    ->where('deleted_at', null)
                    ->get();

                $warehouses = Warehouse::where('deleted_at', null)
                    ->pluck('id')
                    ->toArray();

                if ($request['type'] == 'is_variant') {

                    if ($oldVariants->isNotEmpty()) {
                        $new_variants_id = [];
                        $var = 'id';

                        foreach ($request['variants'] as $new_id) {
                            if (array_key_exists($var, $new_id)) {
                                $new_variants_id[] = $new_id['id'];
                            } else {
                                $new_variants_id[] = 0;
                            }
                        }

                        foreach ($oldVariants as $key => $value) {
                            $old_variants_id[] = $value->id;

                            // Delete Variant
                            if (! in_array($old_variants_id[$key], $new_variants_id)) {
                                $ProductVariant = ProductVariant::findOrFail($value->id);
                                $ProductVariant->deleted_at = Carbon::now();
                                $ProductVariant->save();

                                $ProductWarehouse = product_warehouse::where('product_variant_id', $value->id)
                                    ->update(['deleted_at' => Carbon::now()]);
                            }
                        }

                        foreach ($request['variants'] as $key => $variant) {
                            if (array_key_exists($var, $variant)) {

                                $ProductVariantDT = new ProductVariant;
                                // -- Field Required
                                $ProductVariantDT->product_id = $variant['product_id'];
                                $ProductVariantDT->name = $variant['text'];
                                $ProductVariantDT->price = $variant['price'];
                                $ProductVariantDT->cost = $variant['cost'];
                                $ProductVariantDT->code = $variant['code'];
                                if (Schema::hasColumn('product_variants', 'wholesale')) {
                                    $ProductVariantDT->wholesale = isset($variant['wholesale']) && $variant['wholesale'] !== ''
                                        ? $variant['wholesale']
                                        : 0;
                                }
                                if (Schema::hasColumn('product_variants', 'min_price')) {
                                    $ProductVariantDT->min_price = isset($variant['min_price']) && $variant['min_price'] !== ''
                                        ? $variant['min_price']
                                        : 0;
                                }

                                $ProductVariantUP['product_id'] = $variant['product_id'];
                                $ProductVariantUP['code'] = $variant['code'];
                                $ProductVariantUP['name'] = $variant['text'];
                                $ProductVariantUP['price'] = $variant['price'];
                                $ProductVariantUP['cost'] = $variant['cost'];
                                if (Schema::hasColumn('product_variants', 'wholesale')) {
                                    $ProductVariantUP['wholesale'] = isset($variant['wholesale']) && $variant['wholesale'] !== ''
                                        ? $variant['wholesale']
                                        : 0;
                                }
                                if (Schema::hasColumn('product_variants', 'min_price')) {
                                    $ProductVariantUP['min_price'] = isset($variant['min_price']) && $variant['min_price'] !== ''
                                        ? $variant['min_price']
                                        : 0;
                                }

                            } else {
                                $ProductVariantDT = new ProductVariant;

                                // -- Field Required
                                $ProductVariantDT->product_id = $id;
                                $ProductVariantDT->code = $variant['code'];
                                $ProductVariantDT->name = $variant['text'];
                                $ProductVariantDT->price = $variant['price'];
                                $ProductVariantDT->cost = $variant['cost'];
                                if (Schema::hasColumn('product_variants', 'wholesale')) {
                                    $ProductVariantDT->wholesale = isset($variant['wholesale']) && $variant['wholesale'] !== ''
                                        ? $variant['wholesale']
                                        : 0;
                                }
                                if (Schema::hasColumn('product_variants', 'min_price')) {
                                    $ProductVariantDT->min_price = isset($variant['min_price']) && $variant['min_price'] !== ''
                                        ? $variant['min_price']
                                        : 0;
                                }

                                $ProductVariantUP['product_id'] = $id;
                                $ProductVariantUP['code'] = $variant['code'];
                                $ProductVariantUP['name'] = $variant['text'];
                                $ProductVariantUP['price'] = $variant['price'];
                                $ProductVariantUP['cost'] = $variant['cost'];
                                $ProductVariantUP['qty'] = 0.00;
                                if (Schema::hasColumn('product_variants', 'wholesale')) {
                                    $ProductVariantUP['wholesale'] = isset($variant['wholesale']) && $variant['wholesale'] !== ''
                                        ? $variant['wholesale']
                                        : 0;
                                }
                                if (Schema::hasColumn('product_variants', 'min_price')) {
                                    $ProductVariantUP['min_price'] = isset($variant['min_price']) && $variant['min_price'] !== ''
                                        ? $variant['min_price']
                                        : 0;
                                }
                            }

                            if (! in_array($new_variants_id[$key], $old_variants_id)) {
                                $ProductVariantDT->save();

                                // --Store Product warehouse
                                if ($warehouses) {
                                    $product_warehouse = [];
                                    foreach ($warehouses as $warehouse) {

                                        $product_warehouse[] = [
                                            'product_id' => $id,
                                            'warehouse_id' => $warehouse,
                                            'product_variant_id' => $ProductVariantDT->id,
                                            'manage_stock' => $manage_stock,
                                        ];

                                    }
                                    product_warehouse::insert($product_warehouse);
                                }
                            } else {
                                ProductVariant::where('id', $variant['id'])->update($ProductVariantUP);
                            }
                        }

                    } else {
                        $ProducttWarehouse = product_warehouse::where('product_id', $id)
                            ->update([
                                'deleted_at' => Carbon::now(),
                            ]);

                        foreach ($request['variants'] as $variant) {
                            $product_warehouse_DT = [];
                            $ProductVarDT = new ProductVariant;

                            // -- Field Required
                            $ProductVarDT->product_id = $id;
                            $ProductVarDT->code = $variant['code'];
                            $ProductVarDT->name = $variant['text'];
                            $ProductVarDT->cost = $variant['cost'];
                            $ProductVarDT->price = $variant['price'];
                            if (Schema::hasColumn('product_variants', 'wholesale')) {
                                $ProductVarDT->wholesale = isset($variant['wholesale']) && $variant['wholesale'] !== ''
                                    ? $variant['wholesale']
                                    : 0;
                            }
                            if (Schema::hasColumn('product_variants', 'min_price')) {
                                $ProductVarDT->min_price = isset($variant['min_price']) && $variant['min_price'] !== ''
                                    ? $variant['min_price']
                                    : 0;
                            }
                            $ProductVarDT->save();

                            // -- Store Product warehouse
                            if ($warehouses) {
                                foreach ($warehouses as $warehouse) {

                                    $product_warehouse_DT[] = [
                                        'product_id' => $id,
                                        'warehouse_id' => $warehouse,
                                        'product_variant_id' => $ProductVarDT->id,
                                        'manage_stock' => $manage_stock,
                                    ];
                                }

                                product_warehouse::insert($product_warehouse_DT);
                            }
                        }

                    }
                } else {
                    if ($oldVariants->isNotEmpty()) {
                        foreach ($oldVariants as $old_var) {
                            $var_old = ProductVariant::where('product_id', $old_var['product_id'])
                                ->where('deleted_at', null)
                                ->first();
                            $var_old->deleted_at = Carbon::now();
                            $var_old->save();

                            $ProducttWarehouse = product_warehouse::where('product_variant_id', $old_var['id'])
                                ->update([
                                    'deleted_at' => Carbon::now(),
                                ]);
                        }

                        if ($warehouses) {
                            foreach ($warehouses as $warehouse) {

                                $product_warehouse[] = [
                                    'product_id' => $id,
                                    'warehouse_id' => $warehouse,
                                    'product_variant_id' => null,
                                    'manage_stock' => $manage_stock,
                                ];

                            }
                            product_warehouse::insert($product_warehouse);
                        }
                    }
                }

                $currentImage = $Product->image;

                if ($currentImage && $request->hasFile('image')) {

                    $image = $request->file('image');
                    $path = public_path('/images/products');
                    $filename = rand(11111111, 99999999).'_'.$image->getClientOriginalName();

                    // Resize to one standard size (800x800)
                    $image_resize = Image::make($image->getRealPath());
                    $image_resize->resize(800, 800, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->save($path.'/'.$filename);

                    // Delete old image if it exists and is not the placeholder
                    $oldImage = $path.'/'.$currentImage;
                    if ($currentImage && $currentImage != 'no-image.png' && file_exists($oldImage)) {
                        @unlink($oldImage);
                    }

                } elseif (! $currentImage && $request->hasFile('image')) {

                    $image = $request->file('image');
                    $path = public_path('/images/products');
                    $filename = rand(11111111, 99999999).'_'.$image->getClientOriginalName();

                    $image_resize = Image::make($image->getRealPath());
                    $image_resize->resize(800, 800, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->save($path.'/'.$filename);

                } else {
                    // Keep existing image or fallback to default
                    $filename = $currentImage ? $currentImage : 'no-image.png';
                }

                $Product->image = $filename;
                $Product->save();

                $this->syncProductMultiCategories($request, $Product);

                app(ProductGalleryService::class)->syncAfterUpdate($request, $Product);

                // Update default warehouse location (Rack/Location) per warehouse (no stock-by-location)
                $payloadLoc = json_decode($request->input('warehouse_locations', '{}'), true);
                if (is_array($payloadLoc)) {
                    $user_auth = auth()->user();
                    if ($user_auth->is_all_warehouses) {
                        $warehouseIds = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();
                    } else {
                        $warehouseIds = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                    }

                    foreach ($warehouseIds as $wid) {
                        $entry = $payloadLoc[$wid] ?? null;
                        $locId = is_array($entry) ? ($entry['warehouse_location_id'] ?? null) : $entry;
                        ProductWarehouseLocation::updateOrCreate(
                            ['product_id' => $Product->id, 'warehouse_id' => $wid],
                            ['warehouse_location_id' => $locId ?: null]
                        );
                    }
                }

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    // -------------- Remove Product  ---------------\\
    // -----------------------------------------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($id) {

            $Product = Product::findOrFail($id);

            if (Schema::hasTable('category_product')) {
                $Product->categories()->detach();
            }
            if (Schema::hasTable('product_subcategory')) {
                $Product->subcategories()->detach();
            }

            ProductGalleryService::deleteAllForProduct($Product);

            $pathIMG = public_path().'/images/products/'.$Product->image;
            if (file_exists($pathIMG)) {
                if ($Product->image != 'no-image.png') {
                    @unlink($pathIMG);
                }
            }

            $Product->deleted_at = Carbon::now();
            $Product->save();

            product_warehouse::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            ProductVariant::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);

    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($request) {
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $product_id) {

                $Product = Product::findOrFail($product_id);
                $Product->deleted_at = Carbon::now();

                if (Schema::hasTable('category_product')) {
                    $Product->categories()->detach();
                }
                if (Schema::hasTable('product_subcategory')) {
                    $Product->subcategories()->detach();
                }

                ProductGalleryService::deleteAllForProduct($Product);

                $pathIMG = public_path().'/images/products/'.$Product->image;
                if (file_exists($pathIMG)) {
                    if ($Product->image != 'no-image.png') {
                        @unlink($pathIMG);
                    }
                }

                $Product->save();

                product_warehouse::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);

                ProductVariant::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);

    }

    // --------------  Show Product Details ---------------\\

    public function Get_Products_Details(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Product::class);
        $helpers = new helpers;

        $Product = Product::with(['category', 'categories', 'subCategory', 'subcategories', 'images'])
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['points'] = $Product->points;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;
        $item['note'] = $Product->note;
        $item['category'] = optional($Product->category)->name;
        $item['categories'] = $Product->apiCategoriesList();
        $item['sub_category'] = optional($Product->subCategory)->name;
        $item['subcategories'] = $Product->apiSubcategoriesList();
        $item['brand'] = $Product['brand'] ? $Product['brand']->name : 'N/D';
        $item['price'] = $Product->price;
        $item['wholesale_price'] = $Product->wholesale_price;
        $item['min_price'] = $Product->min_price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['weight'] = $Product->weight;
        $item['taxe'] = $Product->TaxNet;
        $item['tax_method'] = $Product->tax_method === 1 ? 'Exclusive' : 'Inclusive';
        $symbol = $helpers->Get_Currency_Code();
        $item['discount'] = $Product->discount_method === 1 ? $Product->discount.' '.'%' : $Product->discount.' '.$symbol;

        // ——— Warranty & Guarantee ———
        $item['warranty_period'] = $Product->warranty_period;
        $item['warranty_unit'] = $Product->warranty_unit;
        $item['warranty_terms'] = $Product->warranty_terms;

        $item['has_guarantee'] = (bool) $Product->has_guarantee;
        $item['guarantee_period'] = $Product->guarantee_period;
        $item['guarantee_unit'] = $Product->guarantee_unit;

        // Pharmacy: surface batch tracking flag so the detail UI can render the Batches section.
        $item['is_batch_tracked'] = (bool) ($Product->is_batch_tracked ?? false);

        if ($Product->type == 'is_single') {
            $item['type_name'] = 'Single';
            $item['unit'] = $Product['unit']->ShortName;

        } elseif ($Product->type == 'is_variant') {
            $item['type_name'] = 'Variable';
            $item['unit'] = $Product['unit']->ShortName;

        } else {
            $item['type_name'] = 'Service';
            $item['unit'] = '----';

        }

        if ($Product->type == 'is_combo') {
            $combined_products = CombinedProduct::where('product_id', $id)->with('product.unit')->get();

            $materiels = $combined_products->map(function ($combinedProduct) {
                return [
                    'name' => $combinedProduct->product->name,
                    'code' => $combinedProduct->product->code,
                    'quantity' => $combinedProduct->quantity,
                ];
            });

            $item['products_combo_data'] = $materiels;

        }

        if ($Product->is_variant) {
            $item['is_variant'] = 'yes';
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();
            foreach ($productsVariants as $variant) {
                $ProductVariant['code'] = $variant->code;
                $ProductVariant['name'] = $variant->name;
                $ProductVariant['cost'] = number_format($variant->cost, 2, '.', ',');
                $ProductVariant['price'] = number_format($variant->price, 2, '.', ',');
                $ProductVariant['wholesale'] = isset($variant->wholesale)
                    ? number_format((float) $variant->wholesale, 2, '.', ',')
                    : number_format(0, 2, '.', ',');
                $ProductVariant['min_price'] = isset($variant->min_price)
                    ? number_format((float) $variant->min_price, 2, '.', ',')
                    : number_format(0, 2, '.', ',');

                $item['products_variants_data'][] = $ProductVariant;

                foreach ($warehouses as $warehouse) {
                    $product_warehouse = DB::table('product_warehouse')
                        ->where('product_id', $id)
                        ->where('deleted_at', '=', null)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_variant_id', $variant->id)
                        ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                        ->first();

                    $war_var['mag'] = $warehouse->name;
                    $war_var['variant'] = $variant->name;
                    $war_var['qte'] = $product_warehouse->sum;
                    $item['CountQTY_variants'][] = $war_var;
                }

            }
        } else {
            $item['is_variant'] = 'no';
            $item['CountQTY_variants'] = [];
        }

        foreach ($warehouses as $warehouse) {
            $product_warehouse_data = DB::table('product_warehouse')
                ->where('deleted_at', '=', null)
                ->where('product_id', $id)
                ->where('warehouse_id', $warehouse->id)
                ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                ->first();

            $war['mag'] = $warehouse->name;
            $war['qte'] = $product_warehouse_data->sum;
            $item['CountQTY'][] = $war;
        }

        $allimages = $Product->productGalleryFilenames();
        $mainImage = $Product->primaryProductImageFilename();
        if ($mainImage !== '' && ($key = array_search($mainImage, $allimages, true)) !== false && $key !== 0) {
            array_splice($allimages, $key, 1);
            array_unshift($allimages, $mainImage);
        }
        $item['image'] = $mainImage !== '' ? $mainImage : ($allimages[0] ?? '');
        $item['images'] = $allimages;

        $data[] = $item;

        return response()->json($data[0]);

    }

    // ------------ Get products By Warehouse -----------------\\

    public function Products_by_Warehouse(request $request, $id)
    {
        $data = [];
        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')
            ->where(function ($query) use ($request, $id) {
                return $query->where('warehouse_id', $id)
                    ->where('deleted_at', '=', null)
                    ->where(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            // Only allow active products in all flows (sales, purchases, etc.)
                            $q->where('is_active', 1);
                            if ($request->is_sale == '1') {
                                $q->where('not_selling', '=', 0);
                            }
                        });
                    })

                    ->where(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            if (isset($request->product_combo) && $request->product_combo == '1') {
                                $q->whereIn('type', ['is_combo', 'is_single', 'is_variant', 'is_service']);
                            } elseif (! isset($request->product_combo) || $request->product_combo == '0') {
                                $q->whereNotIn('type', ['is_combo']);
                            }
                        });
                    })

                    ->where(function ($query) use ($request) {
                        if ($request->stock == '1' && $request->product_service == '1') {
                            return $query->where('qte', '>', 0)->orWhere('manage_stock', false);

                        } elseif ($request->stock == '1' && $request->product_service == '0') {
                            return $query->where('qte', '>', 0)->orWhere('manage_stock', true);

                        } else {
                            return $query->where('manage_stock', true);
                        }
                    });
            })->get();

        foreach ($product_warehouse_data as $product_warehouse) {

            if ($product_warehouse->product_variant_id) {
                $item['product_variant_id'] = $product_warehouse->product_variant_id;

                $item['code'] = $product_warehouse['productVariant']->code;
                $item['Variant'] = '['.$product_warehouse['productVariant']->name.']'.$product_warehouse['product']->name;
                $item['name'] = '['.$product_warehouse['productVariant']->name.']'.$product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['productVariant']->code;

                $product_price = $product_warehouse['productVariant']->price;

            } else {
                $item['product_variant_id'] = null;
                $item['Variant'] = null;
                $item['code'] = $product_warehouse['product']->code;
                $item['name'] = $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['product']->code;

                $product_price = $product_warehouse['product']->price;
            }

            $item['id'] = $product_warehouse->product_id;
            $item['product_type'] = $product_warehouse['product']->type;
            $item['Type_barcode'] = $product_warehouse['product']->Type_barcode;
            $firstimage = explode(',', $product_warehouse['product']->image);
            $item['image'] = $firstimage[0];

            if ($product_warehouse['product']['unitSale']) {

                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $item['qte_sale'] = $product_warehouse->qte * $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price / $product_warehouse['product']['unitSale']->operator_value;
                } else {
                    $item['qte_sale'] = $product_warehouse->qte / $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price * $product_warehouse['product']['unitSale']->operator_value;
                }

            } else {
                $item['qte_sale'] = $product_warehouse['product']->type != 'is_service' ? $product_warehouse->qte : '---';
                $price = $product_price;
            }

            // Compute wholesale price per sale unit (min_price is returned raw from DB)
            $wholesale_product_price = isset($product_warehouse['product']->wholesale_price) ? $product_warehouse['product']->wholesale_price : $product_price;

            if ($product_warehouse['product']['unitSale']) {
                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $wholesale_unit_price = $wholesale_product_price / $product_warehouse['product']['unitSale']->operator_value;
                } else {
                    $wholesale_unit_price = $wholesale_product_price * $product_warehouse['product']['unitSale']->operator_value;
                }
            } else {
                $wholesale_unit_price = $wholesale_product_price;
            }

            if ($product_warehouse['product']['unitPurchase']) {

                if ($product_warehouse['product']['unitPurchase']->operator == '/') {
                    $item['qte_purchase'] = round($product_warehouse->qte * $product_warehouse['product']['unitPurchase']->operator_value, 5);
                } else {
                    $item['qte_purchase'] = round($product_warehouse->qte / $product_warehouse['product']['unitPurchase']->operator_value, 5);
                }

            } else {
                $item['qte_purchase'] = $product_warehouse->qte;
            }

            $item['manage_stock'] = $product_warehouse->manage_stock;
            $item['qte'] = $product_warehouse['product']->type != 'is_service' ? $product_warehouse->qte : '---';
            $item['unitSale'] = $product_warehouse['product']['unitSale'] ? $product_warehouse['product']['unitSale']->ShortName : '';
            $item['unitPurchase'] = $product_warehouse['product']['unitPurchase'] ? $product_warehouse['product']['unitPurchase']->ShortName : '';

            // Discount

            if ($product_warehouse['product']->discount !== 0.0) {
                if ($product_warehouse['product']->discount_method == '1') {
                    $discount = $price * $product_warehouse['product']->discount / 100;
                    $item['discount'] = $product_warehouse['product']->discount;
                    $item['DiscountNet'] = $discount;
                    $price_discounted = $price - $discount;
                    $item['discount_method'] = '1';
                } else {
                    $discount = $product_warehouse['product']->discount;
                    $item['discount'] = $product_warehouse['product']->discount;
                    $item['DiscountNet'] = $product_warehouse['product']->discount;
                    $price_discounted = $price - $product_warehouse['product']->discount;
                    $item['discount_method'] = '2';
                }

            } else {
                $item['discount'] = 0;
                $item['DiscountNet'] = 0;
                $item['discount_method'] = '2';
                $price_discounted = $product_price;

            }

            // Tax
            if ($product_warehouse['product']->TaxNet !== 0.0) {
                // Exclusive
                if ($product_warehouse['product']->tax_method == '1') {
                    $tax_price = $price_discounted * $product_warehouse['product']->TaxNet / 100;
                    $item['Net_price'] = $price_discounted + $tax_price;
                    // Inxclusive
                } else {
                    $item['Net_price'] = $price_discounted;
                }
            } else {
                $item['Net_price'] = $price_discounted;
            }

            // Apply discount/tax to wholesale and min prices as well
            // Wholesale
            if ($product_warehouse['product']->discount !== 0.0) {
                if ($product_warehouse['product']->discount_method == '1') {
                    $wholesale_discount = $wholesale_unit_price * $product_warehouse['product']->discount / 100;
                    $wholesale_price_discounted = $wholesale_unit_price - $wholesale_discount;
                } else {
                    $wholesale_discount = $product_warehouse['product']->discount;
                    $wholesale_price_discounted = $wholesale_unit_price - $wholesale_discount;
                }
            } else {
                $wholesale_discount = 0;
                $wholesale_price_discounted = $wholesale_unit_price;
            }

            if ($product_warehouse['product']->TaxNet !== 0.0) {
                if ($product_warehouse['product']->tax_method == '1') {
                    $wholesale_tax_price = $wholesale_price_discounted * $product_warehouse['product']->TaxNet / 100;
                    $wholesale_net_price = $wholesale_price_discounted + $wholesale_tax_price;
                } else {
                    $wholesale_tax_price = $wholesale_price_discounted * $product_warehouse['product']->TaxNet / 100;
                    $wholesale_net_price = $wholesale_price_discounted;
                }
            } else {
                $wholesale_tax_price = 0;
                $wholesale_net_price = $wholesale_price_discounted;
            }

            $item['Unit_price_wholesale'] = $wholesale_unit_price;
            $item['wholesale_Net_price'] = $wholesale_net_price;
            // return raw min price from DB without discount/tax/unit conversion
            $item['min_price'] = $product_warehouse['product']->min_price ?? 0;

            $data[] = $item;
        }

        return response()->json($data);
    }

    public function show($id)
    {
        //
    }

    // ------------ Get product By ID -----------------\\
    public function show_product_data($id, $variant_id, $warehouse_id = null)
    {

        $Product_data = Product::with('unit')
            ->where('id', $id)
            ->where('deleted_at', '=', null)
            ->where('is_active', 1)
            ->firstOrFail();

        $data = [];
        $item['id'] = $Product_data['id'];
        $item['image'] = $Product_data['image'];
        $item['product_type'] = $Product_data['type'];
        $item['Type_barcode'] = $Product_data['Type_barcode'];

        $item['unit_id'] = $Product_data['unit'] ? $Product_data['unit']->id : '';
        $item['unit'] = $Product_data['unit'] ? $Product_data['unit']->ShortName : '';

        $item['purchase_unit_id'] = $Product_data['unitPurchase'] ? $Product_data['unitPurchase']->id : '';
        $item['unitPurchase'] = $Product_data['unitPurchase'] ? $Product_data['unitPurchase']->ShortName : '';

        $item['sale_unit_id'] = $Product_data['unitSale'] ? $Product_data['unitSale']->id : '';
        $item['unitSale'] = $Product_data['unitSale'] ? $Product_data['unitSale']->ShortName : '';

        $item['tax_method'] = $Product_data['tax_method'];
        $item['tax_percent'] = $Product_data['TaxNet'];

        $item['discount_method'] = $Product_data['discount_method'];
        $item['discount'] = $Product_data['discount'];

        $item['is_imei'] = $Product_data['is_imei'];
        $item['not_selling'] = $Product_data['not_selling'];
        $item['hide_from_online_store'] = $Product_data['hide_from_online_store'] ?? 0;
        $item['is_batch_tracked'] = (bool) ($Product_data['is_batch_tracked'] ?? false);

        // product single
        if ($Product_data['type'] == 'is_single') {
            $product_price = $Product_data['price'];
            $product_cost = $Product_data['cost'];

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];

            // product is_variant
        } elseif ($Product_data['type'] == 'is_variant') {

            $product_variant_data = ProductVariant::where('product_id', $id)
                ->where('id', $variant_id)->first();

            $product_price = $product_variant_data['price'];
            $product_cost = $product_variant_data['cost'];
            $item['code'] = $product_variant_data['code'];
            $item['name'] = '['.$product_variant_data['name'].']'.$Product_data['name'];

            // product is_service
        } else {

            $product_price = $Product_data['price'];
            $product_cost = 0;

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];
        }

        // check if product has Unit sale
        if ($Product_data['unitSale']) {

            if ($Product_data['unitSale']->operator == '/') {
                $price = $product_price / $Product_data['unitSale']->operator_value;

            } else {
                $price = $product_price * $Product_data['unitSale']->operator_value;
            }

        } else {
            $price = $product_price;
        }

        // check if product has Unit Purchase

        if ($Product_data['unitPurchase']) {

            if ($Product_data['unitPurchase']->operator == '/') {
                $cost = $product_cost / $Product_data['unitPurchase']->operator_value;
            } else {
                $cost = $product_cost * $Product_data['unitPurchase']->operator_value;
            }

        } else {
            $cost = 0;
        }

        $item['Unit_cost'] = $cost;
        $item['fix_cost'] = $product_cost;
        $item['Unit_price'] = $price;
        $item['fix_price'] = $product_price;

        // Calculate wholesale price with same unit conversion (min_price returned raw)
        if ($Product_data['type'] == 'is_variant' && isset($product_variant_data)) {
            $wholesale_product_price = isset($product_variant_data['wholesale']) && $product_variant_data['wholesale'] !== null
                ? $product_variant_data['wholesale']
                : $product_price;
        } else {
            $wholesale_product_price = isset($Product_data->wholesale_price) && $Product_data->wholesale_price !== null
                ? $Product_data->wholesale_price
                : $product_price;
        }

        if ($Product_data['unitSale']) {
            if ($Product_data['unitSale']->operator == '/') {
                $wholesale_unit_price = $wholesale_product_price / $Product_data['unitSale']->operator_value;
            } else {
                $wholesale_unit_price = $wholesale_product_price * $Product_data['unitSale']->operator_value;
            }
        } else {
            $wholesale_unit_price = $wholesale_product_price;
        }

        if ($Product_data->discount !== 0.0) {
            if ($Product_data['discount_method'] == '1') {
                $discount = $product_price * $Product_data['discount'] / 100;
                $item['discount'] = $Product_data->discount;
                $item['DiscountNet'] = $discount;
                $price_discount = $product_price - $discount;
                $item['discount_method'] = '1';
            } else {
                $discount = $Product_data->discount;
                $item['discount'] = $Product_data->discount;
                $item['DiscountNet'] = $Product_data->discount;
                $price_discount = $product_price - $Product_data->discount;
                $item['discount_method'] = '2';
            }

        } else {
            $item['discount'] = 0;
            $item['DiscountNet'] = 0;
            $item['discount_method'] = '2';
            $price_discount = $product_price;

        }

        if ($Product_data->TaxNet !== 0.0) {
            // Exclusive
            if ($Product_data['tax_method'] == '1') {
                $tax_price = $price_discount * $Product_data['TaxNet'] / 100;
                $tax_cost = $cost * $Product_data['TaxNet'] / 100;

                $item['Total_cost'] = $cost + $tax_cost;
                $item['Total_price'] = $price_discount + $tax_price;
                $item['Net_cost'] = $cost;
                $item['Net_price'] = $price_discount;
                $item['tax_price'] = $tax_price;
                $item['tax_cost'] = $tax_cost;

                // Inxclusive
            } else {
                $tax_price = $price_discount * $Product_data['TaxNet'] / 100;
                $tax_cost = $cost * $Product_data['TaxNet'] / 100;

                $item['Total_cost'] = $cost;
                $item['Total_price'] = $price_discount;
                $item['Net_cost'] = $cost - $tax_cost;
                $item['Net_price'] = $price_discount - $tax_price;
                $item['tax_price'] = $tax_price;
                $item['tax_cost'] = $tax_cost;
            }
        } else {
            $item['Total_cost'] = $cost;
            $item['Total_price'] = $price_discount;
            $item['Net_cost'] = $cost;
            $item['Net_price'] = $price_discount;
            $item['tax_price'] = 0;
            $item['tax_cost'] = 0;
        }

        // Compute wholesale and min final prices with same discount/tax logic
        // Wholesale
        if ($Product_data->discount !== 0.0) {
            if ($Product_data['discount_method'] == '1') {
                $wholesale_discount = $wholesale_unit_price * $Product_data['discount'] / 100;
                $wholesale_price_discount = $wholesale_unit_price - $wholesale_discount;
            } else {
                $wholesale_discount = $Product_data->discount;
                $wholesale_price_discount = $wholesale_unit_price - $wholesale_discount;
            }
        } else {
            $wholesale_discount = 0;
            $wholesale_price_discount = $wholesale_unit_price;
        }

        if ($Product_data->TaxNet !== 0.0) {
            if ($Product_data['tax_method'] == '1') {
                $wholesale_tax_price = $wholesale_price_discount * $Product_data['TaxNet'] / 100;
                $wholesale_total_price = $wholesale_price_discount + $wholesale_tax_price;
            } else {
                $wholesale_tax_price = $wholesale_price_discount * $Product_data['TaxNet'] / 100;
                $wholesale_total_price = $wholesale_price_discount;
            }
        } else {
            $wholesale_tax_price = 0;
            $wholesale_total_price = $wholesale_price_discount;
        }

        $item['Unit_price_wholesale'] = $wholesale_unit_price;
        $item['wholesale_Net_price'] = $wholesale_total_price;

        // Compute min price per sale unit (no discount/tax), to compare with Net_price
        $min_price_raw = ($Product_data['type'] == 'is_variant' && isset($product_variant_data))
            ? ($product_variant_data['min_price'] ?? 0)
            : ($Product_data->min_price ?? 0);

        if ($Product_data['unitSale']) {
            if ($Product_data['unitSale']->operator == '/') {
                $min_unit_price = $min_price_raw / $Product_data['unitSale']->operator_value;
            } else {
                $min_unit_price = $min_price_raw * $Product_data['unitSale']->operator_value;
            }
        } else {
            $min_unit_price = $min_price_raw;
        }
        $item['min_price'] = $min_unit_price ?: 0;

        // Add warehouse stock quantity data
        $item['qte'] = 0;
        $item['qte_sale'] = 0;

        if ($warehouse_id) {
            $query = product_warehouse::where('warehouse_id', $warehouse_id)
                ->where('product_id', $id)
                ->where('deleted_at', '=', null);

            // Only filter by variant if variant_id is provided and not null
            if ($variant_id && $variant_id != 'null') {
                $query->where('product_variant_id', $variant_id);
            } else {
                $query->whereNull('product_variant_id');
            }

            $product_warehouse = $query->first();

            if ($product_warehouse) {
                $item['qte'] = $product_warehouse->qte;

                // Calculate qte_sale based on sale unit if exists
                if ($Product_data['unitSale']) {
                    if ($Product_data['unitSale']->operator == '/') {
                        $item['qte_sale'] = $product_warehouse->qte / $Product_data['unitSale']->operator_value;
                    } else {
                        $item['qte_sale'] = $product_warehouse->qte / $Product_data['unitSale']->operator_value;
                    }
                } else {
                    $item['qte_sale'] = $product_warehouse->qte;
                }
            }
        }

        // Suggested Rack/Location (display-only hint)
        $item['warehouse_location'] = null;
        if ($warehouse_id) {
            $pwl = ProductWarehouseLocation::with('location')
                ->where('product_id', $id)
                ->where('warehouse_id', $warehouse_id)
                ->first();

            if ($pwl && $pwl->location) {
                $item['warehouse_location'] = [
                    'id' => $pwl->location->id,
                    'code' => $pwl->location->code,
                    'name' => $pwl->location->name,
                    'is_active' => (bool) $pwl->location->is_active,
                ];
            }
        }

        $data[] = $item;

        return response()->json($data[0]);
    }

    // --------------  Product Quantity Alerts ---------------\\

    public function Products_Alert(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Alerts', Product::class);

        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->where('manage_stock', true)
            ->whereRaw('qte <= stock_alert')
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse);
                });
            })->where('product_warehouse.deleted_at', null)->get();

        $data = [];

        if ($product_warehouse_data->isNotEmpty()) {

            foreach ($product_warehouse_data as $product_warehouse) {
                if ($product_warehouse->qte <= $product_warehouse['product']->stock_alert) {
                    if ($product_warehouse->product_variant_id !== null) {
                        $item['code'] = $product_warehouse['productVariant']->code;
                        $item['name'] = '['.$product_warehouse['productVariant']->name.']'.$product_warehouse['product']->name;
                    } else {
                        $item['code'] = $product_warehouse['product']->code;
                        $item['name'] = $product_warehouse['product']->name;
                    }
                    $item['quantity'] = $product_warehouse->qte;
                    $item['warehouse'] = $product_warehouse['warehouse']->name;
                    $item['stock_alert'] = $product_warehouse['product']->stock_alert;
                    $data[] = $item;
                }
            }
        }

        $perPage = $request->limit; // How many items do you want to display.
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $collection = collect($data);
        // Get only the items you need using array_slice
        $data_collection = $collection->slice($offSet, $perPage)->values();

        $products = new LengthAwarePaginator($data_collection, count($data), $perPage, Paginator::resolveCurrentPage(), ['path' => Paginator::resolveCurrentPath()]);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    // ---------------- Show Form Create Product ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        $categories = Category::whereNull('deleted_at')->get(['id', 'name']);
        $subcategories = SubCategory::orderBy('name')->get(['id', 'name', 'category_id']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);
        $units = Unit::where('deleted_at', null)->where('base_unit', null)->get();

        // get warehouses and pad with opening‑stock defaults
        $user_auth = auth()->user();

        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
            $allowedWarehouseIds = $warehouses->pluck('id')->toArray();
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')
                ->whereIn('id', $warehouses_id)
                ->get(['id', 'name']);
            $allowedWarehouseIds = $warehouses->pluck('id')->toArray();
        }

        // Add `qte` = 0 to each warehouse
        $warehouses = $warehouses->map(function ($w) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'qte' => 0, // opening stock
            ];
        });

        $warehouse_locations = WarehouseLocation::whereNull('deleted_at')
            ->where('is_active', 1)
            ->whereIn('warehouse_id', $allowedWarehouseIds)
            ->orderBy('code')
            ->get(['id', 'warehouse_id', 'code', 'name']);

        return response()->json([
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'units' => $units,
            'warehouses' => $warehouses,
            'warehouse_locations' => $warehouse_locations,
        ]);

    }

    // ---------------- Show Elements Barcode ---------------\\

    public function Get_element_barcode(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'barcode', Product::class);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json(['warehouses' => $warehouses]);

    }

    // ---------------- Show Form Edit Product ---------------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);

        $Product = Product::where('deleted_at', '=', null)->findOrFail($id);

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['points'] = $Product->points;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;

        // ——— Warranty & Guarantee ———
        $item['warranty_period'] = $Product->warranty_period;
        $item['warranty_unit'] = $Product->warranty_unit;
        $item['warranty_terms'] = $Product->warranty_terms;

        $item['has_guarantee'] = (bool) $Product->has_guarantee;
        $item['guarantee_period'] = $Product->guarantee_period;
        $item['guarantee_unit'] = $Product->guarantee_unit;

        if ($Product->category_id) {
            if (Category::where('id', $Product->category_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['category_id'] = $Product->category_id;
            } else {
                $item['category_id'] = '';
            }
        } else {
            $item['category_id'] = '';
        }

        if ($Product->sub_category_id) {
            // validate against SubCategory model (no soft deletes on this table)
            if (SubCategory::where('id', $Product->sub_category_id)->first()) {
                $item['sub_category_id'] = $Product->sub_category_id;
            } else {
                $item['sub_category_id'] = '';
            }
        } else {
            $item['sub_category_id'] = '';
        }

        if ($Product->brand_id) {
            if (Brand::where('id', $Product->brand_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['brand_id'] = $Product->brand_id;
            } else {
                $item['brand_id'] = '';
            }
        } else {
            $item['brand_id'] = '';
        }

        if ($Product->unit_id) {
            if (Unit::where('id', $Product->unit_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_id'] = $Product->unit_id;
            } else {
                $item['unit_id'] = '';
            }

            if (Unit::where('id', $Product->unit_sale_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_sale_id'] = $Product->unit_sale_id;
            } else {
                $item['unit_sale_id'] = '';
            }

            if (Unit::where('id', $Product->unit_purchase_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_purchase_id'] = $Product->unit_purchase_id;
            } else {
                $item['unit_purchase_id'] = '';
            }

        } else {
            $item['unit_id'] = '';
        }

        $materiels = [];
        if ($Product->type == 'is_combo') {
            $combined_products = CombinedProduct::where('product_id', $id)->with('product.unit')->get();

            $materiels = $combined_products->map(function ($combinedProduct) {
                return [
                    'product_id' => $combinedProduct->combined_product_id,
                    'name' => $combinedProduct->product->name,
                    'code' => $combinedProduct->product->code,
                    'unit_name' => $combinedProduct->product['unit']->ShortName,
                    'cost' => $combinedProduct->product->cost,
                    'quantity' => $combinedProduct->quantity,
                ];
            });

        }

        $item['tax_method'] = $Product->tax_method;
        $item['discount_method'] = $Product->discount_method;
        $item['discount'] = $Product->discount;
        $item['price'] = $Product->price;
        $item['wholesale_price'] = $Product->wholesale_price;
        $item['min_price'] = $Product->min_price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['weight'] = $Product->weight;
        $item['length'] = $Product->length;
        $item['width'] = $Product->width;
        $item['height'] = $Product->height;
        $item['TaxNet'] = $Product->TaxNet;
        $item['note'] = $Product->note ? $Product->note : '';

        $firstimage = explode(',', $Product->image);
        $item['image'] = $firstimage[0];

        if ($Product->type == 'is_variant') {
            $item['is_variant'] = true;
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();

            $var_id = 0;
            foreach ($productsVariants as $variant) {
                $variant_item['var_id'] = $var_id += 1;
                $variant_item['id'] = $variant->id;
                $variant_item['text'] = $variant->name;
                $variant_item['code'] = $variant->code;
                $variant_item['price'] = $variant->price;
                $variant_item['cost'] = $variant->cost;
                $variant_item['wholesale'] = $variant->wholesale ?? 0;
                $variant_item['min_price'] = $variant->min_price ?? 0;
                $variant_item['product_id'] = $variant->product_id;
                $item['ProductVariant'][] = $variant_item;
            }
        } else {
            $item['is_variant'] = false;
            $item['ProductVariant'] = [];
        }

        $item['is_imei'] = $Product->is_imei ? true : false;
        $item['not_selling'] = $Product->not_selling ? true : false;
        $item['is_featured'] = $Product->is_featured ? true : false;
        $item['hide_from_online_store'] = $Product->hide_from_online_store ? true : false;
        $item['is_active'] = $Product->is_active ? true : false;

        $item['is_preorder'] = (bool) $Product->is_preorder;
        $item['preorder_available_date'] = $Product->preorder_available_date
            ? $Product->preorder_available_date->format('Y-m-d') : '';
        $item['preorder_limit'] = $Product->preorder_limit !== null ? $Product->preorder_limit : '';
        $item['preorder_note'] = $Product->preorder_note ?? '';

        // Pharmacy / Batch & Expiry payload (always present, defaults are safe for non-pharmacy products)
        $item['is_batch_tracked'] = (bool) $Product->is_batch_tracked;
        $item['shelf_life_days'] = $Product->shelf_life_days !== null ? (int) $Product->shelf_life_days : '';
        $item['generic_name'] = $Product->generic_name ?? '';
        $item['strength'] = $Product->strength ?? '';
        $item['dosage_form'] = $Product->dosage_form ?? '';
        $item['pack_size'] = $Product->pack_size ?? '';
        $item['manufacturer'] = $Product->manufacturer ?? '';
        $item['prescription_required'] = (bool) $Product->prescription_required;
        $item['drug_schedule'] = $Product->drug_schedule ?? '';

        $item['product_images'] = [];
        if (Schema::hasTable('product_images')) {
            $item['product_images'] = ProductImage::where('product_id', $id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'image_path' => $img->image_path,
                        'url' => asset('images/products/'.$img->image_path),
                        'is_main' => (bool) $img->is_main,
                        'sort_order' => (int) $img->sort_order,
                    ];
                })
                ->values()
                ->all();
        }

        $Product->loadMissing(['categories', 'subcategories']);

        $pivotCatIds = $Product->categories->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        $primaryCat = (int) ($Product->category_id ?? 0);
        if ($pivotCatIds === [] && $primaryCat > 0) {
            $item['assigned_category_ids'] = [$primaryCat];
        } elseif ($primaryCat > 0) {
            $item['assigned_category_ids'] = array_values(array_unique(array_merge(
                [$primaryCat],
                array_values(array_diff($pivotCatIds, [$primaryCat]))
            )));
        } else {
            $item['assigned_category_ids'] = $pivotCatIds;
        }

        $pivotSubIds = $Product->subcategories->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        $primarySub = (int) ($Product->sub_category_id ?? 0);
        if ($pivotSubIds === [] && $primarySub > 0) {
            $item['assigned_subcategory_ids'] = [$primarySub];
        } elseif ($primarySub > 0) {
            $item['assigned_subcategory_ids'] = array_values(array_unique(array_merge(
                [$primarySub],
                array_values(array_diff($pivotSubIds, [$primarySub]))
            )));
        } else {
            $item['assigned_subcategory_ids'] = $pivotSubIds;
        }

        $data = $item;
        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $all_subcategories = SubCategory::orderBy('name')->get(['id', 'name', 'category_id']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);

        $product_units = Unit::where('id', $Product->unit_id)
            ->orWhere('base_unit', $Product->unit_id)
            ->where('deleted_at', null)
            ->get();

        $units = Unit::where('deleted_at', null)
            ->where('base_unit', null)
            ->get();

        // Warehouses assigned to user (for default location selection UI)
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
            $allowedWarehouseIds = $warehouses->pluck('id')->toArray();
        } else {
            $allowedWarehouseIds = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $allowedWarehouseIds)->get(['id', 'name']);
        }

        $warehouse_locations = WarehouseLocation::whereNull('deleted_at')
            ->whereIn('warehouse_id', $allowedWarehouseIds)
            ->orderBy('code')
            ->get(['id', 'warehouse_id', 'code', 'name', 'is_active']);

        $product_warehouse_locations = ProductWarehouseLocation::where('product_id', $id)
            ->whereIn('warehouse_id', $allowedWarehouseIds)
            ->get(['warehouse_id', 'warehouse_location_id']);

        return response()->json([
            'product' => $data,
            'categories' => $categories,
            'all_subcategories' => $all_subcategories,
            'brands' => $brands,
            'units' => $units,
            'units_sub' => $product_units,
            'materiels' => $materiels,
            'warehouses' => $warehouses,
            'warehouse_locations' => $warehouse_locations,
            'product_warehouse_locations' => $product_warehouse_locations,
        ]);

    }

    /**
     * IMPORT: Single products (no variants)
     * Expected headers per row:
     * name, code, price, cost, category, unit, brand (opt), stock_alert (opt), note (opt)
     */
    public function import_single_products(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_import', Product::class);
        ini_set('max_execution_time', 2000);

        $request->validate([
            'products' => 'required|mimes:xls,xlsx',
        ]);

        // Read first sheet with headings → associative rows
        $rows = Excel::toArray(new ProductImport, $request->file('products'))[0] ?? [];

        // Drop rows that are entirely empty (all values null/blank)
        $rows = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));

        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.']);
        }

        $warehouses = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();

        // Preload existing codes (products & variants)
        $existingProductCodes = Product::whereNull('deleted_at')->pluck('code')->all();
        $existingVariantCodes = ProductVariant::whereNull('deleted_at')->pluck('code')->all();
        $existingCodesSet = array_fill_keys(array_merge($existingProductCodes, $existingVariantCodes), true);

        $seenCodes = []; // duplicates inside the file
        $toCreate = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $code = trim($row['code'] ?? '');
            // Support 'retail_price' as the canonical header; fallback to 'price'
            $price = $row['retail_price'] ?? ($row['price'] ?? null);
            $cost = $row['cost'] ?? null;
            $categoryName = $row['category'] ?? '';
            $subCategoryName = $row['sub_category'] ?? ($row['subcategory'] ?? '');
            $unitName = $row['unit'] ?? '';
            $brandName = $row['brand'] ?? '';
            $stockAlert = $row['stock_alert'] ?? 0;
            $note = $row['note'] ?? null;
            $wholesale = $row['wholesale_price'] ?? null;
            $minPrice = $row['min_price'] ?? null;

            if (! $name) {
                return response()->json(['status' => false, 'message' => 'Product name is missing.']);
            }
            if (! $code) {
                return response()->json(['status' => false, 'message' => "Product \"$name\" has no code."]);
            }

            if (isset($seenCodes[$code])) {
                return response()->json(['status' => false, 'message' => "Duplicate product code \"$code\" found in file."]);
            }
            if (isset($existingCodesSet[$code])) {
                return response()->json(['status' => false, 'message' => "Code \"$code\" already exists (product or variant)."]);
            }
            if (! is_numeric($price)) {
                return response()->json(['status' => false, 'message' => "Retail price for \"$name\" is invalid."]);
            }
            if (! is_numeric($cost)) {
                return response()->json(['status' => false, 'message' => "Cost for \"$name\" is invalid."]);
            }
            if ($wholesale !== null && $wholesale !== '' && ! is_numeric($wholesale)) {
                return response()->json(['status' => false, 'message' => "Wholesale price for \"$name\" is invalid."]);
            }
            if ($minPrice !== null && $minPrice !== '' && ! is_numeric($minPrice)) {
                return response()->json(['status' => false, 'message' => "Minimum price for \"$name\" is invalid."]);
            }

            $categoryName = trim($categoryName);
            if ($categoryName === '') {
                return response()->json(['status' => false, 'message' => "Missing category name for product \"$name\"."]);
            }

            $category = Category::firstOrCreate(['name' => $categoryName]);

            $subCategoryId = null;
            $subCategoryName = trim($subCategoryName);
            if ($subCategoryName !== '') {
                $subCategory = SubCategory::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $subCategoryName],
                    ['status' => true]
                );
                $subCategoryId = $subCategory->id;
            }

            $unit = Unit::where('ShortName', $unitName)->orWhere('name', $unitName)->first();
            if (! $unit) {
                return response()->json(['status' => false, 'message' => "Unit \"$unitName\" for \"$name\" does not exist."]);
            }

            $brandId = null;
            if (trim($brandName) !== '') {
                $brand = Brand::firstOrCreate(['name' => trim($brandName)]);
                $brandId = $brand->id;
            }

            $seenCodes[$code] = true;

            $toCreate[] = [
                'name' => $name,
                'code' => $code,
                'price' => (float) $price,
                'cost' => (float) $cost,
                'category_id' => $category->id,
                'sub_category_id' => $subCategoryId,
                'unit_id' => $unit->id,
                'unit_sale_id' => $unit->id,
                'unit_purchase_id' => $unit->id,
                'brand_id' => $brandId,
                'stock_alert' => is_numeric($stockAlert) ? $stockAlert : 0,
                'note' => $note ?: null,
                'wholesale_price' => is_numeric($wholesale) ? (float) $wholesale : 0,
                'min_price' => is_numeric($minPrice) ? (float) $minPrice : 0,
            ];
        }

        try {
            DB::transaction(function () use ($toCreate, $warehouses) {
                DB::disableQueryLog(); // reduce memory during huge imports

                // MySQL prepared statement cap ≈ 65k placeholders.
                // We insert 4 columns per row → theoretical max ≈ 16k rows.
                // Use a safe margin to avoid "too many placeholders".
                $PW_BATCH_ROWS = 12000;

                $pwRows = [];

                foreach ($toCreate as $data) {
                    $product = Product::create([
                        // base
                        'type' => 'is_single',
                        'is_variant' => 0,
                        'is_imei' => 0,
                        'not_selling' => 0,
                        'is_active' => 1,
                        'Type_barcode' => 'CODE128',
                        'image' => 'no-image.png',
                        'TaxNet' => 0,
                        'tax_method' => 1,
                        'discount' => 0,
                        'discount_method' => 1,
                        // payload
                        ...$data,
                    ]);

                    if (Schema::hasTable('category_product') && ! empty($data['category_id'])) {
                        $product->categories()->sync([(int) $data['category_id']]);
                    }
                    if (Schema::hasTable('product_subcategory') && ! empty($data['sub_category_id'])) {
                        $product->subcategories()->sync([(int) $data['sub_category_id']]);
                    }

                    // Prepare product_warehouse rows for each warehouse
                    foreach ($warehouses as $wid) {
                        $pwRows[] = [
                            'product_id' => $product->id,
                            'warehouse_id' => $wid,
                            'manage_stock' => 1,
                            'qte' => 0,
                        ];
                    }

                    // Flush in batches to avoid exceeding placeholder limits
                    if (count($pwRows) >= $PW_BATCH_ROWS) {
                        DB::table('product_warehouse')->insert($pwRows);
                        $pwRows = [];
                    }
                }

                // Insert any remaining rows
                if (! empty($pwRows)) {
                    DB::table('product_warehouse')->insert($pwRows);
                }
            });

            return response()->json(['status' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while importing: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * IMPORT: Service products (single row per service, type = is_service). Cost is always 0.
     * Expected headers: name, code, retail_price/price, category, unit, wholesale_price (opt), min_price (opt), brand (opt), note (opt)
     */
    public function import_service_products(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_import', Product::class);
        ini_set('max_execution_time', 2000);

        $request->validate([
            'products' => 'required|mimes:xls,xlsx',
        ]);

        $rows = Excel::toArray(new ProductImport, $request->file('products'))[0] ?? [];

        $rows = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));

        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.']);
        }

        $warehouses = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();

        $existingProductCodes = Product::whereNull('deleted_at')->pluck('code')->all();
        $existingVariantCodes = ProductVariant::whereNull('deleted_at')->pluck('code')->all();
        $existingCodesSet = array_fill_keys(array_merge($existingProductCodes, $existingVariantCodes), true);

        $seenCodes = [];
        $toCreate = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $code = trim($row['code'] ?? '');
            $price = $row['retail_price'] ?? ($row['price'] ?? null);
            $categoryName = $row['category'] ?? '';
            $subCategoryName = $row['sub_category'] ?? ($row['subcategory'] ?? '');
            $unitName = $row['unit'] ?? '';
            $brandName = $row['brand'] ?? '';
            $note = $row['note'] ?? null;
            $wholesale = $row['wholesale_price'] ?? null;
            $minPrice = $row['min_price'] ?? null;

            if (! $name) {
                return response()->json(['status' => false, 'message' => 'Service name is missing.']);
            }
            if (! $code) {
                return response()->json(['status' => false, 'message' => "Service \"$name\" has no code."]);
            }

            if (isset($seenCodes[$code])) {
                return response()->json(['status' => false, 'message' => "Duplicate service code \"$code\" found in file."]);
            }
            if (isset($existingCodesSet[$code])) {
                return response()->json(['status' => false, 'message' => "Code \"$code\" already exists (product or variant)."]);
            }
            if (! is_numeric($price)) {
                return response()->json(['status' => false, 'message' => "Retail price for \"$name\" is invalid."]);
            }
            if ($wholesale !== null && $wholesale !== '' && ! is_numeric($wholesale)) {
                return response()->json(['status' => false, 'message' => "Wholesale price for \"$name\" is invalid."]);
            }
            if ($minPrice !== null && $minPrice !== '' && ! is_numeric($minPrice)) {
                return response()->json(['status' => false, 'message' => "Minimum price for \"$name\" is invalid."]);
            }

            $categoryName = trim($categoryName);
            if ($categoryName === '') {
                return response()->json(['status' => false, 'message' => "Missing category name for service \"$name\"."]);
            }

            $category = Category::firstOrCreate(['name' => $categoryName]);

            $subCategoryId = null;
            $subCategoryName = trim($subCategoryName);
            if ($subCategoryName !== '') {
                $subCategory = SubCategory::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $subCategoryName],
                    ['status' => true]
                );
                $subCategoryId = $subCategory->id;
            }

            $unit = Unit::where('ShortName', $unitName)->orWhere('name', $unitName)->first();
            if (! $unit) {
                return response()->json(['status' => false, 'message' => "Unit \"$unitName\" for \"$name\" does not exist."]);
            }

            $brandId = null;
            if (trim($brandName) !== '') {
                $brand = Brand::firstOrCreate(['name' => trim($brandName)]);
                $brandId = $brand->id;
            }

            $seenCodes[$code] = true;

            $toCreate[] = [
                'name' => $name,
                'code' => $code,
                'price' => (float) $price,
                'cost' => 0,
                'category_id' => $category->id,
                'sub_category_id' => $subCategoryId,
                'unit_id' => $unit->id,
                'unit_sale_id' => $unit->id,
                'unit_purchase_id' => $unit->id,
                'brand_id' => $brandId,
                'stock_alert' => 0,
                'note' => $note ?: null,
                'wholesale_price' => is_numeric($wholesale) ? (float) $wholesale : 0,
                'min_price' => is_numeric($minPrice) ? (float) $minPrice : 0,
            ];
        }

        try {
            DB::transaction(function () use ($toCreate, $warehouses) {
                DB::disableQueryLog();

                $PW_BATCH_ROWS = 12000;
                $pwRows = [];

                foreach ($toCreate as $data) {
                    $product = Product::create([
                        'type' => 'is_service',
                        'is_variant' => 0,
                        'is_imei' => 0,
                        'not_selling' => 0,
                        'is_active' => 1,
                        'Type_barcode' => 'CODE128',
                        'image' => 'no-image.png',
                        'TaxNet' => 0,
                        'tax_method' => 1,
                        'discount' => 0,
                        'discount_method' => 1,
                        ...$data,
                    ]);

                    if (Schema::hasTable('category_product') && ! empty($data['category_id'])) {
                        $product->categories()->sync([(int) $data['category_id']]);
                    }
                    if (Schema::hasTable('product_subcategory') && ! empty($data['sub_category_id'])) {
                        $product->subcategories()->sync([(int) $data['sub_category_id']]);
                    }

                    foreach ($warehouses as $wid) {
                        $pwRows[] = [
                            'product_id' => $product->id,
                            'warehouse_id' => $wid,
                            'manage_stock' => 0,
                            'qte' => 0,
                        ];
                    }

                    if (count($pwRows) >= $PW_BATCH_ROWS) {
                        DB::table('product_warehouse')->insert($pwRows);
                        $pwRows = [];
                    }
                }

                if (! empty($pwRows)) {
                    DB::table('product_warehouse')->insert($pwRows);
                }
            });

            return response()->json(['status' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while importing: '.$e->getMessage(),
            ]);
        }
    }

    public function import_variant_products(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_import', Product::class);
        ini_set('max_execution_time', 2000);

        $request->validate([
            'products' => 'required|mimes:xls,xlsx',
        ]);

        // First sheet, heading row -> associative rows
        $rows = Excel::toArray(new ProductImport, $request->file('products'))[0] ?? [];

        // Drop rows that are entirely empty (all values null/blank)
        $rows = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));
        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.']);
        }

        // ---- helpers ----
        $normalizeKeys = function (array $row) {
            $out = [];
            foreach ($row as $k => $v) {
                $key = is_string($k) ? strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $k))) : $k;
                $out[$key] = $v;
            }

            return $out;
        };
        $firstVal = function (array $row, array $keys, $default = null) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $row)) {
                    $vv = is_string($row[$k]) ? trim($row[$k]) : $row[$k];
                    if ($vv !== '' && $vv !== null) {
                        return $vv;
                    }
                }
            }

            return $default;
        };

        // sanitize plain text (strip HTML/JS, trim, neutralize common patterns)
        $clean = function ($v, $allowEmpty = false) {
            if (is_null($v)) {
                $v = '';
            }
            $v = (string) $v;
            $v = trim(strip_tags($v));
            $v = str_ireplace(['javascript:', '&#x', '&lt;', '&gt;'], '', $v);
            if (! $allowEmpty && $v === '') {
                return '';
            }
            if (preg_match('/[<>]/', $v)) {
                $v = str_replace(['<', '>'], '', $v);
            }

            return $v;
        };

        $warehouses = Warehouse::whereNull('deleted_at')->pluck('id')->toArray();

        // Global uniqueness set (DB): products.code + product_variants.code
        $existingProductCodes = Product::whereNull('deleted_at')->pluck('code')->all();
        $existingVariantCodes = ProductVariant::whereNull('deleted_at')->pluck('code')->all();
        $existingCodes = array_fill_keys(array_merge($existingProductCodes, $existingVariantCodes), true);

        // In-file sets
        $fileParentCodes = []; // product_code's seen in file
        $fileVariantCodes = []; // variant_code's seen in file

        $groups = []; // product_code => ['parent'=>..., 'variants'=>[]]

        // ---- parse & validate rows ----
        foreach ($rows as $raw) {
            $r = $normalizeKeys($raw);

            // Optional "type" must be is_variant if present
            $type = $firstVal($r, ['type'], null);
            if ($type !== null && strtolower($type) !== 'is_variant') {
                return response()->json(['status' => false, 'message' => 'File contains non-variant rows. Use the single-products importer for those.']);
            }

            // Your headers (then sanitize)
            $parentName = $clean((string) $firstVal($r, ['product_name', 'name'], ''));
            $parentCode = $clean((string) $firstVal($r, ['product_code', 'code', 'parent_code'], ''));
            $category = $clean((string) $firstVal($r, ['category', 'category_name'], ''));
            $subCategoryName = $clean((string) $firstVal($r, ['sub_category', 'subcategory', 'sub_category_name'], ''), true);
            $unitName = $clean((string) $firstVal($r, ['unit', 'unit_name', 'unit_short'], ''));
            $brandName = $clean((string) $firstVal($r, ['brand', 'brand_name'], ''), true);

            $vName = $clean((string) $firstVal($r, ['variant_name', 'name_variant'], ''));
            $vCode = $clean((string) $firstVal($r, ['variant_code', 'sku', 'variant_sku'], ''));
            $vCost = $firstVal($r, ['variant_cost', 'v_cost'], null);
            $vPrice = $firstVal($r, ['variant_price', 'v_price'], null);
            $vWholesale = $firstVal($r, ['variant_wholesale', 'v_wholesale'], null);
            $vMinPrice = $firstVal($r, ['variant_min_price', 'v_min_price'], null);

            // Required checks (same logic, now on sanitized values)
            if ($parentName === '') {
                return response()->json(['status' => false, 'message' => 'Parent product name is missing.']);
            }
            if ($parentCode === '') {
                return response()->json(['status' => false, 'message' => "Parent product \"$parentName\" has no parent code."]);
            }
            if ($category === '') {
                return response()->json(['status' => false, 'message' => "Missing category name for product \"$parentName\"."]);
            }
            if ($unitName === '') {
                return response()->json(['status' => false, 'message' => "Unit is missing for product \"$parentName\"."]);
            }
            if ($vName === '') {
                return response()->json(['status' => false, 'message' => "Variant name is missing for parent \"$parentName\"."]);
            }
            if ($vCode === '') {
                return response()->json(['status' => false, 'message' => "Variant code is missing for parent \"$parentName\"."]);
            }
            if (! is_numeric($vCost)) {
                return response()->json(['status' => false, 'message' => "Variant cost for \"$vName\" is invalid."]);
            }
            if (! is_numeric($vPrice)) {
                return response()->json(['status' => false, 'message' => "Variant price for \"$vName\" is invalid."]);
            }
            if ($vWholesale !== null && $vWholesale !== '' && ! is_numeric($vWholesale)) {
                return response()->json(['status' => false, 'message' => "Variant wholesale for \"$vName\" is invalid."]);
            }
            if ($vMinPrice !== null && $vMinPrice !== '' && ! is_numeric($vMinPrice)) {
                return response()->json(['status' => false, 'message' => "Variant minimum price for \"$vName\" is invalid."]);
            }

            // ---- in-file cross checks ----
            if (isset($fileVariantCodes[$parentCode])) {
                return response()->json([
                    'status' => false,
                    'message' => "Parent code \"$parentCode\" conflicts with a variant_code in the same file.",
                ]);
            }
            if (isset($fileParentCodes[$vCode])) {
                return response()->json([
                    'status' => false,
                    'message' => "Variant code \"$vCode\" conflicts with a product_code in the same file.",
                ]);
            }
            $fileParentCodes[$parentCode] = true;

            if (isset($fileVariantCodes[$vCode])) {
                return response()->json(['status' => false, 'message' => "Duplicate variant code \"$vCode\" found in file."]);
            }
            $fileVariantCodes[$vCode] = true;

            // ---- global (DB) uniqueness ----
            if (isset($existingCodes[$parentCode])) {
                return response()->json(['status' => false, 'message' => "Parent code \"$parentCode\" already exists (product or variant)."]);
            }
            if (isset($existingCodes[$vCode])) {
                return response()->json(['status' => false, 'message' => "Variant code \"$vCode\" already exists (product or variant)."]);
            }

            // Group and keep parent consistency
            if (! isset($groups[$parentCode])) {
                $groups[$parentCode] = [
                    'parent' => [
                        'name' => $parentName,
                        'code' => $parentCode,
                        'category' => $category,
                        'sub_category' => $subCategoryName,
                        'unit' => $unitName,
                        'brand' => $brandName,
                        'stock_alert' => 0,
                        'note' => null,
                    ],
                    'variants' => [],
                ];
            } else {
                $p = $groups[$parentCode]['parent'];
                if ($p['name'] !== $parentName || $p['category'] !== $category || $p['unit'] !== $unitName || (string) $p['brand'] !== (string) $brandName) {
                    return response()->json([
                        'status' => false,
                        'message' => "Inconsistent parent data for code \"$parentCode\". Ensure product_name/category/unit/brand are identical for all its rows.",
                    ]);
                }
            }

            $groups[$parentCode]['variants'][] = [
                'variant_name' => $vName,
                'variant_code' => $vCode,
                'variant_cost' => (float) $vCost,
                'variant_price' => (float) $vPrice,
                'variant_wholesale' => is_numeric($vWholesale) ? (float) $vWholesale : 0,
                'variant_min_price' => is_numeric($vMinPrice) ? (float) $vMinPrice : 0,
                'variant_qty' => 0,
            ];
        }

        // ---- persist ----
        try {
            DB::transaction(function () use ($groups, $warehouses) {
                $pwRows = [];

                foreach ($groups as $parentCode => $bundle) {
                    $p = $bundle['parent'];

                    // Category (create if missing)
                    $category = Category::firstOrCreate(['name' => trim($p['category'])]);

                    // Sub-category (optional, create if missing)
                    $subCategoryId = null;
                    $subCatName = trim((string) ($p['sub_category'] ?? ''));
                    if ($subCatName !== '') {
                        $subCat = SubCategory::firstOrCreate(
                            ['category_id' => $category->id, 'name' => $subCatName],
                            ['status' => true]
                        );
                        $subCategoryId = $subCat->id;
                    }

                    // Unit (must exist)
                    $unit = Unit::where('ShortName', $p['unit'])->orWhere('name', $p['unit'])->first();
                    if (! $unit) {
                        throw new \RuntimeException("Unit \"{$p['unit']}\" for \"{$p['name']}\" does not exist.");
                    }

                    // Brand (optional)
                    $brandId = null;
                    if (trim((string) $p['brand']) !== '') {
                        $brand = Brand::firstOrCreate(['name' => trim((string) $p['brand'])]);
                        $brandId = $brand->id;
                    }

                    // Parent product (variant type)
                    $product = Product::create([
                        'type' => 'is_variant',
                        'is_variant' => 1,
                        'is_imei' => 0,
                        'not_selling' => 0,
                        'is_active' => 1,
                        'Type_barcode' => 'CODE128',
                        'image' => 'no-image.png',
                        'TaxNet' => 0,
                        'tax_method' => 1,
                        'price' => 0,
                        'cost' => 0,
                        'name' => $p['name'],
                        'code' => $p['code'],
                        'category_id' => $category->id,
                        'sub_category_id' => $subCategoryId,
                        'unit_id' => $unit->id,
                        'unit_sale_id' => $unit->id,
                        'unit_purchase_id' => $unit->id,
                        'brand_id' => $brandId,
                        'stock_alert' => 0,
                        'note' => null,
                    ]);

                    // Variants — forced image = 'no-image.png'
                    $variantRows = [];
                    $codesForFetch = [];
                    foreach ($bundle['variants'] as $v) {
                        $variantRows[] = [
                            'product_id' => $product->id,
                            'name' => $v['variant_name'], // sanitized
                            'code' => $v['variant_code'], // sanitized
                            'cost' => $v['variant_cost'],
                            'price' => $v['variant_price'],
                            // optional columns if present in schema
                            'wholesale' => Schema::hasColumn('product_variants', 'wholesale') ? ($v['variant_wholesale'] ?? 0) : null,
                            'min_price' => Schema::hasColumn('product_variants', 'min_price') ? ($v['variant_min_price'] ?? 0) : null,
                            'image' => 'no-image.png',
                        ];
                        $codesForFetch[] = $v['variant_code'];
                    }
                    ProductVariant::insert($variantRows);

                    // Map variant IDs
                    $created = ProductVariant::where('product_id', $product->id)
                        ->whereIn('code', $codesForFetch)
                        ->whereNull('deleted_at')
                        ->get(['id', 'code']);

                    $idByCode = [];
                    foreach ($created as $cv) {
                        $idByCode[$cv->code] = $cv->id;
                    }

                    // product_warehouse (forced qte=0)
                    foreach ($bundle['variants'] as $v) {
                        $vid = $idByCode[$v['variant_code']] ?? null;
                        if (! $vid) {
                            continue;
                        }
                        foreach ($warehouses as $wid) {
                            $pwRows[] = [
                                'product_id' => $product->id,
                                'warehouse_id' => $wid,
                                'product_variant_id' => $vid,
                                'manage_stock' => 1,
                                'qte' => 0,
                            ];
                        }
                    }
                }

                if (! empty($pwRows)) {
                    product_warehouse::insert($pwRows);
                }
            });

            return response()->json(['status' => true]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while importing variants: '.$e->getMessage(),
            ]);
        }
    }

        /**
     * IMPORT: Update Only - Updates existing products by code
     * Expected CSV format: code,cost,retail_price
     * Only updates cost and retail_price (price field) for products matching the code
     */
    public function import_update_only(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_import', Product::class);
        ini_set('max_execution_time', 2000);

        // Validate file - check extension instead of MIME type for better CSV support
        $request->validate([
            'products' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['csv', 'xls', 'xlsx'];
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('The ' . $attribute . ' must be a file of type: csv, xls, xlsx.');
                    }
                },
            ],
        ]);

        // Read file - support both CSV and Excel
        $fileExtension = strtolower($request->file('products')->getClientOriginalExtension());
        
        if ($fileExtension === 'csv') {
            // Handle CSV file
            $file = fopen($request->file('products')->getRealPath(), 'r');
            $rows = [];
            $headers = null;
            
            // Normalize header function
            $normalizeHeader = function($header) {
                return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header)));
            };
            
            while (($row = fgetcsv($file)) !== false) {
                if ($headers === null) {
                    // Normalize headers: lowercase, trim, replace spaces/special chars with underscore
                    $headers = array_map($normalizeHeader, $row);
                    continue;
                }
                
                if (count($row) !== count($headers)) {
                    continue; // Skip malformed rows
                }
                
                $rows[] = array_combine($headers, array_map('trim', $row));
            }
            fclose($file);
        } else {
            // Handle Excel file
            $rows = Excel::toArray(new ProductImport, $request->file('products'))[0] ?? [];
        }

        // Drop rows that are entirely empty
        $rows = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }
            return false;
        }));

        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.']);
        }

        $updated = 0;
        $notFound = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            // Normalize keys (handle case-insensitive and variations)
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $normalizedKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $key)));
                $normalizedRow[$normalizedKey] = $value;
            }

            // Extract values - support multiple possible column names
            $code = trim($normalizedRow['code'] ?? '');
            $cost = $normalizedRow['cost'] ?? null;
            $retailPrice = $normalizedRow['retail_price'] ?? ($normalizedRow['price'] ?? null);

            // Validation
            if (empty($code)) {
                $errors[] = "Row " . ($index + 2) . ": Missing product code.";
                continue;
            }

            if ($cost === null || $cost === '' || !is_numeric($cost)) {
                $errors[] = "Row " . ($index + 2) . " (code: $code): Invalid or missing cost.";
                continue;
            }

            if ($retailPrice === null || $retailPrice === '' || !is_numeric($retailPrice)) {
                $errors[] = "Row " . ($index + 2) . " (code: $code): Invalid or missing retail_price.";
                continue;
            }

            // Find product by code (only non-deleted products)
            $product = Product::where('code', $code)
                ->whereNull('deleted_at')
                ->first();

            if (!$product) {
                $notFound[] = $code;
                continue;
            }

            // Update only cost and price (retail_price maps to price field)
            try {
                $product->cost = (float) $cost;
                $product->price = (float) $retailPrice;
                $product->save();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . " (code: $code): Failed to update - " . $e->getMessage();
            }
        }

        // Build response message
        $messages = [];
        if ($updated > 0) {
            $messages[] = "Successfully updated $updated product(s).";
        }
        if (count($notFound) > 0) {
            $messages[] = count($notFound) . " product code(s) not found: " . implode(', ', array_slice($notFound, 0, 10)) . (count($notFound) > 10 ? '...' : '');
        }
        if (count($errors) > 0) {
            $messages[] = count($errors) . " error(s) occurred. " . implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '...' : '');
        }

        if ($updated === 0 && count($notFound) === 0 && count($errors) === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No products were updated. Please check your file format.',
            ]);
        }

        return response()->json([
            'status' => $updated > 0,
            'message' => implode(' ', $messages),
            'updated' => $updated,
            'not_found' => count($notFound),
            'errors' => count($errors),
        ]);
    }

    // ----------------- count_stock_list

    public function count_stock_list(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'count_stock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;

        $count_stock = CountStock::whereNull('deleted_at')
            ->with(['warehouse', 'user', 'category'])
            ->where(function ($query) use ($request) {
                $query->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;

                    $query->whereHas('warehouse', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    })->orWhereHas('category', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
                });
            });
        $totalRows = $count_stock->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $stocks = $count_stock->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];

        foreach ($stocks as $stock) {

            $item['id'] = $stock->id;
            $item['date'] = $stock->date;
            $item['warehouse_name'] = $stock['warehouse']->name;
            $item['category_name'] = $stock['category'] ? $stock['category']->name : '---';
            $item['file_stock'] = $stock->file_stock;

            $data[] = $item;
        }

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'stocks' => $data,
            'warehouses' => $warehouses,
            'categories' => $categories,
        ]);
    }

    // ----------------- store_count_stock

    public function store_count_stock(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'count_stock', Product::class);

        $request->validate([
            'date' => 'required',
            'warehouse_id' => 'required',
        ]);

        $products = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('product_warehouse.deleted_at', '=', null)
            ->where('product_warehouse.warehouse_id', '=', $request->warehouse_id)
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('products.category_id', $request->category_id);
            })
            ->select(
                'product_warehouse.product_id as productID',
                'products.name',
                'product_warehouse.product_variant_id as productVariantID',
                'product_warehouse.qte'
            )
            ->get();

        $stock = [];
        $incorrect_stock = [];

        foreach ($products as $product) {

            if ($product->productVariantID) {
                $variant = ProductVariant::where('product_id', $product->productID)->where('id', $product->productVariantID)->first();
                $item['product_name'] = $variant->name.'-'.$product->name;
            } else {
                $item['product_name'] = $product->name;
            }

            $item['quantity'] = $product->qte === 0.0 ? '0' : $product->qte;

            $stock[] = $item;
        }

        // Create an instance of StockExport with the warehouse name
        $stockExport = new StockExport($stock);

        $excelFileName = 'stock_export_'.now()->format('YmdHis').'.xlsx';
        $excelFolderPath = public_path().'/images/count_stock/';
        $excelFilePath = $excelFolderPath.$excelFileName;

        // Check if the directory exists, if not, create it
        if (! File::exists($excelFolderPath)) {
            File::makeDirectory($excelFolderPath, 0755, true, true);
        }

        // Use File::put to store the file directly in the desired public directory
        File::put($excelFilePath, Excel::raw($stockExport, \Maatwebsite\Excel\Excel::XLSX));

        // Save the file name in the count_stock table
        CountStock::create([
            'date' => $request->date,
            'warehouse_id' => $request->warehouse_id,
            'category_id' => $request->category_id,
            'user_id' => Auth::user()->id,
            'file_stock' => $excelFileName,
        ]);

        return response()->json(['success' => true]);

    }

    // -------------- get_products_materiels ------------------\\

    public function get_products_materiels(request $request)
    {

        $products = Product::where('products.deleted_at', '=', null)
            ->where('products.type', 'is_single')
            ->where('products.is_active', 1)
            ->join('units', 'products.unit_sale_id', '=', 'units.id')
            ->select('products.id as product_id', 'products.name', 'products.cost', 'products.code', 'units.ShortName as unit_name')
            ->get();

        return response()->json($products);
    }

    // GET /opening-stock/import/meta
    public function opening_stock_meta(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'opening_stock_import', Product::class);

        // warehouses for user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json(['warehouses' => $warehouses]);
    }

    // POST /opening-stock/import/single
    public function opening_stock_import_single(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'opening_stock_import', Product::class);

        $v = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'products' => 'required|mimes:xls,xlsx',
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $v->errors()->all(),
            ], 422);
        }

        $rows = Excel::toArray(new OpeningStockRowsImport, $request->file('products'))[0] ?? [];
        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.', 'errors' => ['The imported file is empty.']]);
        }

        // Normalize keys expected: product_code, qty
        $errors = [];
        $clean = [];
        $seenCodes = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // heading row = 1
            $code = trim((string) ($row['product_code'] ?? ''));
            $qty = $row['qty'] ?? null;

            if ($code === '') {
                $errors[] = "Row {$rowNum}: product_code is required.";
            }
            if ($qty === null || ! is_numeric($qty)) {
                $errors[] = "Row {$rowNum}: qty must be a number.";
            }

            if ($code !== '') {
                if (isset($seenCodes[$code])) {
                    $errors[] = "Row {$rowNum}: duplicate product_code '{$code}' in file.";
                } else {
                    $seenCodes[$code] = true;
                }
            }

            $clean[] = ['row' => $rowNum, 'code' => $code, 'qty' => (float) $qty];
        }

        // Validate existence of products
        $codes = array_values(array_unique(array_column($clean, 'code')));
        $existing = Product::whereNull('deleted_at')->whereIn('code', $codes)->pluck('id', 'code')->toArray();
        foreach ($codes as $code) {
            if ($code !== '' && ! isset($existing[$code])) {
                // Provide detailed, row-by-row errors:
                foreach ($clean as $c) {
                    if ($c['code'] === $code) {
                        $errors[] = "Row {$c['row']}: product_code '{$code}' does not exist.";
                    }
                }
            }
        }

        if (! empty($errors)) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        }

        // Apply stock + record virtual adjustments (similar to product create "opening stock")
        DB::transaction(function () use ($clean, $existing, $request) {
            $warehouseId = (int) $request->warehouse_id;

            // 1) Apply stock to product_warehouse
            foreach ($clean as $c) {
                $productId = $existing[$c['code']];
                $pw = product_warehouse::firstOrNew([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => null,
                ]);
                if (! $pw->exists) {
                    $pw->manage_stock = 1;
                    $pw->qte = 0;
                }
                $pw->qte = (float) $pw->qte + (float) $c['qty'];
                $pw->save();
            }

            // 2) Create a lightweight Adjustment with one detail per product
            //    so average‑cost/COGS logic can treat this as opening stock.
            $detailsByProduct = [];
            foreach ($clean as $c) {
                $qty = (float) $c['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $productId = $existing[$c['code']];
                if (! isset($detailsByProduct[$productId])) {
                    $detailsByProduct[$productId] = 0.0;
                }
                $detailsByProduct[$productId] += $qty;
            }

            if (! empty($detailsByProduct)) {
                $adjRef = app('App\Http\Controllers\AdjustmentController')->getNumberOrder();

                $adjustment = Adjustment::create([
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'Ref' => $adjRef,
                    'warehouse_id' => $warehouseId,
                    'items' => count($detailsByProduct),
                    'notes' => 'Opening stock import (auto)',
                    'user_id' => Auth::id(),
                ]);

                foreach ($detailsByProduct as $productId => $qty) {
                    AdjustmentDetail::create([
                        'adjustment_id' => $adjustment->id,
                        'product_id' => $productId,
                        'product_variant_id' => null,
                        'quantity' => $qty,
                        'type' => 'add',
                    ]);
                }
            }
        });

        return response()->json(['status' => true]);
    }

    // POST /opening-stock/import/variants
    public function opening_stock_import_variants(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'opening_stock_import', Product::class);

        $v = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'products' => 'required|mimes:xls,xlsx',
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $v->errors()->all(),
            ], 422);
        }

        $rows = Excel::toArray(new OpeningStockRowsImport, $request->file('products'))[0] ?? [];
        if (empty($rows)) {
            return response()->json(['status' => false, 'message' => 'The imported file is empty.', 'errors' => ['The imported file is empty.']]);
        }

        // Expected keys: product_code, variant_code, qty
        $errors = [];
        $clean = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $pcode = trim((string) ($row['product_code'] ?? ''));
            $vcode = trim((string) ($row['variant_code'] ?? ''));
            $qty = $row['qty'] ?? null;

            if ($pcode === '') {
                $errors[] = "Row {$rowNum}: product_code is required.";
            }
            if ($vcode === '') {
                $errors[] = "Row {$rowNum}: variant_code is required.";
            }
            if ($qty === null || ! is_numeric($qty)) {
                $errors[] = "Row {$rowNum}: qty must be a number.";
            }

            $clean[] = ['row' => $rowNum, 'pcode' => $pcode, 'vcode' => $vcode, 'qty' => (float) $qty];
        }

        // Validate existence + relationships
        $productCodes = array_values(array_unique(array_column($clean, 'pcode')));
        $products = Product::whereNull('deleted_at')->whereIn('code', $productCodes)->pluck('id', 'code')->toArray();

        // variant lookup
        $variantCodes = array_values(array_unique(array_column($clean, 'vcode')));
        $variants = ProductVariant::whereNull('deleted_at')
            ->whereIn('code', $variantCodes)
            ->get(['id', 'product_id', 'code'])
            ->keyBy('code');

        foreach ($clean as $c) {
            if ($c['pcode'] !== '' && ! isset($products[$c['pcode']])) {
                $errors[] = "Row {$c['row']}: product_code '{$c['pcode']}' does not exist.";

                continue;
            }
            if ($c['vcode'] !== '' && ! $variants->has($c['vcode'])) {
                $errors[] = "Row {$c['row']}: variant_code '{$c['vcode']}' does not exist.";

                continue;
            }
            // match product-variant
            if ($c['pcode'] !== '' && $c['vcode'] !== '' && $variants->has($c['vcode'])) {
                $pid = $products[$c['pcode']];
                $vProd = $variants->get($c['vcode'])->product_id;
                if ((int) $pid !== (int) $vProd) {
                    $errors[] = "Row {$c['row']}: variant_code '{$c['vcode']}' does not belong to product_code '{$c['pcode']}'.";
                }
            }
        }

        if (! empty($errors)) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        }

        // Apply stock + record virtual adjustments (similar to product create "opening stock")
        DB::transaction(function () use ($clean, $products, $variants, $request) {
            $warehouseId = (int) $request->warehouse_id;
            $detailRows = [];

            // 1) Apply stock to product_warehouse
            foreach ($clean as $c) {
                $productId = $products[$c['pcode']];
                $variantId = $variants->get($c['vcode'])->id;

                $pw = product_warehouse::firstOrNew([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                ]);
                if (! $pw->exists) {
                    $pw->manage_stock = 1;
                    $pw->qte = 0;
                }
                $pw->qte = (float) $pw->qte + (float) $c['qty'];
                $pw->save();

                $qty = (float) $c['qty'];
                if ($qty > 0) {
                    $detailRows[] = [
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'quantity' => $qty,
                    ];
                }
            }

            // 2) Single Adjustment header per import+warehouse, with one detail per variant row
            if (! empty($detailRows)) {
                $adjRef = app('App\Http\Controllers\AdjustmentController')->getNumberOrder();

                $adjustment = Adjustment::create([
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'Ref' => $adjRef,
                    'warehouse_id' => $warehouseId,
                    'items' => count($detailRows),
                    'notes' => 'Opening stock import (variants, auto)',
                    'user_id' => Auth::id(),
                ]);

                foreach ($detailRows as $row) {
                    AdjustmentDetail::create([
                        'adjustment_id' => $adjustment->id,
                        'product_id' => $row['product_id'],
                        'product_variant_id' => $row['product_variant_id'],
                        'quantity' => $row['quantity'],
                        'type' => 'add',
                    ]);
                }
            }
        });

        return response()->json(['status' => true]);
    }

    public function cleanNames()
    {
        $count = 0;

        \App\Models\Product::where('name', 'REGEXP', '<|>')
            ->chunkById(200, function ($products) use (&$count) {
                foreach ($products as $p) {
                    $old = $p->name;
                    $p->name = str_replace(['<', '>'], ['‹', '›'], $old);
                    $p->save();
                    $count++;
                }
            });

        return response()->json([
            'success' => true,
            'message' => "Cleaned {$count} products containing < or >.",
        ]);
    }

    // -------------- Duplicate Product  ---------------\\
    // -----------------------------------------------\\
    public function duplicate(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        $newProductId = null;

        DB::transaction(function () use ($id, &$newProductId) {
            $original = Product::whereNull('deleted_at')
                ->with(['variants' => function ($q) {
                    $q->whereNull('deleted_at');
                }, 'images'])
                ->findOrFail($id);

            $newCode = $this->generateUniqueCode();

            $newImage = 'no-image.png';
            $oldImage = $original->image;
            if (! empty($oldImage) && $oldImage !== 'no-image.png') {
                $srcPath = public_path('/images/products/'.$oldImage);
                if (file_exists($srcPath)) {
                    $newImage = rand(11111111, 99999999).'_'.$oldImage;
                    $dstPath = public_path('/images/products/'.$newImage);
                    @copy($srcPath, $dstPath);
                }
            }

            $copy = $original->replicate();
            $copy->code = $newCode;
            $copy->image = $newImage;
            $copy->created_at = now();
            $copy->updated_at = now();
            $copy->save();

            $newProductId = $copy->id;

            $original->loadMissing(['categories', 'subcategories']);
            if (Schema::hasTable('category_product')) {
                if ($original->categories->isNotEmpty()) {
                    $copy->categories()->sync($original->categories->pluck('id')->all());
                } elseif ($copy->category_id) {
                    $copy->categories()->sync([(int) $copy->category_id]);
                }
            }
            if (Schema::hasTable('product_subcategory')) {
                if ($original->subcategories->isNotEmpty()) {
                    $copy->subcategories()->sync($original->subcategories->pluck('id')->all());
                } elseif ($copy->sub_category_id) {
                    $copy->subcategories()->sync([(int) $copy->sub_category_id]);
                }
            }

            ProductGalleryService::duplicateImages($original, $copy);
            if (Schema::hasTable('product_images') && $copy->images()->count() === 0 && ! empty($newImage) && $newImage !== 'no-image.png') {
                ProductImage::create([
                    'product_id' => $copy->id,
                    'image_path' => $newImage,
                    'is_main' => true,
                    'sort_order' => 0,
                ]);
            }

            $variantIdMap = [];
            if ($original->is_variant) {
                foreach ($original->variants as $variant) {
                    $newVariant = new ProductVariant;
                    $newVariant->product_id = $copy->id;
                    $newVariant->name = $variant->name;
                    $newVariant->cost = $variant->cost;
                    $newVariant->price = $variant->price;
                    $baseName = trim((string) $variant->name);
                    if ($baseName === '') {
                        $baseName = 'VAR';
                    }
                    $candidate = substr($baseName, 0, 50).'-'.$copy->code;
                    $newVariant->code = $this->ensureUniqueCode($candidate);
                    $newVariant->qty = 0;
                    $newVariant->image = $variant->image ?? 'no-image.png';
                    $newVariant->save();

                    $variantIdMap[$variant->id] = $newVariant->id;
                }
            }

            if ($original->type === 'is_combo') {
                $components = CombinedProduct::where('product_id', $original->id)->get(['combined_product_id', 'quantity']);
                if ($components->isNotEmpty()) {
                    $syncData = [];
                    foreach ($components as $c) {
                        $syncData[$c->combined_product_id] = ['quantity' => $c->quantity];
                    }
                    $copy->combinedProducts()->sync($syncData);
                }
            }

            $pws = product_warehouse::where('product_id', $original->id)
                ->whereNull('deleted_at')
                ->get(['warehouse_id', 'product_variant_id', 'manage_stock']);

            if ($pws->isNotEmpty()) {
                $rows = [];
                foreach ($pws as $pw) {
                    $rows[] = [
                        'product_id' => $copy->id,
                        'warehouse_id' => $pw->warehouse_id,
                        'product_variant_id' => $pw->product_variant_id ? ($variantIdMap[$pw->product_variant_id] ?? null) : null,
                        'manage_stock' => (int) ($pw->manage_stock ?? 1),
                        'qte' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($rows)) {
                    product_warehouse::insert($rows);
                }
            }
        }, 10);

        return response()->json(['success' => true, 'id' => $newProductId]);
    }

    protected function ensureUniqueCode(string $candidate): string
    {
        $code = substr($candidate, 0, 192);
        if (! $this->codeExistsAcrossTables($code)) {
            return $code;
        }

        for ($i = 2; $i <= 10; $i++) {
            $try = substr($candidate, 0, max(1, 192 - (strlen((string) $i) + 1))).'-'.$i;
            if (! $this->codeExistsAcrossTables($try)) {
                return $try;
            }
        }

        do {
            $try = (string) rand(10000000, 99999999);
        } while ($this->codeExistsAcrossTables($try));

        return $try;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = (string) rand(10000000, 99999999);
        } while ($this->codeExistsAcrossTables($code));

        return $code;
    }

    protected function codeExistsAcrossTables(string $code): bool
    {
        $inProducts = Product::whereNull('deleted_at')->where('code', $code)->exists();
        if ($inProducts) {
            return true;
        }
        $inVariants = ProductVariant::whereNull('deleted_at')->where('code', $code)->exists();

        return $inVariants;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeMultiCategoryIds($raw): array
    {
        return $this->normalizeOrderedMultiIds($raw);
    }

    /**
     * Decode request input to a flat int list, preserving order and first occurrence of each id.
     *
     * @return array<int, int>
     */
    protected function normalizeOrderedMultiIds($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($raw as $v) {
            if ($v === null || $v === '' || $v === 'null') {
                continue;
            }
            $i = (int) $v;
            if ($i <= 0 || isset($seen[$i])) {
                continue;
            }
            $seen[$i] = true;
            $out[] = $i;
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return array<int, int>
     */
    protected function filterCategoryIdsPreservingOrder(array $orderedIds): array
    {
        if ($orderedIds === []) {
            return [];
        }
        $allowed = Category::whereNull('deleted_at')
            ->whereIn('id', $orderedIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->flip()
            ->all();
        $out = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return array<int, int>
     */
    protected function filterSubcategoryIdsPreservingOrder(array $orderedIds): array
    {
        if ($orderedIds === []) {
            return [];
        }
        $allowed = SubCategory::whereIn('id', $orderedIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->flip()
            ->all();
        $out = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Dual-write: pivot tables plus primary category/subcategory columns (first selected wins).
     */
    protected function syncProductMultiCategories(Request $request, Product $product): void
    {
        $categoryDirty = false;
        $subDirty = false;

        if (Schema::hasTable('category_product') && $request->has('multi_category_ids')) {
            $ids = $this->normalizeOrderedMultiIds($request->input('multi_category_ids'));
            if ($ids !== []) {
                $valid = $this->filterCategoryIdsPreservingOrder($ids);
                if ($valid !== []) {
                    $product->category_id = $valid[0];
                    $categoryDirty = true;
                    $product->categories()->sync($valid);
                }
            }
        }

        if (Schema::hasTable('product_subcategory') && $request->has('multi_subcategory_ids')) {
            $subIds = $this->normalizeOrderedMultiIds($request->input('multi_subcategory_ids'));
            if ($subIds !== []) {
                $validSub = $this->filterSubcategoryIdsPreservingOrder($subIds);
                $product->sub_category_id = $validSub !== [] ? $validSub[0] : null;
                $subDirty = true;
                $product->subcategories()->sync($validSub);
            } else {
                $product->sub_category_id = null;
                $subDirty = true;
                $product->subcategories()->detach();
            }
        }

        if ($categoryDirty || $subDirty) {
            $product->saveQuietly();
        }
    }
}
