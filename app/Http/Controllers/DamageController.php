<?php

namespace App\Http\Controllers;

use App\Models\CombinedProduct;
use App\Models\Damage;
use App\Models\DamageDetail;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Services\BatchService;
use App\utils\helpers;
use ArPHP\I18N\Arabic;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class DamageController extends BaseController
{
    // ------------ Show All Damages  -----------\\
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Damage::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $is_all_warehouses = $user->is_all_warehouses;
        // If the user is restricted, fetch their assigned warehouse IDs once and reuse below.
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }

        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        $columns = [0 => 'Ref', 1 => 'warehouse_id', 2 => 'date'];
        $param = [0 => 'like', 1 => '=', 2 => '='];
        $data = [];

        $Damages = Damage::with('warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        if (! $is_all_warehouses) {
            $Damages->whereIn('warehouse_id', $warehouse_ids);
        }

        $Filtred = $helpers->filter($Damages, $columns, $param, $request)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($q) use ($request) {
                            return $q->whereHas('warehouse', function ($q2) use ($request) {
                                $q2->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $Damages = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Damages as $DamageRow) {
            $item['id'] = $DamageRow->id;
            $item['date'] = $DamageRow['date'].' '.$DamageRow['time'];
            $item['Ref'] = $DamageRow->Ref;
            $item['warehouse_name'] = $DamageRow['warehouse']->name;
            $item['items'] = $DamageRow->items;
            $data[] = $item;
        }

        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'damages' => $data,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
        ]);
    }

    // ------------ Store New Damage -----------\\
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Damage::class);

        $productionRules = [
            'warehouse_id' => 'required',
            'details' => 'required',
        ];
        $request->validate($productionRules, [
            'warehouse_id.required' => 'Warehouse is required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new Damage;
            $order->date = $request->date;
            $order->time = now()->toTimeString();
            $order->Ref = $this->getNumberOrder();
            $order->warehouse_id = $request->warehouse_id;
            $order->notes = $request->notes;
            $order->items = count($request['details']);
            $order->user_id = Auth::user()->id;
            $order->save();

            $data = $request['details'];
            $persistedDetails = [];
            foreach ($data as $key => $value) {
                $persistedDetails[$key] = DamageDetail::create([
                    'damage_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'] ?? null,
                ]);

                // Always subtract for damage
                if (! empty($value['product_variant_id'])) {
                    $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->where('product_id', $value['product_id'])
                        ->where('product_variant_id', $value['product_variant_id'])
                        ->first();

                    if ($product_warehouse) {
                        $product_warehouse->qte -= $value['quantity'];
                        if ($product_warehouse->qte < 0) {
                            $product_warehouse->qte = 0;
                        }
                        $product_warehouse->save();
                    }
                } else {
                    $product_detail = Product::where('deleted_at', '=', null)
                        ->where('id', $value['product_id'])
                        ->first();

                    if ($product_detail && $product_detail->type == 'is_single') {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte -= $value['quantity'];
                            if ($product_warehouse->qte < 0) {
                                $product_warehouse->qte = 0;
                            }
                            $product_warehouse->save();
                        }
                    } elseif ($product_detail && $product_detail->type == 'is_combo') {
                        $combined_products = CombinedProduct::where('product_id', $value['product_id'])->with('product')->get();

                        foreach ($combined_products as $combined_product) {
                            $qty_combined = $combined_product->quantity * $value['quantity'];

                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $order->warehouse_id)
                                ->where('product_id', $combined_product->combined_product_id)
                                ->first();

                            if ($product_warehouse) {
                                $product_warehouse->qte -= $qty_combined;
                                if ($product_warehouse->qte < 0) {
                                    $product_warehouse->qte = 0;
                                }
                                $product_warehouse->save();
                            }
                        }

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte -= $value['quantity'];
                            if ($product_warehouse->qte < 0) {
                                $product_warehouse->qte = 0;
                            }
                            $product_warehouse->save();
                        }
                    }
                }
            }

            // Pharmacy: debit user-picked batches alongside the warehouse-stock change
            // we just made above so the per-batch ledger stays consistent.
            $batchService = app(BatchService::class);
            if ($batchService->isSupported()) {
                $batchService->applyForDamageWithAutoFallback(
                    $order,
                    array_values($data),
                    $persistedDetails
                );
            }
        }, 10);

        return response()->json(['success' => true]);
    }

    public function show($id)
    {
        // not used
    }

    // --------------- Update Damage ----------------------\\
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Damage::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $current_damage = Damage::findOrFail($id);

        if (! $view_records) {
            $this->authorizeForUser($request->user('api'), 'check_record', $current_damage);
        }

        request()->validate([
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id, $current_damage) {
            $old_details = DamageDetail::where('damage_id', $id)->get();
            $new_details = $request['details'];
            $length = count($new_details);

            $new_ids = [];
            foreach ($new_details as $new_detail) {
                $new_ids[] = $new_detail['id'];
            }

            // Pharmacy: reverse old batch debits before warehouse-stock reversal so
            // the per-batch ledger mirrors the warehouse change.
            $batchService = app(BatchService::class);
            if ($batchService->isSupported()) {
                $batchService->reverseForDamageDetails($old_details);
            }

            $old_ids = [];
            foreach ($old_details as $key => $value) {
                $old_ids[] = $value->id;

                // Reverse previous subtraction
                if ($value['product_variant_id'] !== null) {
                    $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $current_damage->warehouse_id)
                        ->where('product_id', $value['product_id'])
                        ->where('product_variant_id', $value['product_variant_id'])
                        ->first();

                    if ($product_warehouse) {
                        $product_warehouse->qte += $value['quantity'];
                        $product_warehouse->save();
                    }
                } else {
                    $product_detail = Product::where('deleted_at', '=', null)
                        ->where('id', $value['product_id'])
                        ->first();

                    if ($product_detail && $product_detail->type == 'is_single') {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_damage->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte += $value['quantity'];
                            $product_warehouse->save();
                        }
                    } elseif ($product_detail && $product_detail->type == 'is_combo') {
                        $combined_products = CombinedProduct::where('product_id', $value['product_id'])->with('product')->get();
                        foreach ($combined_products as $combined_product) {
                            $qty_combined = $combined_product->quantity * $value['quantity'];

                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_damage->warehouse_id)
                                ->where('product_id', $combined_product->combined_product_id)
                                ->first();

                            if ($product_warehouse) {
                                $product_warehouse->qte += $qty_combined;
                                $product_warehouse->save();
                            }
                        }

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_damage->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte += $value['quantity'];
                            $product_warehouse->save();
                        }
                    }
                }

                if (! in_array($old_ids[$key], $new_ids)) {
                    $detail = DamageDetail::findOrFail($value->id);
                    $detail->delete();
                }
            }

            $newPersistedDetails = [];
            foreach ($new_details as $key => $product_detail) {
                // Apply new subtraction
                if (! empty($product_detail['product_variant_id'])) {
                    $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $request->warehouse_id)
                        ->where('product_id', $product_detail['product_id'])
                        ->where('product_variant_id', $product_detail['product_variant_id'])
                        ->first();

                    if ($product_warehouse) {
                        $product_warehouse->qte -= $product_detail['quantity'];
                        if ($product_warehouse->qte < 0) {
                            $product_warehouse->qte = 0;
                        }
                        $product_warehouse->save();
                    }
                } else {
                    $prod = Product::where('deleted_at', '=', null)
                        ->where('id', $product_detail['product_id'])
                        ->first();

                    if ($prod && $prod->type == 'is_single') {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte -= $product_detail['quantity'];
                            if ($product_warehouse->qte < 0) {
                                $product_warehouse->qte = 0;
                            }
                            $product_warehouse->save();
                        }
                    } elseif ($prod && $prod->type == 'is_combo') {
                        $combined_products = CombinedProduct::where('product_id', $product_detail['product_id'])->with('product')->get();
                        foreach ($combined_products as $combined_product) {
                            $qty_combined = $combined_product->quantity * $product_detail['quantity'];
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $combined_product->combined_product_id)
                                ->first();

                            if ($product_warehouse) {
                                $product_warehouse->qte -= $qty_combined;
                                if ($product_warehouse->qte < 0) {
                                    $product_warehouse->qte = 0;
                                }
                                $product_warehouse->save();
                            }
                        }

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte -= $product_detail['quantity'];
                            if ($product_warehouse->qte < 0) {
                                $product_warehouse->qte = 0;
                            }
                            $product_warehouse->save();
                        }
                    }
                }

                $orderDetails['damage_id'] = $id;
                $orderDetails['quantity'] = $product_detail['quantity'];
                $orderDetails['product_id'] = $product_detail['product_id'];
                $orderDetails['product_variant_id'] = $product_detail['product_variant_id'] ?? null;

                if (! in_array($product_detail['id'], $old_ids)) {
                    $persistedDetail = DamageDetail::Create($orderDetails);
                } else {
                    DamageDetail::where('id', $product_detail['id'])->update($orderDetails);
                    $persistedDetail = DamageDetail::find($product_detail['id']);
                }
                $newPersistedDetails[$key] = $persistedDetail;
            }

            // Pharmacy: re-apply per-batch debits now that DamageDetail rows exist.
            // Lockstep alignment so any skipped rows don't misalign indices.
            if ($batchService->isSupported()) {
                $alignedInput = [];
                $alignedPersisted = [];
                foreach ($new_details as $key => $product_detail) {
                    if (isset($newPersistedDetails[$key])) {
                        $alignedInput[] = $product_detail;
                        $alignedPersisted[] = $newPersistedDetails[$key];
                    }
                }
                $current_damage->warehouse_id = (int) $request['warehouse_id'];
                $batchService->applyForDamageWithAutoFallback(
                    $current_damage,
                    $alignedInput,
                    $alignedPersisted
                );
            }

            $current_damage->update([
                'warehouse_id' => $request['warehouse_id'],
                'notes' => $request['notes'],
                'date' => $request['date'],
                'items' => $length,
            ]);
        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------ Delete Damage -----------\\
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Damage::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $current_damage = Damage::findOrFail($id);
            $old_details = DamageDetail::where('damage_id', $id)->get();

            if (! $view_records) {
                $this->authorizeForUser($request->user('api'), 'check_record', $current_damage);
            }

            // Pharmacy: reverse batch debits before warehouse-stock reversal so the
            // per-batch ledger mirrors the warehouse change.
            $batchService = app(BatchService::class);
            if ($batchService->isSupported()) {
                $batchService->reverseForDamageDetails($old_details);
            }

            foreach ($old_details as $key => $value) {
                // Reverse subtraction (add back)
                if ($value['product_variant_id'] !== null) {
                    $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $current_damage->warehouse_id)
                        ->where('product_id', $value['product_id'])
                        ->where('product_variant_id', $value['product_variant_id'])
                        ->first();

                    if ($product_warehouse) {
                        $product_warehouse->qte += $value['quantity'];
                        $product_warehouse->save();
                    }
                } else {
                    $product_detail = Product::where('deleted_at', '=', null)
                        ->where('id', $value['product_id'])
                        ->first();

                    if ($product_detail && $product_detail->type == 'is_single') {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_damage->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte += $value['quantity'];
                            $product_warehouse->save();
                        }
                    } elseif ($product_detail && $product_detail->type == 'is_combo') {
                        $combined_products = CombinedProduct::where('product_id', $value['product_id'])->with('product')->get();
                        foreach ($combined_products as $combined_product) {
                            $qty_combined = $combined_product->quantity * $value['quantity'];

                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_damage->warehouse_id)
                                ->where('product_id', $combined_product->combined_product_id)
                                ->first();

                            if ($product_warehouse) {
                                $product_warehouse->qte += $qty_combined;
                                $product_warehouse->save();
                            }
                        }

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_damage->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_warehouse) {
                            $product_warehouse->qte += $value['quantity'];
                            $product_warehouse->save();
                        }
                    }
                }
            }
            $current_damage->details()->delete();

            $current_damage->update([
                'deleted_at' => Carbon::now(),
            ]);
        }, 10);

        return response()->json(['success' => true], 200);
    }

    // -------------Show Form Create Damage-----------\\
    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Damage::class);

        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json(['warehouses' => $warehouses]);
    }

    // -------------Show Form Edit Damage-----------\\
    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Damage::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Damage_data = Damage::with('details.product')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = [];

        if (! $view_records) {
            $this->authorizeForUser($request->user('api'), 'check_record', $Damage_data);
        }

        if ($Damage_data->warehouse_id) {
            if (Warehouse::where('id', $Damage_data->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $damage['warehouse_id'] = $Damage_data->warehouse_id;
            } else {
                $damage['warehouse_id'] = '';
            }
        } else {
            $damage['warehouse_id'] = '';
        }

        $damage['notes'] = $Damage_data->notes;
        $damage['date'] = $Damage_data->date;

        $batchesByDetail = app(BatchService::class)->batchesForDamageDetails($Damage_data['details']);

        $detail_id = 0;
        foreach ($Damage_data['details'] as $detail) {
            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('warehouse_id', $Damage_data->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['id'] = $detail->id;
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];
                $data['current'] = $item_product ? $item_product->qte : 0;
                $data['type'] = 'sub';
                $data['unit'] = $detail['product']['unit']->ShortName;
                $data['del'] = $item_product ? 0 : 1;
                $data['product_type'] = $detail['product']['type'] ?? 'is_single';
            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $Damage_data->warehouse_id)
                    ->where('product_variant_id', '=', null)
                    ->first();

                $data['id'] = $detail->id;
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                $data['current'] = $item_product ? $item_product->qte : 0;
                $data['type'] = 'sub';
                $data['unit'] = $detail['product']['unit']->ShortName;
                $data['del'] = $item_product ? 0 : 1;
                $data['product_type'] = $detail['product']['type'] ?? 'is_single';
            }

            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            $data['batches'] = $batchesByDetail[(int) $detail->id] ?? [];

            $details[] = $data;
        }

        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'details' => $details,
            'damage' => $damage,
            'warehouses' => $warehouses,
        ]);
    }

    // ---------------- Get Details Damage-----------------\\
    public function Damage_detail(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Damage::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Damage_data = Damage::with('details.product.unit')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = [];

        if (! $view_records) {
            $this->authorizeForUser($request->user('api'), 'check_record', $Damage_data);
        }

        $DamageArr['Ref'] = $Damage_data->Ref;
        $DamageArr['date'] = $Damage_data->date;
        $DamageArr['note'] = $Damage_data->notes;
        $DamageArr['warehouse'] = $Damage_data['warehouse']->name;

        $batchesByDetail = app(BatchService::class)->batchesForDamageDetails($Damage_data['details']);

        foreach ($Damage_data['details'] as $detail) {
            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)
                    ->first();

                $data['quantity'] = $detail->quantity;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];
                $data['unit'] = $detail['product']['unit']->ShortName;
                $data['type'] = 'sub';
            } else {
                $data['quantity'] = $detail->quantity;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                $data['type'] = 'sub';
                $data['unit'] = $detail['product']['unit']->ShortName;
            }

            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            $data['batches'] = $batchesByDetail[(int) $detail->id] ?? [];

            $details[] = $data;
        }

        return response()->json([
            'details' => $details,
            'damage' => $DamageArr,
        ]);
    }

    // -------------- damage_pdf -----------\\
    public function damage_pdf(Request $request, $id)
    {
        $details = [];
        $helpers = new helpers;
        $damage_data = Damage::with('details.product.unit')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $adjustment['warehouse_name'] = $damage_data['warehouse']->name;
        $adjustment['Ref'] = $damage_data->Ref;
        $adjustment['date'] = $damage_data->date.' '.$damage_data->time;

        $detail_id = 0;
        foreach ($damage_data['details'] as $detail) {
            $data['detail_id'] = $detail_id += 1;

            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)
                    ->first();

                $data['quantity'] = '-'.' '.number_format($detail->quantity, 2, '.', '');
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];
                $data['unit'] = $detail['product']['unit']->ShortName;
            } else {
                $data['quantity'] = '-'.' '.number_format($detail->quantity, 2, '.', '');
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                $data['unit'] = $detail['product']['unit']->ShortName;
            }
            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $Html = view('pdf.adjustment_pdf', [
            'setting' => $settings,
            'adjustment' => $adjustment,
            'details' => $details,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Damage.pdf');
    }

    // ------------- Delete by selection  ---------------\\
    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Damage::class);

        foreach ($request->selectedIds as $id) {
            $this->destroy($request, $id);
        }

        return response()->json(['success' => true]);
    }

    // ------ batches_for_damage ---------------\\
    //
    // Returns FEFO-ordered active batches at the source warehouse for batch-tracked
    // products. Mirrors batches_for_sale but authorized via the Damage policy.
    public function batches_for_damage(Request $request, $product_id, $warehouse_id, $variant_id = null)
    {
        $this->authorizeForUser($request->user('api'), 'create', Damage::class);

        $productId = (int) $product_id;
        $warehouseId = (int) $warehouse_id;
        $variantId = ($variant_id !== null && $variant_id !== '' && $variant_id !== 'null' && (int) $variant_id > 0)
            ? (int) $variant_id
            : null;

        $batchService = app(BatchService::class);

        return response()->json([
            'supported' => $batchService->isSupported(),
            'batches' => $batchService->availableBatchesForSale($productId, $variantId, $warehouseId),
        ]);
    }

    // ------------ Reference Number of Damage  -----------\\
    public function getNumberOrder()
    {
        $last = DB::table('damages')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            $inMsg = isset($nwMsg[1]) ? ($nwMsg[1] + 1) : 1112;
            $code = 'DM_'.$inMsg;
        } else {
            $code = 'DM_1111';
        }

        return $code;
    }
}





