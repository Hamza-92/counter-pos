<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Unit;
use App\Models\User;
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

class TransferController extends BaseController
{
    // ------------ Show All Transfers  -----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Transfer::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $is_all_warehouses = $user->is_all_warehouses;

        $warehouse_ids = [];
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        // Filter fields With Params to retrieve
        $columns = [0 => 'Ref', 1 => 'from_warehouse_id', 2 => 'to_warehouse_id', 3 => 'statut'];
        $param = [0 => 'like', 1 => '=', 2 => '=', 3 => 'like'];
        $data = [];

        // Check If User Has Permission View  All Records
        $transfers = Transfer::with('from_warehouse', 'to_warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

            // ✅ Restrict by warehouses (from OR to)
        if (! $is_all_warehouses) {
            $transfers->where(function ($q) use ($warehouse_ids) {
                $q->whereIn('from_warehouse_id', $warehouse_ids)
                ->orWhereIn('to_warehouse_id', $warehouse_ids);
            });
        }

        // Multiple Filter
        $Filtred = $helpers->filter($transfers, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('from_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('to_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $transfers = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($transfers as $transfer) {
            $item['id'] = $transfer->id;
            $item['date'] = $transfer['date'].' '.$transfer['time'];
            $item['Ref'] = $transfer->Ref;
            $item['from_warehouse'] = $transfer['from_warehouse']->name;
            $item['to_warehouse'] = $transfer['to_warehouse']->name;
            $item['GrandTotal'] = $transfer->GrandTotal;
            $item['items'] = $transfer->items;
            $item['statut'] = $transfer->statut;
            // NEW: explicit approval status (old NULL rows are treated as approved in the model accessor)
            $item['approval_status'] = $transfer->approval_status;
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

        return response()->json([
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
            'transfers' => $data,
        ]);
    }

    // ------------ Store New Transfer -----------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Transfer::class);

        request()->validate([
            'transfer.from_warehouse' => 'required',
            'transfer.to_warehouse' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new Transfer;

            $order->date = $request->transfer['date'];
            $order->Ref = $this->getNumberOrder();
            $order->from_warehouse_id = $request->transfer['from_warehouse'];
            $order->to_warehouse_id = $request->transfer['to_warehouse'];
            $order->items = count($request['details']);
            $order->tax_rate = $request->transfer['tax_rate'] ? $request->transfer['tax_rate'] : 0;
            $order->TaxNet = $request->transfer['TaxNet'] ? $request->transfer['TaxNet'] : 0;
            $order->discount = $request->transfer['discount'] ? $request->transfer['discount'] : 0;
            $order->shipping = $request->transfer['shipping'] ? $request->transfer['shipping'] : 0;
            $order->statut = $request->transfer['statut'];
            $order->notes = $request->transfer['notes'];
            $order->GrandTotal = $request['GrandTotal'];
            $order->user_id = Auth::user()->id;
            // NEW APPROVAL LOGIC:
            // All NEW transfers start as "pending" and do NOT move stock until explicitly approved.
            $order->approval_status = 'pending';
            $order->save();

            $data = $request['details'];

            // Stock movement is now gated behind approval:
            // - For new transfers (approval_status = pending), we only create the header & details.
            // - Actual stock movement happens later, when a manager approves the transfer.
            $shouldAffectStock = $order->isApproved();
            $persistedDetails = [];

            foreach ($data as $key => $value) {

                $unit = Unit::where('id', $value['purchase_unit_id'])->first();

                if ($shouldAffectStock && $request->transfer['statut'] == 'completed') {
                    if ($value['product_variant_id'] !== null) {

                        // --------- eliminate the quantity ''from_warehouse''--------------\\
                        $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['from_warehouse'])
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse_from) {
                            if ($unit->operator == '/') {
                                $product_warehouse_from->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_from->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_from->save();
                        }

                        // --------- ADD the quantity ''TO_warehouse''------------------\\
                        $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['to_warehouse'])
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse_to) {
                            if ($unit->operator == '/') {
                                $product_warehouse_to->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_to->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_to->save();
                        }

                    } else {

                        // --------- eliminate the quantity ''from_warehouse''--------------\\
                        $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['from_warehouse'])
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $product_warehouse_from) {
                            if ($unit->operator == '/') {
                                $product_warehouse_from->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_from->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_from->save();
                        }

                        // --------- ADD the quantity ''TO_warehouse''------------------\\
                        $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['to_warehouse'])
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $product_warehouse_to) {
                            if ($unit->operator == '/') {
                                $product_warehouse_to->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_to->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_to->save();
                        }
                    }

                } elseif ($shouldAffectStock && $request->transfer['statut'] == 'sent') {

                    if ($value['product_variant_id'] !== null) {

                        $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['from_warehouse'])
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse_from) {
                            if ($unit->operator == '/') {
                                $product_warehouse_from->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_from->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_from->save();
                        }

                    } else {

                        $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $request->transfer['from_warehouse'])
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $product_warehouse_from) {
                            if ($unit->operator == '/') {
                                $product_warehouse_from->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse_from->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse_from->save();
                        }
                    }
                }

                $persistedDetails[$key] = TransferDetail::create([
                    'transfer_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'purchase_unit_id' => $value['purchase_unit_id'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'cost' => $value['Unit_cost'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'total' => $value['subtotal'],
                ]);
            }

            // Pharmacy: apply per-batch movement only when warehouse stock is also being
            // touched (i.e. the transfer was created already approved AND its statut is
            // either "completed" or "sent"). Pending/draft transfers don't move batches yet.
            if ($shouldAffectStock && in_array($request->transfer['statut'], ['completed', 'sent'], true)) {
                $batchService = app(BatchService::class);
                if ($batchService->isSupported()) {
                    $batchService->applyForTransferWithAutoFallback(
                        $order,
                        array_values($data),
                        $persistedDetails
                    );
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------- Update Transfer -----------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Transfer::class);

        request()->validate([
            'transfer.to_warehouse' => 'required',
            'transfer.from_warehouse' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $current_Transfer = Transfer::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === Transfer->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_Transfer);
            }

            $Old_Details = TransferDetail::where('transfer_id', $id)->get();
            $data = $request['details'];
            $Trans = $request->transfer;
            $length = count($data);

            // Only already-approved transfers affect stock at update time.
            // Pending or rejected transfers never touch stock here.
            $isApproved = $current_Transfer->isApproved();

            // Get Ids details
            $new_products_id = [];
            foreach ($data as $new_detail) {
                $new_products_id[] = $new_detail['id'];
            }

            // Pharmacy: reverse old batch movements before the warehouse-stock reversal
            // below so the per-batch ledger ends up consistent. Only matters when the
            // current transfer is one that actually touched stock (approved + not pending).
            $batchService = app(BatchService::class);
            if ($batchService->isSupported()
                && $isApproved
                && in_array($current_Transfer->statut, ['completed', 'sent'], true)) {
                $batchService->reverseForTransferDetails($Old_Details);
            }

            // Init Data with old Parametre
            $old_products_id = [];
            foreach ($Old_Details as $key => $value) {
                // check if detail has purchase_unit_id Or Null
                if ($value['purchase_unit_id'] !== null) {
                    $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                } else {
                    $product_unit_purchase_id = Product::with('unitPurchase')
                        ->where('id', $value['product_id'])
                        ->first();
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }

                $old_products_id[] = $value->id;

                if ($value['purchase_unit_id'] !== null) {

                    if ($isApproved && $current_Transfer->statut == 'completed') {
                        if ($value['product_variant_id'] !== null) {

                            $warehouse_from_variant = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $warehouse_from_variant) {
                                if ($unit->operator == '/') {
                                    $warehouse_from_variant->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_from_variant->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_from_variant->save();
                            }

                            $warehouse_To_variant = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $warehouse_To_variant) {
                                if ($unit->operator == '/') {
                                    $warehouse_To_variant->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_To_variant->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_To_variant->save();
                            }

                        } else {
                            $warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $warehouse_from) {
                                if ($unit->operator == '/') {
                                    $warehouse_from->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_from->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_from->save();
                            }

                            $warehouse_To = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $warehouse_To) {
                                if ($unit->operator == '/') {
                                    $warehouse_To->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_To->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_To->save();
                            }
                        }

                    } elseif ($isApproved && $current_Transfer->statut == 'sent') {
                        if ($value['product_variant_id'] !== null) {

                            $Sent_variant_To = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $Sent_variant_To) {
                                if ($unit->operator == '/') {
                                    $Sent_variant_To->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $Sent_variant_To->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $Sent_variant_To->save();
                            }
                        } else {
                            $Sent_variant_From = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $Sent_variant_From) {
                                if ($unit->operator == '/') {
                                    $Sent_variant_From->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $Sent_variant_From->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $Sent_variant_From->save();
                            }
                        }
                    }

                    // Delete Detail
                    if (! in_array($old_products_id[$key], $new_products_id)) {
                        $TransferDetail = TransferDetail::findOrFail($value->id);
                        $TransferDetail->delete();
                    }
                }

            }

            // Update Data with New request
            $newPersistedDetails = [];
            foreach ($data as $key => $product_detail) {

                if ($product_detail['no_unit'] !== 0) {
                    $unit = Unit::where('id', $product_detail['purchase_unit_id'])->first();
                    if ($isApproved && $Trans['statut'] == 'completed') {
                        if ($product_detail['product_variant_id'] !== null) {

                            // --------- eliminate the quantity ''from_warehouse''--------------\\
                            $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['from_warehouse'])
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit && $product_warehouse_from) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_from->save();
                            }

                            // --------- ADD the quantity ''TO_warehouse''------------------\\
                            $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['to_warehouse'])
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit && $product_warehouse_to) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_to->qte += $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_to->qte += $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_to->save();
                            }

                        } else {

                            // --------- eliminate the quantity ''from_warehouse''--------------\\
                            $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['from_warehouse'])
                                ->where('product_id', $product_detail['product_id'])->first();

                            if ($unit && $product_warehouse_from) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_from->save();
                            }

                            // --------- ADD the quantity ''TO_warehouse''------------------\\
                            $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['to_warehouse'])
                                ->where('product_id', $product_detail['product_id'])->first();

                            if ($unit && $product_warehouse_to) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_to->qte += $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_to->qte += $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_to->save();
                            }
                        }

                    } elseif ($isApproved && $Trans['statut'] == 'sent') {

                        if ($product_detail['product_variant_id'] !== null) {

                            $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['from_warehouse'])
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit && $product_warehouse_from) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_from->save();
                            }

                        } else {

                            $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $Trans['from_warehouse'])
                                ->where('product_id', $product_detail['product_id'])->first();

                            if ($unit && $product_warehouse_from) {
                                if ($unit->operator == '/') {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse_from->qte -= $product_detail['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse_from->save();
                            }
                        }
                    }

                    $TransDetail['transfer_id'] = $id;
                    $TransDetail['quantity'] = $product_detail['quantity'];
                    $TransDetail['purchase_unit_id'] = $product_detail['purchase_unit_id'];
                    $TransDetail['product_id'] = $product_detail['product_id'];
                    $TransDetail['product_variant_id'] = $product_detail['product_variant_id'];
                    $TransDetail['cost'] = $product_detail['Unit_cost'];
                    $TransDetail['TaxNet'] = $product_detail['tax_percent'];
                    $TransDetail['tax_method'] = $product_detail['tax_method'];
                    $TransDetail['discount'] = $product_detail['discount'];
                    $TransDetail['discount_method'] = $product_detail['discount_Method'];
                    $TransDetail['total'] = $product_detail['subtotal'];

                    if (! in_array($product_detail['id'], $old_products_id)) {
                        $persistedDetail = TransferDetail::Create($TransDetail);
                    } else {
                        TransferDetail::where('id', $product_detail['id'])->update($TransDetail);
                        $persistedDetail = TransferDetail::find($product_detail['id']);
                    }
                    $newPersistedDetails[$key] = $persistedDetail;
                }
            }

            $current_Transfer->update([
                'to_warehouse_id' => $Trans['to_warehouse'],
                'from_warehouse_id' => $Trans['from_warehouse'],
                'date' => $Trans['date'],
                'notes' => $Trans['notes'],
                'statut' => $Trans['statut'],
                'items' => count($request['details']),
                'tax_rate' => $Trans['tax_rate'] ? $Trans['tax_rate'] : 0,
                'TaxNet' => $Trans['TaxNet'] ? $Trans['TaxNet'] : 0,
                'discount' => $Trans['discount'] ? $Trans['discount'] : 0,
                'shipping' => $Trans['shipping'] ? $Trans['shipping'] : 0,
                'GrandTotal' => $request['GrandTotal'],
            ]);

            // Pharmacy: re-apply per-batch movements now that TransferDetail rows exist.
            // Only when the new state actually touches stock (approved + completed/sent).
            // Keep input + persisted in lockstep so no_unit==0 rows don't misalign indices.
            if ($batchService->isSupported()
                && $isApproved
                && in_array($Trans['statut'], ['completed', 'sent'], true)) {
                $alignedInput = [];
                $alignedPersisted = [];
                foreach ($data as $key => $product_detail) {
                    if (isset($newPersistedDetails[$key])) {
                        $alignedInput[] = $product_detail;
                        $alignedPersisted[] = $newPersistedDetails[$key];
                    }
                }
                $batchService->applyForTransferWithAutoFallback(
                    $current_Transfer->fresh(),
                    $alignedInput,
                    $alignedPersisted
                );
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------ Delete Transfer -----------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Transfer::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $current_Transfer = Transfer::findOrFail($id);
            $Old_Details = TransferDetail::where('transfer_id', $id)->get();

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === current_Transfer->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_Transfer);
            }

            // Only already-approved transfers affect stock when being deleted.
            $isApproved = $current_Transfer->isApproved();

            // Pharmacy: reverse batch movements before warehouse-stock reversal so the
            // per-batch ledger mirrors the warehouse change.
            $batchService = app(BatchService::class);
            if ($batchService->isSupported()
                && $isApproved
                && in_array($current_Transfer->statut, ['completed', 'sent'], true)) {
                $batchService->reverseForTransferDetails($Old_Details);
            }

            // Init Data with old Parametre
            foreach ($Old_Details as $key => $value) {
                // check if detail has purchase_unit_id Or Null
                if ($value['purchase_unit_id'] !== null) {
                    $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                } else {
                    $product_unit_purchase_id = Product::with('unitPurchase')
                        ->where('id', $value['product_id'])
                        ->first();
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }

                if ($isApproved && $current_Transfer->statut == 'completed') {
                    if ($value['product_variant_id'] !== null) {

                        $warehouse_from_variant = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $warehouse_from_variant) {
                            if ($unit->operator == '/') {
                                $warehouse_from_variant->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $warehouse_from_variant->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $warehouse_from_variant->save();
                        }

                        $warehouse_To_variant = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $warehouse_To_variant) {
                            if ($unit->operator == '/') {
                                $warehouse_To_variant->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $warehouse_To_variant->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $warehouse_To_variant->save();
                        }

                    } else {
                        $warehouse_from = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $warehouse_from) {
                            if ($unit->operator == '/') {
                                $warehouse_from->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $warehouse_from->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $warehouse_from->save();
                        }

                        $warehouse_To = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $warehouse_To) {
                            if ($unit->operator == '/') {
                                $warehouse_To->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $warehouse_To->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $warehouse_To->save();
                        }
                    }

                } elseif ($isApproved && $current_Transfer->statut == 'sent') {
                    if ($value['product_variant_id'] !== null) {

                        $Sent_variant_To = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $Sent_variant_To) {
                            if ($unit->operator == '/') {
                                $Sent_variant_To->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $Sent_variant_To->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $Sent_variant_To->save();
                        }
                    } else {
                        $Sent_variant_From = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                            ->where('product_id', $value['product_id'])->first();

                        if ($unit && $Sent_variant_From) {
                            if ($unit->operator == '/') {
                                $Sent_variant_From->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $Sent_variant_From->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $Sent_variant_From->save();
                        }
                    }
                }

            }

            $current_Transfer->details()->delete();
            $current_Transfer->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Transfer::class);

        \DB::transaction(function () use ($request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $Transfer_id) {
                $current_Transfer = Transfer::findOrFail($Transfer_id);
                $Old_Details = TransferDetail::where('transfer_id', $Transfer_id)->get();

                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === Transfer->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Transfer);
                }

                // Only already-approved transfers affect stock when being deleted.
                $isApproved = $current_Transfer->isApproved();

                // Pharmacy: reverse batch movements before warehouse-stock reversal so
                // the per-batch ledger mirrors the warehouse change. Without this the
                // bulk-delete path would orphan transfer_detail_batches rows and drift
                // ProductBatch.qty.
                $batchService = app(BatchService::class);
                if ($batchService->isSupported()
                    && $isApproved
                    && in_array($current_Transfer->statut, ['completed', 'sent'], true)) {
                    $batchService->reverseForTransferDetails($Old_Details);
                }

                // Init Data with old Parametre
                foreach ($Old_Details as $key => $value) {
                    // check if detail has purchase_unit_id Or Null
                    if ($value['purchase_unit_id'] !== null) {
                        $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                    } else {
                        $product_unit_purchase_id = Product::with('unitPurchase')
                            ->where('id', $value['product_id'])
                            ->first();
                        $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                    }

                    if ($isApproved && $current_Transfer->statut == 'completed') {
                        if ($value['product_variant_id'] !== null) {

                            $warehouse_from_variant = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $warehouse_from_variant) {
                                if ($unit->operator == '/') {
                                    $warehouse_from_variant->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_from_variant->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_from_variant->save();
                            }

                            $warehouse_To_variant = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $warehouse_To_variant) {
                                if ($unit->operator == '/') {
                                    $warehouse_To_variant->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_To_variant->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_To_variant->save();
                            }

                        } else {
                            $warehouse_from = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $warehouse_from) {
                                if ($unit->operator == '/') {
                                    $warehouse_from->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_from->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_from->save();
                            }

                            $warehouse_To = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->to_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $warehouse_To) {
                                if ($unit->operator == '/') {
                                    $warehouse_To->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $warehouse_To->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $warehouse_To->save();
                            }
                        }

                    } elseif ($isApproved && $current_Transfer->statut == 'sent') {
                        if ($value['product_variant_id'] !== null) {

                            $Sent_variant_To = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $Sent_variant_To) {
                                if ($unit->operator == '/') {
                                    $Sent_variant_To->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $Sent_variant_To->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $Sent_variant_To->save();
                            }
                        } else {
                            $Sent_variant_From = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Transfer->from_warehouse_id)
                                ->where('product_id', $value['product_id'])->first();

                            if ($unit && $Sent_variant_From) {
                                if ($unit->operator == '/') {
                                    $Sent_variant_From->qte += $value['quantity'] / $unit->operator_value;
                                } else {
                                    $Sent_variant_From->qte += $value['quantity'] * $unit->operator_value;
                                }
                                $Sent_variant_From->save();
                            }
                        }
                    }

                }

                $current_Transfer->details()->delete();
                $current_Transfer->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------ Reference Number of transfers  -----------\\

    // ------ batches_for_transfer ---------------\\
    //
    // Returns FEFO-ordered active batches at the SOURCE warehouse for batch-tracked
    // products. Mirrors batches_for_sale but authorized via the Transfer policy.

    public function batches_for_transfer(Request $request, $product_id, $warehouse_id, $variant_id = null)
    {
        $this->authorizeForUser($request->user('api'), 'create', Transfer::class);

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

    public function getNumberOrder()
    {
        // Get prefix from settings, fallback to 'TR' if not set
        $setting = \App\Models\Setting::where('deleted_at', '=', null)->first();
        $prefix = !empty($setting->transfer_prefix) ? $setting->transfer_prefix : 'TR';
        
        // Get the last transfer with a reference that starts with the prefix
        $last = DB::table('transfers')
            ->where('Ref', 'like', $prefix.'_%')
            ->latest('id')
            ->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            
            // Ensure valid structure before processing
            if (isset($nwMsg[1]) && is_numeric($nwMsg[1])) {
                $inMsg = $nwMsg[1] + 1;
                $code = $nwMsg[0].'_'.str_pad($inMsg, 4, '0', STR_PAD_LEFT);
            } else {
                $code = $prefix.'_0001'; // Fallback if reference is corrupted
            }
        } else {
            $code = $prefix.'_0001';
        }

        return $code;

    }

    // ------------- Show Form Edit Transfer-----------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Transfer::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Transfer_data = Transfer::with('details.product.unit')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        /**
         * Warehouses restriction
         * Allow if:
         * - user has access to all warehouses
         * - OR at least one of (from_warehouse_id OR to_warehouse_id)
         *   belongs to user assigned warehouses
         */
        $user_auth = auth()->user();

        if (! $user_auth->is_all_warehouses) {

            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                ->pluck('warehouse_id')
                ->toArray();

            $fromAllowed = !empty($Transfer_data->from_warehouse_id) 
                && in_array($Transfer_data->from_warehouse_id, $warehouses_id);

            $toAllowed = !empty($Transfer_data->to_warehouse_id) 
                && in_array($Transfer_data->to_warehouse_id, $warehouses_id);

            // Allow if at least one warehouse matches
            if (! $fromAllowed && ! $toAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to access this transfer (warehouse restriction).',
                ], 403);
            }
        }

        $details = [];
        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === Transfer->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Transfer_data);
        }

        if ($Transfer_data->from_warehouse_id) {
            if (Warehouse::where('id', $Transfer_data->from_warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $transfer['from_warehouse'] = $Transfer_data->from_warehouse_id;
            } else {
                $transfer['from_warehouse'] = '';
            }
        } else {
            $transfer['from_warehouse'] = '';
        }

        if ($Transfer_data->to_warehouse_id) {
            if (Warehouse::where('id', $Transfer_data->to_warehouse_id)->where('deleted_at', '=', null)->first()) {
                $transfer['to_warehouse'] = $Transfer_data->to_warehouse_id;
            } else {
                $transfer['to_warehouse'] = '';
            }
        } else {
            $transfer['to_warehouse'] = '';
        }

        $transfer['statut'] = $Transfer_data->statut;
        $transfer['notes'] = $Transfer_data->notes;
        $transfer['date'] = $Transfer_data->date;
        $transfer['tax_rate'] = $Transfer_data->tax_rate;
        $transfer['TaxNet'] = $Transfer_data->TaxNet;
        $transfer['discount'] = $Transfer_data->discount;
        $transfer['shipping'] = $Transfer_data->shipping;

        $batchesByDetail = app(BatchService::class)->batchesForTransferDetails($Transfer_data['details']);

        $detail_id = 0;
        foreach ($Transfer_data['details'] as $detail) {
            // -------check if detail has purchase_unit_id Or Null
            if ($detail->purchase_unit_id !== null) {
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
                $data['no_unit'] = 1;
            } else {
                $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $detail->product_id)
                    ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('warehouse_id', $Transfer_data->from_warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];
                $data['code'] = $productsVariants->code;

                $data['product_variant_id'] = $detail->product_variant_id;

                if ($unit && $unit->operator == '/') {
                    $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } elseif ($unit && $unit->operator == '*') {
                    $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['stock'] = 0;
                }
                $data['unitPurchase'] = $detail['product']['unitPurchase']->ShortName;

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)->where('warehouse_id', $Transfer_data->from_warehouse_id)
                    ->where('product_variant_id', '=', null)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } elseif ($unit && $unit->operator == '*') {
                    $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['stock'] = 0;
                }
            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;
            $data['quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['etat'] = 'current';
            $data['qte_copy'] = $detail->quantity;
            $data['unitPurchase'] = $unit->ShortName;
            $data['purchase_unit_id'] = $unit->id;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
            }
            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = $detail->cost;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {
                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = $tax_cost;
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet'] - $tax_cost);
                $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            }

            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            // The picker stores selected source batches under product_batch_id; we map
            // the saved pivot rows to that shape so the picker UI re-hydrates correctly.
            $existingBatches = $batchesByDetail[(int) $detail->id] ?? [];
            $data['batches'] = array_map(function ($b) {
                return [
                    'product_batch_id' => $b['source_batch_id'],
                    'batch_no' => $b['batch_no'],
                    'expiry_date' => $b['expiry_date'],
                    'qty_available' => 0, // hydrated by fetch_batches_for_detail on edit page
                    'qty' => $b['qty'],
                ];
            }, $existingBatches);

            $details[] = $data;
        }

        // get warehouses assigned to user
        $user_auth = auth()->user();

        if ($user_auth->is_all_warehouses) {

            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        } else {

            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                ->pluck('warehouse_id')
                ->toArray();

            $warehouses = Warehouse::where('deleted_at', '=', null)
                ->whereIn('id', $warehouses_id)
                ->get(['id', 'name']);

            // ✅ Append current transfer warehouses (from/to) if not assigned
            $appendIds = array_filter([
                $Transfer_data->from_warehouse_id ?? null
            ]);

            $missingIds = array_diff($appendIds, $warehouses_id);

            if (! empty($missingIds)) {
                $missingWarehouses = Warehouse::where('deleted_at', '=', null)
                    ->whereIn('id', $missingIds)
                    ->get(['id', 'name']);

                $warehouses = $warehouses->merge($missingWarehouses)->unique('id')->values();
            }
        }

        $to_warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'transfer' => $transfer,
            'warehouses' => $warehouses,
            'to_warehouses' => $to_warehouses,
        ]);
    }

    // ---------------- Get Details Transfer -----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Transfer::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Transfer_data = Transfer::with('details.product.unit')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = [];
        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === Transfer->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Transfer_data);
        }

        $transfer['date'] = $Transfer_data->date.' '.$Transfer_data->time;
        $transfer['note'] = $Transfer_data->notes;
        $transfer['Ref'] = $Transfer_data->Ref;
        $transfer['from_warehouse'] = $Transfer_data['from_warehouse']->name;
        $transfer['to_warehouse'] = $Transfer_data['to_warehouse']->name;
        $transfer['items'] = $Transfer_data->items;
        $transfer['statut'] = $Transfer_data->statut;
        $transfer['approval_status'] = $Transfer_data->approval_status;
        $transfer['GrandTotal'] = $Transfer_data->GrandTotal;

        $batchesByDetail = app(BatchService::class)->batchesForTransferDetails($Transfer_data['details']);

        foreach ($Transfer_data['details'] as $detail) {

            // -------check if detail has purchase_unit_id Or Null
            if ($detail->purchase_unit_id !== null) {
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            } else {
                $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $detail->product_id)
                    ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['quantity'] = $detail->quantity;
            $data['unit'] = $unit->ShortName;
            $data['total'] = $detail->total;
            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            $data['batches'] = $batchesByDetail[(int) $detail->id] ?? [];

            $details[] = $data;
        }

        return response()->json([
            'details' => $details,
            'transfer' => $transfer,
        ]);
    }

    // ---------------- Show Form Create Transfer ---------------\\

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Transfer::class);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $to_warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json(['warehouses' => $warehouses, 'to_warehouses' => $to_warehouses]);
    }

    // -------------- transfer_pdf -----------\\

    public function transfer_pdf(Request $request, $id)
    {
        $details = [];
        $helpers = new helpers;
        $transfer_data = Transfer::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $batchesByDetail = app(BatchService::class)->batchesForTransferDetails($transfer_data['details']);

        $transfer['from_warehouse'] = $transfer_data['from_warehouse']->name;
        $transfer['to_warehouse'] = $transfer_data['to_warehouse']->name;

        $transfer['statut'] = $transfer_data->statut;
        // Expose approval_status in case PDFs need it in the future.
        $transfer['approval_status'] = $transfer_data->approval_status;
        $transfer['Ref'] = $transfer_data->Ref;
        $transfer['date'] = $transfer_data->date.' '.$transfer_data->time;

        $detail_id = 0;
        foreach ($transfer_data['details'] as $detail) {

            // -------check if detail has purchase_unit_id Or Null
            if ($detail->purchase_unit_id !== null) {
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            } else {
                $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $detail->product_id)
                    ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['detail_id'] = $detail_id += 1;
            $data['quantity'] = number_format($detail->quantity, 2, '.', '');
            $data['unit_purchase'] = $unit->ShortName;
            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            $data['batches'] = $batchesByDetail[(int) $detail->id] ?? [];

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $Html = view('pdf.transfer_pdf', [
            'setting' => $settings,
            'transfer' => $transfer,
            'details' => $details,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);

        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('transfer.pdf');

    }

    // -------------- Approval Workflow (NEW) -----------\\

    /**
     * Approve a pending stock transfer and apply its stock movement once.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Transfer::class);

        \DB::transaction(function () use ($request, $id) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $transfer = Transfer::with('details')
                ->where('deleted_at', '=', null)
                ->findOrFail($id);

            if (! $view_records) {
                $this->authorizeForUser($request->user('api'), 'check_record', $transfer);
            }

            // OLD TRANSFERS SAFETY: never double-apply stock for already approved transfers.
            if ($transfer->isApproved()) {
                // No-op; return gracefully.
                return;
            }

            if ($transfer->approval_status === 'rejected') {
                abort(422, 'Rejected transfers cannot be approved without editing.');
            }

            // Apply initial stock movement based on the stored header + details.
            $this->applyInitialStockMovement($transfer);

            $transfer->approval_status = 'approved';
            $transfer->save();
        }, 10);

        return response()->json(['success' => true]);
    }

    /**
     * Mark a pending transfer as rejected – no stock movement is ever applied.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Transfer::class);

        \DB::transaction(function () use ($request, $id) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $transfer = Transfer::where('deleted_at', '=', null)->findOrFail($id);

            if (! $view_records) {
                $this->authorizeForUser($request->user('api'), 'check_record', $transfer);
            }

            // If it was already approved, we don't silently roll back stock here.
            if ($transfer->isApproved()) {
                abort(422, 'Approved transfers cannot be rejected via this action.');
            }

            $transfer->approval_status = 'rejected';
            $transfer->save();
        }, 10);

        return response()->json(['success' => true]);
    }

    /**
     * Apply stock movement for an unapproved transfer using its saved details.
     * This mirrors the existing logic in store(), but is executed only once,
     * at approval time, and only for transfers that weren't touching stock yet.
     */
    protected function applyInitialStockMovement(Transfer $transfer)
    {
        $details = TransferDetail::where('transfer_id', $transfer->id)->get();

        foreach ($details as $detail) {
            // Resolve unit exactly like other transfer routines do.
            if ($detail->purchase_unit_id !== null) {
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            } else {
                $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $detail->product_id)
                    ->first();
                $unit = $product_unit_purchase_id
                    ? Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first()
                    : null;
            }

            // No movement if unit is missing (keeps behaviour safe).
            if (! $unit) {
                continue;
            }

            // Mirror "completed" behaviour from store(): move stock from -> to.
            if ($transfer->statut == 'completed') {
                if ($detail->product_variant_id !== null) {
                    // FROM warehouse (variant)
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->first();

                    if ($product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }

                    // TO warehouse (variant)
                    $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->to_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->first();

                    if ($product_warehouse_to) {
                        if ($unit->operator == '/') {
                            $product_warehouse_to->qte += $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_to->qte += $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_to->save();
                    }
                } else {
                    // FROM warehouse (simple product)
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }

                    // TO warehouse (simple product)
                    $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->to_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($product_warehouse_to) {
                        if ($unit->operator == '/') {
                            $product_warehouse_to->qte += $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_to->qte += $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_to->save();
                    }
                }
            } elseif ($transfer->statut == 'sent') {
                // Mirror "sent" behaviour from store(): move stock out of FROM only.
                if ($detail->product_variant_id !== null) {
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->first();

                    if ($product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }
                } else {
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }
                }
            }
        }

        // Pharmacy: per-batch movements are NOT auto-FEFO'd at approval time. The
        // strict picker is the source of truth — for a pending transfer to update
        // the per-batch ledger on approval, the user must edit it after approval
        // and pick batches explicitly (the same flow they'd use to fix any line).
        // Warehouse stock above is still moved either way; only the per-batch
        // ledger is left untouched when no batches were saved.
    }
}
