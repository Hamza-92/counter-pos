<?php

namespace App\Http\Controllers;

use App\Mail\CustomEmail;
use App\Services\BatchService;
use App\Models\Account;
use App\Models\Client;
use App\Models\EmailMessage;
use App\Models\PaymentMethod;
use App\Models\PaymentSale;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalesAgent;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\sms_gateway;
use App\Models\SMSMessage;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Support\ZatcaQr;
use App\utils\helpers;
use ArPHP\I18N\Arabic;
use Carbon\Carbon;
use DB;
use GuzzleHttp\Client as Client_guzzle;
use GuzzleHttp\Client as Client_termi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use PDF;
use Twilio\Rest\Client as Client_Twilio;

class SalesController extends BaseController
{
    // ------------- GET ALL SALES -----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        $user = Auth::user();
        $is_all_warehouses = $user->is_all_warehouses;
        // If the user is restricted, fetch their assigned warehouse IDs once and reuse below.
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        // Filter fields With Params to retrieve
        $param = [
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => 'like',
            4 => '=',
            5 => '=',
            6 => 'like',
        ];
        $columns = [
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'shipping_status',
        ];
        $data = [];

        // Check If User Has Permission View  All Records
        $Sales = Sale::with('facture', 'client', 'warehouse', 'user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        if (! $is_all_warehouses) {
            $Sales->whereIn('warehouse_id', $warehouse_ids);
        }
        // Multiple Filter
        $Filtred = $helpers->filter($Sales, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
                        ->orWhere('shipping_status', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $Sales = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Sales as $Sale) {

            $item['id'] = $Sale['id'];
            $item['date'] = $Sale['date'].' '.$Sale['time'];
            $item['Ref'] = $Sale['Ref'];
            $item['created_by'] = $Sale['user']->username;
            $item['statut'] = $Sale['statut'];
            $item['shipping_status'] = $Sale['shipping_status'];
            $item['discount'] = $Sale['discount'];
            $item['shipping'] = $Sale['shipping'];
            $item['warehouse_name'] = $Sale['warehouse']['name'];
            $item['client_id'] = $Sale['client']['id'];
            $item['client_name'] = $Sale['client']['name'];
            $item['client_email'] = $Sale['client']['email'];
            $item['client_tele'] = $Sale['client']['phone'];
            $item['client_code'] = $Sale['client']['code'];
            $item['client_adr'] = $Sale['client']['adresse'];
            $item['GrandTotal'] = number_format($Sale['GrandTotal'], 2, '.', '');
            $item['paid_amount'] = number_format($Sale['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['GrandTotal'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Sale['payment_statut'];

            if (SaleReturn::where('sale_id', $Sale['id'])->where('deleted_at', '=', null)->exists()) {
                $sellReturn = SaleReturn::where('sale_id', $Sale['id'])->where('deleted_at', '=', null)->first();
                $item['salereturn_id'] = $sellReturn->id;
                $item['sale_has_return'] = 'yes';
            } else {
                $item['sale_has_return'] = 'no';
            }

            // Get documents count
            $item['documents_count'] = DB::table('sale_documents')
                ->where('sale_id', $Sale['id'])
                ->whereNull('deleted_at')
                ->count();

            $data[] = $item;
        }

        $stripe_key = config('app.STRIPE_KEY');
        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'stripe_key' => $stripe_key,
            'totalRows' => $totalRows,
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ------------- STORE NEW SALE-----------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        $sale = \DB::transaction(function () use ($request) {
            $helpers = new helpers;
            $order = new Sale;

            $order->is_pos = 0;
            $order->date = $request->date;
            $order->time = now()->toTimeString();
            $order->Ref = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->GrandTotal = $request->GrandTotal;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            // Ensure order-level discount method is saved: '1' for percentage, '2' for fixed
            $order->discount_Method = $request->has('discount_Method') ? (string) $request->discount_Method : '2';
            $order->shipping = $request->shipping;
            $order->statut = $request->statut;
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;
            $order->sales_agent_id = $request->sales_agent_id ?? null;
            $order->save();

            $data = $request['details'];
            $total_points_earned = 0;
            $inputDetailsForBatches = [];
            foreach ($data as $key => $value) {

                $product = Product::find($value['product_id']);
                $unit = Unit::where('id', $value['sale_unit_id'])->first();
                $total_points_earned += $value['quantity'] * $product->points;

                $inputDetailsForBatches[] = [
                    'product_id' => (int) $value['product_id'],
                    'batches' => isset($value['batches']) && is_array($value['batches']) ? $value['batches'] : [],
                ];

                $baseDate = $request->date ? \Carbon\Carbon::parse($request->date) : now();
                $warrantyGuarantee = SaleDetail::computeWarrantyGuaranteeDates($product, $baseDate);

                $orderDetails[] = array_merge([
                    'date' => $request->date,
                    'sale_id' => $order->id,
                    'sale_unit_id' => $value['sale_unit_id'] ? $value['sale_unit_id'] : null,
                    'quantity' => $value['quantity'],
                    'price' => $value['Unit_price'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'] ? $value['product_variant_id'] : null,
                    'total' => $value['subtotal'],
                    'imei_number' => $value['imei_number'],
                    'price_type' => isset($value['price_type']) ? $value['price_type'] : 'retail',
                ], $warrantyGuarantee);

                if ($order->statut == 'completed') {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }

                    } else {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }
                    }
                }
            }
            SaleDetail::insert($orderDetails);

            // Apply batch consumption for batch-tracked products (only when completed).
            $batchService = app(BatchService::class);
            if ($batchService->isSupported() && $order->statut === 'completed') {
                $persistedDetails = SaleDetail::where('sale_id', $order->id)
                    ->orderBy('id', 'asc')
                    ->get();
                $batchService->applyForSale($order, $inputDetailsForBatches, $persistedDetails);
            }

            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();

            if ($request->payment['status'] != 'pending') {
                $sale = Sale::findOrFail($order->id);
                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $sale);
                }

                try {

                    $total_paid = $sale->paid_amount + $request['amount'];
                    $due = $sale->GrandTotal - $total_paid;

                    if ($due === 0.0 || $due < 0.0) {
                        $payment_statut = 'paid';
                    } elseif ($due != $sale->GrandTotal) {
                        $payment_statut = 'partial';
                    } elseif ($due == $sale->GrandTotal) {
                        $payment_statut = 'unpaid';
                    }

                    if ($request['amount'] > 0 && $request->payment['status'] != 'pending') {
                        // All payment methods (including card) are now handled uniformly; no Stripe charge is performed here.
                        PaymentSale::create([
                            'sale_id' => $order->id,
                            'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                            'date' => Carbon::now(),
                            'account_id' => $request->payment['account_id'] ? $request->payment['account_id'] : null,
                            'payment_method_id' => $request->payment['payment_method_id'],
                            'montant' => $request['amount'],
                            'change' => $request['change'],
                            'notes' => null,
                            'user_id' => Auth::user()->id,
                        ]);

                        $account = Account::where('id', $request->payment['account_id'])->exists();

                        if ($account) {
                            // Account exists, perform the update
                            $account = Account::find($request->payment['account_id']);
                            $account->update([
                                'balance' => $account->balance + $request['amount'],
                            ]);
                        }

                        $sale->update([
                            'paid_amount' => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);
                    }
                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }

            }

            // 🪙 Points logic
            $client = Client::find($request->client_id);
            $used_points = $request->used_points ?? 0;
            $discount_from_points = $request->discount_from_points ?? 0;
            $earned_points = 0;

            if ($client && ($client->is_royalty_eligible == 1 || $client->is_royalty_eligible || $client->is_royalty_eligible === 1)) {

                // Deduct used points if valid
                if ($used_points > 0 && $client->points >= $used_points) {
                    $client->decrement('points', $used_points);
                }

                // Earn points
                $earned_points = $total_points_earned;

                $client->increment('points', $earned_points);

                $order_used_points = $used_points;
                $order_earned_points = $earned_points;
                $order_discount_from_points = $discount_from_points;
            } else {
                $order_used_points = 0;
                $order_earned_points = 0;
                $order_discount_from_points = 0;
            }

            $order->update([
                'used_points' => $order_used_points,
                'earned_points' => $order_earned_points,
                'discount_from_points' => $order_discount_from_points,
            ]);

            return $order;

        }, 10);

        // Calculate commissions when sale is completed
        if ($sale->statut === 'completed') {
            try {
                app(\App\Services\CommissionService::class)->calculateForSale($sale);
            } catch (\Throwable $e) {
                \Log::warning('Commission calculation failed (non-blocking): '.$e->getMessage(), ['sale_id' => $sale->id]);
            }
        }

        // (at the very end of your store() method, after the transaction)
        $qboSync = 'skipped';
        try {
            $realmGuess = $sale->quickbooks_realm_id ?: env('QUICKBOOKS_REALM_ID'); // may be null

            if (class_exists(\App\Jobs\SyncSaleToQuickBooks::class)) {
                \App\Jobs\SyncSaleToQuickBooks::dispatch($sale->id, $realmGuess)->afterCommit();
                $qboSync = 'queued';
            } else {
                $sale->load(['details.product', 'client']);
                /** @var \App\Services\QuickBooksService $qb */
                $qb = app(\App\Services\QuickBooksService::class);
                $res = $qb->createInvoice($sale, $realmGuess); // realmGuess can be null

                if ($res['ok'] ?? false) {
                    $sale->update([
                        'quickbooks_invoice_id' => $res['id'],
                        'quickbooks_realm_id' => $res['realm'] ?? ($realmGuess ?: null),
                        'quickbooks_synced_at' => now(),
                        'quickbooks_sync_error' => null,
                    ]);
                    $qboSync = 'ok';
                } else {
                    $sale->update([
                        'quickbooks_realm_id' => $realmGuess ?: $sale->quickbooks_realm_id,
                        'quickbooks_sync_error' => ($res['error'] ?? 'Unknown error')
                            .(isset($res['http']) ? " (HTTP {$res['http']})" : '')
                            .(isset($res['body']) ? " :: {$res['body']}" : ''),
                    ]);
                    $qboSync = 'failed';
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('QuickBooks sync failed (non-blocking): '.$e->getMessage(), [
                'sale_id' => $sale->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $sale->update(['quickbooks_sync_error' => $e->getMessage()]);
            $qboSync = 'failed';
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'quickbooks_invoice_id' => $sale->quickbooks_invoice_id,
            'qbo_sync' => $qboSync,
        ]);
    }

    // ------------- UPDATE SALE -----------

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Sale::class);

        request()->validate([
            'warehouse_id' => 'required',
            'client_id' => 'required',
        ]);

        $sale = \DB::transaction(function () use ($request, $id) {

            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $current_Sale = Sale::findOrFail($id);

             /**
             * Warehouses restriction
             * Allow if:
             * - user has access to all warehouses (is_all_warehouses = 1)
             * - OR sale warehouse_id is in user's assigned warehouses
            */
            $user_auth = auth()->user();

            if (! $user_auth->is_all_warehouses) {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                    ->pluck('warehouse_id')
                    ->toArray();

                if (empty($current_Sale->warehouse_id) || ! in_array($current_Sale->warehouse_id, $warehouses_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not allowed to access this sale (warehouse restriction).',
                    ], 403);
                }
            }

            if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false, 'Return exist for the Transaction' => false], 403);
            } else {
                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === Sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Sale);
                }
                $old_sale_details = SaleDetail::where('sale_id', $id)->get();
                $new_sale_details = $request['details'];
                $length = count($new_sale_details);

                // Get Ids for new Details
                $new_products_id = [];
                foreach ($new_sale_details as $new_detail) {
                    $new_products_id[] = $new_detail['id'];
                }

                // Reverse batch consumption for the old (completed) state of the sale BEFORE
                // deleting any detail rows or restoring product_warehouse stock. This releases
                // ProductBatch.qty and clears sale_detail_batches pivots so the new state can
                // be reapplied cleanly below.
                $batchService = app(BatchService::class);
                if ($batchService->isSupported() && $current_Sale->statut == 'completed') {
                    $batchService->reverseForSaleDetails($old_sale_details);
                }

                // Init Data with old Parametre
                $old_products_id = [];
                foreach ($old_sale_details as $key => $value) {
                    $old_products_id[] = $value->id;

                    // check if detail has sale_unit_id Or Null
                    if ($value['sale_unit_id'] !== null) {
                        $old_unit = Unit::where('id', $value['sale_unit_id'])->first();
                    } else {
                        $product_unit_sale_id = Product::with('unitSale')
                            ->where('id', $value['product_id'])
                            ->first();

                        if ($product_unit_sale_id['unitSale']) {
                            $old_unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                        }
                        $old_unit = null;

                    }

                    if ($current_Sale->statut == 'completed') {

                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }
                    // Delete Detail
                    if (! in_array($old_products_id[$key], $new_products_id)) {
                        $SaleDetail = SaleDetail::findOrFail($value->id);
                        $SaleDetail->delete();
                    }
                }

                // Update Data with New request
                $total_points_earned = 0;
                $newPersistedDetails = [];
                foreach ($new_sale_details as $prd => $prod_detail) {

                    $product = Product::find($prod_detail['product_id']);
                    $total_points_earned += $prod_detail['quantity'] * $product->points;

                    $get_type_product = Product::where('id', $prod_detail['product_id'])->first()->type;

                    if ($prod_detail['sale_unit_id'] !== null || $get_type_product == 'is_service') {
                        $unit_prod = Unit::where('id', $prod_detail['sale_unit_id'])->first();

                        if ($request['statut'] == 'completed') {

                            if ($prod_detail['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->where('product_variant_id', $prod_detail['product_variant_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }

                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }

                        }

                        $orderDetails['sale_id'] = $id;
                        $orderDetails['date'] = $request['date'];
                        $orderDetails['price'] = $prod_detail['Unit_price'];
                        $orderDetails['sale_unit_id'] = $prod_detail['sale_unit_id'];
                        $orderDetails['TaxNet'] = $prod_detail['tax_percent'];
                        $orderDetails['tax_method'] = $prod_detail['tax_method'];
                        $orderDetails['discount'] = $prod_detail['discount'];
                        $orderDetails['discount_method'] = $prod_detail['discount_Method'];
                        $orderDetails['quantity'] = $prod_detail['quantity'];
                        $orderDetails['product_id'] = $prod_detail['product_id'];
                        $orderDetails['product_variant_id'] = $prod_detail['product_variant_id'];
                        $orderDetails['total'] = $prod_detail['subtotal'];
                        $orderDetails['imei_number'] = $prod_detail['imei_number'];

                        $baseDate = ! empty($request['date']) ? \Carbon\Carbon::parse($request['date']) : now();
                        $wg = SaleDetail::computeWarrantyGuaranteeDates($product, $baseDate);
                        $orderDetails['warranty_date'] = $wg['warranty_date'];
                        $orderDetails['guarantee_date'] = $wg['guarantee_date'];

                        if (! in_array($prod_detail['id'], $old_products_id)) {
                            $orderDetails['date'] = $request['date'];
                            $orderDetails['sale_unit_id'] = $unit_prod ? $unit_prod->id : null;
                            $persistedDetail = SaleDetail::Create($orderDetails);
                        } else {
                            SaleDetail::where('id', $prod_detail['id'])->update($orderDetails);
                            $persistedDetail = SaleDetail::find($prod_detail['id']);
                        }
                        $newPersistedDetails[$prd] = $persistedDetail;
                    } else {
                        $newPersistedDetails[$prd] = null;
                    }
                }

                // Apply batch consumption for the new (completed) state. If the request
                // carries explicit batch picks per line, those are consumed; otherwise
                // the line qty is auto-filled via FEFO. The local sale's warehouse_id is
                // temporarily updated so the service looks up batches in the right warehouse
                // before the main update() below persists it.
                if ($batchService->isSupported() && $request['statut'] == 'completed') {
                    $current_Sale->warehouse_id = (int) $request->warehouse_id;
                    $batchService->applyForSaleWithAutoFallback(
                        $current_Sale,
                        $new_sale_details,
                        $newPersistedDetails
                    );
                }

                // -------------------- Loyalty points update (edit) --------------------
                // 1) Roll back old points from the original client (if any)
                // 2) Apply new points to the (possibly new) client based on the request

                $oldClient = Client::find($current_Sale->client_id);
                $newClient = Client::find($request->client_id);

                $previous_used   = (int) ($current_Sale->used_points ?? 0);
                $previous_earned = (int) ($current_Sale->earned_points ?? 0);

                // Use the same convention as store(): trust used_points coming from frontend
                $discount_from_points = (float) ($request->discount_from_points ?? 0);
                $new_used   = (float) ($request->used_points ?? 0);
                $new_earned = (float) $total_points_earned;

                // Run loyalty logic if:
                // - the sale previously had loyalty applied, OR
                // - the client is eligible
                $salePreviouslyHadLoyalty =
                    ((int) ($current_Sale->used_points ?? 0) > 0)
                    || ((int) ($current_Sale->earned_points ?? 0) > 0)
                    || ((float) ($current_Sale->discount_from_points ?? 0) > 0);

                $clientEligible = $newClient
                    && ($newClient->is_royalty_eligible == 1 || $newClient->is_royalty_eligible || $newClient->is_royalty_eligible === 1);

                // If the sale previously had loyalty, treat this client "as eligible"
                // for edit recalculation even if the eligibility flag is currently off.
                $eligibleForLoyaltyRecalc = $clientEligible || $salePreviouslyHadLoyalty;

                if ($salePreviouslyHadLoyalty || $clientEligible) {

                    // If client stays the same (your case in edit UI), update points by DELTA.
                    // This avoids "rollback earned points" making the balance negative and skipping deductions.
                    if ($oldClient && $newClient && (int) $oldClient->id === (int) $newClient->id) {

                        $client = $newClient;
                        $eligible = $eligibleForLoyaltyRecalc;

                        // Target values for this save
                        $target_used = $eligible ? max(0, (float) $new_used) : 0.0;
                        $target_earned = $eligible ? max(0, (float) $new_earned) : 0.0;

                        // Apply USED points delta
                        $delta_used = $target_used - (float) $previous_used;
                        if ($delta_used > 0) {
                            $available = max(0.0, (float) $client->points);
                            $to_dec = min($delta_used, $available);
                            if ($to_dec > 0) {
                                $client->decrement('points', $to_dec);
                            }
                            // Persist the effective used points that were actually deducted
                            $target_used = (float) $previous_used + (float) $to_dec;
                        } elseif ($delta_used < 0) {
                            $client->increment('points', abs($delta_used));
                        }

                        // Apply EARNED points delta
                        $delta_earned = $target_earned - (float) $previous_earned;
                        if ($delta_earned > 0) {
                            $client->increment('points', $delta_earned);
                        } elseif ($delta_earned < 0) {
                            $available = max(0.0, (float) $client->points);
                            $to_dec = min(abs($delta_earned), $available);
                            if ($to_dec > 0) {
                                $client->decrement('points', $to_dec);
                            }
                        }

                        // Save back onto sale
                        $new_used = $target_used;
                        $new_earned = $target_earned;
                    } else {
                        // Fallback: client changed (shouldn't happen in your UI, but keep it safe)
                        if ($oldClient && ($previous_used > 0 || $previous_earned > 0)) {
                            if ($previous_used > 0) {
                                $oldClient->increment('points', $previous_used);
                            }
                            if ($previous_earned > 0) {
                                $oldClient->decrement('points', $previous_earned);
                            }
                        }

                        if ($eligibleForLoyaltyRecalc) {
                            if ($new_used > 0) {
                                $available = max(0.0, (float) $newClient->points);
                                $to_use = min((float) $new_used, $available);
                                if ($to_use > 0) {
                                    $newClient->decrement('points', $to_use);
                                    $new_used = $to_use;
                                } else {
                                    $new_used = 0;
                                }
                            }
                            if ($new_earned > 0) {
                                $newClient->increment('points', $new_earned);
                            }
                        } else {
                            // Not eligible: ensure sale is cleared and any previous loyalty is rolled back above
                            $new_used = 0;
                            $new_earned = 0;
                        }
                    }
                }


                $due = $request['GrandTotal'] - $current_Sale->paid_amount;
                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } elseif ($due != $request['GrandTotal']) {
                    $payment_statut = 'partial';
                } elseif ($due == $request['GrandTotal']) {
                    $payment_statut = 'unpaid';
                }

                $current_Sale->update([
                    'date' => $request['date'],
                    'client_id' => $request['client_id'],
                    'warehouse_id' => $request['warehouse_id'],
                    'notes' => $request['notes'],
                    'statut' => $request['statut'],
                    'sales_agent_id' => $request->filled('sales_agent_id') ? $request->sales_agent_id : $current_Sale->sales_agent_id,
                    'tax_rate' => $request['tax_rate'],
                    'TaxNet' => $request['TaxNet'],
                    'discount' => $request['discount'],
                    // Ensure order-level discount method stays in sync when editing
                    'discount_Method' => $request->has('discount_Method')
                        ? (string) $request->discount_Method
                        : ($current_Sale->discount_Method ?? '2'),
                    'shipping' => $request['shipping'],
                    'GrandTotal' => $request['GrandTotal'],
                    'payment_statut' => $payment_statut,
                    'used_points' => $new_used,
                    'earned_points' => $new_earned,
                    'discount_from_points' => $request['discount_from_points'],
                ]);
            }

            return $current_Sale;

        }, 10);

        // Calculate commissions when sale is completed (idempotent)
        if ($sale->statut === 'completed') {
            try {
                app(\App\Services\CommissionService::class)->calculateForSale($sale->fresh());
            } catch (\Throwable $e) {
                \Log::warning('Commission calculation failed (non-blocking): '.$e->getMessage(), ['sale_id' => $sale->id]);
            }
        }

        // ---------- AFTER COMMIT: QBO sync (update-only) ----------
        $qboSync = 'skipped';
        try {
            /** @var \App\Services\QuickBooksService $qb */
            $qb = app(\App\Services\QuickBooksService::class);
            $res = $qb->updateInvoice($sale);

            if (! ($res['ok'] ?? false)) {
                \Log::warning('QBO update failed (no create fallback)', [
                    'sale_id' => $sale->id,
                    'error' => $res['error'] ?? 'unknown',
                    'http' => $res['http'] ?? null,
                    'body' => $res['body'] ?? null,
                ]);
                $sale->update([
                    'quickbooks_sync_error' => substr(($res['error'] ?? '').' '.($res['body'] ?? ''), 0, 65000),
                ]);
                $qboSync = 'failed';
            } else {
                // ✅ never overwrite an existing id here
                $updates = [
                    'quickbooks_realm_id' => $sale->quickbooks_realm_id ?: ($res['realm'] ?? null),
                    'quickbooks_synced_at' => now(),
                    'quickbooks_sync_error' => null,
                ];
                if (empty($sale->quickbooks_invoice_id) && ! empty($res['id'])) {
                    $updates['quickbooks_invoice_id'] = (string) $res['id'];
                }
                $sale->update($updates);
                $qboSync = 'ok';
            }
        } catch (\Throwable $e) {
            \Log::warning('QBO sync (update) failed non-blocking: '.$e->getMessage(), [
                'sale_id' => $sale->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $qboSync = 'failed';
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'qbo_sync' => $qboSync,
        ]);
    }

    // ------------- Remove SALE BY ID -----------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', \App\Models\Sale::class);

        // We’ll capture QBO identifiers before deletion so we can sync after commit
        $qboInfo = [
            'id' => null,
            'ref' => null,
            'realm' => null,
        ];

        \DB::transaction(function () use ($id, $request, &$qboInfo) {
            $user = \Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $current = \App\Models\Sale::with(['details.product'])->findOrFail($id);

            /**
             * Warehouses restriction
             * Allow if:
             * - user has access to all warehouses (is_all_warehouses = 1)
             * - OR sale warehouse_id is in user's assigned warehouses
            */
            $user_auth = auth()->user();

            if (! $user_auth->is_all_warehouses) {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                    ->pluck('warehouse_id')
                    ->toArray();

                if (empty($current->warehouse_id) || ! in_array($current->warehouse_id, $warehouses_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not allowed to access this sale (warehouse restriction).',
                    ], 403);
                }
            }

            // capture identifiers for QBO delete after commit
            $qboInfo['id'] = $current->quickbooks_invoice_id;
            $qboInfo['ref'] = $current->Ref;
            $qboInfo['realm'] = $current->quickbooks_realm_id;

            if (\App\Models\SaleReturn::where('sale_id', $id)->whereNull('deleted_at')->exists()) {
                abort(403, 'Return exists for this sale; cannot delete.');
            }

            if (! $view_records) {
                $this->authorizeForUser($request->user('api'), 'check_record', $current);
            }

            // Restore stock if sale was completed
            if ($current->statut === 'completed') {
                foreach ($current->details as $d) {
                    $product = $d->product;
                    if (! $product || ($product->type ?? null) === 'is_service') {
                        continue;
                    }

                    // find unit used on the line (or fallback to product sale unit)
                    $unit = $d->sale_unit_id ? \App\Models\Unit::find($d->sale_unit_id)
                                            : optional($product->unitSale);

                    $qty = (float) $d->quantity;
                    $opv = max((float) ($unit->operator_value ?? 1), 1);
                    $addQ = ($unit && $unit->operator === '/') ? ($qty / $opv) : ($qty * $opv);

                    $pw = \App\Models\product_warehouse::whereNull('deleted_at')
                        ->where('warehouse_id', $current->warehouse_id)
                        ->where('product_id', $d->product_id)
                        ->when($d->product_variant_id, fn ($q) => $q->where('product_variant_id', $d->product_variant_id))
                        ->first();

                    if ($pw && $unit) {
                        $pw->qte = max(0, $pw->qte + $addQ);
                        $pw->save();
                    }
                }
            }

            // Reverse client points (refund used points, remove earned points)
            $client = Client::find($current->client_id);
            if ($client) {
                $used_points = (int) ($current->used_points ?? 0);
                $earned_points = (int) ($current->earned_points ?? 0);

                if ($used_points > 0) {
                    // Restore previously used points back to client
                    $client->increment('points', $used_points);
                }

                if ($earned_points > 0) {
                    // Remove previously earned points; avoid negative balance
                    $new_balance = max(0, (int) $client->points - $earned_points);
                    $client->update(['points' => $new_balance]);
                }
            }

            // Delete shipment if exists
            if ($ship = \App\Models\Shipment::where('sale_id', $id)->first()) {
                $ship->delete();
            }

            // Reverse batch consumption (adds qty back to product_batches, removes pivot rows).
            $batchService = app(BatchService::class);
            if ($batchService->isSupported() && $current->statut === 'completed') {
                $batchService->reverseForSaleDetails($current->details);
            }

            // Delete details
            $current->details()->delete();

            // Reverse payments locally (and adjust account balances)
            $payments = \App\Models\PaymentSale::where('sale_id', $id)->get();
            foreach ($payments as $p) {
                if ($p->Reglement === 'credit card') {
                    if ($card = \App\Models\PaymentWithCreditCard::where('payment_id', $p->id)->first()) {
                        $card->delete();
                    }
                }
                if ($p->account_id && ($acct = \App\Models\Account::find($p->account_id))) {
                    $acct->update(['balance' => $acct->balance - (float) $p->montant]);
                }
                $p->delete();
            }

            // Soft-delete the sale
            $current->update([
                'deleted_at' => \Carbon\Carbon::now(),
                'shipping_status' => null,
            ]);
        }, 10);

        // ---------- AFTER COMMIT: QBO delete (best effort; non-blocking) ----------
        $qboSync = 'skipped';
        try {
            // Build a minimal sale stub for the service (no DB reads required)
            $saleStub = new \App\Models\Sale;
            $saleStub->id = $id;
            $saleStub->quickbooks_invoice_id = $qboInfo['id'];
            $saleStub->Ref = $qboInfo['ref'];
            $saleStub->quickbooks_realm_id = $qboInfo['realm'];

            /** @var \App\Services\QuickBooksService $qb */
            $qb = app(\App\Services\QuickBooksService::class);
            $res = $qb->deleteInvoice($saleStub);

            if (! ($res['ok'] ?? false)) {
                \Log::warning('QBO delete failed (non-blocking)', [
                    'sale_id' => $id,
                    'error' => $res['error'] ?? 'unknown',
                    'http' => $res['http'] ?? null,
                    'body' => $res['body'] ?? null,
                ]);

                // Save error on the soft-deleted row (use withTrashed if model has SoftDeletes)
                $deletedSale = \App\Models\Sale::withTrashed()->find($id) ?: \App\Models\Sale::query()->find($id);
                if ($deletedSale) {
                    $deletedSale->update([
                        'quickbooks_sync_error' => substr(($res['error'] ?? '').' '.($res['body'] ?? ''), 0, 65000),
                    ]);
                }
                $qboSync = 'failed';
            } else {
                // Optionally mark as deleted in QBO
                $deletedSale = \App\Models\Sale::withTrashed()->find($id) ?: \App\Models\Sale::query()->find($id);
                if ($deletedSale) {
                    $deletedSale->update([
                        'quickbooks_sync_error' => null,
                    ]);
                }
                $qboSync = 'ok';
            }
        } catch (\Throwable $e) {
            \Log::warning('QBO delete failed (non-blocking): '.$e->getMessage(), [
                'sale_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            $qboSync = 'failed';
        }

        return response()->json(['success' => true, 'qbo_sync' => $qboSync]);
    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Sale::class);

        \DB::transaction(function () use ($request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $sale_id) {

                if (SaleReturn::where('sale_id', $sale_id)->where('deleted_at', '=', null)->exists()) {
                    return response()->json(['success' => false, 'Return exist for the Transaction' => false], 403);
                } else {
                    $current_Sale = Sale::findOrFail($sale_id);

                     /**
                     * Warehouses restriction
                     * Allow if:
                     * - user has access to all warehouses (is_all_warehouses = 1)
                     * - OR sale warehouse_id is in user's assigned warehouses
                    */
                    $user_auth = auth()->user();

                    if (! $user_auth->is_all_warehouses) {
                        $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                            ->pluck('warehouse_id')
                            ->toArray();

                        if (empty($current_Sale->warehouse_id) || ! in_array($current_Sale->warehouse_id, $warehouses_id)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'You are not allowed to access this sale (warehouse restriction).',
                            ], 403);
                        }
                    }

                    $old_sale_details = SaleDetail::where('sale_id', $sale_id)->get();
                    $shipment_data = Shipment::where('sale_id', $sale_id)->first();

                    // Check If User Has Permission view All Records
                    if (! $view_records) {
                        // Check If User->id === current_Sale->id
                        $this->authorizeForUser($request->user('api'), 'check_record', $current_Sale);
                    }

                    // Pharmacy: reverse batch consumption (adds qty back to product_batches,
                    // removes pivot rows) before warehouse stock is restored below.
                    $batchService = app(BatchService::class);
                    if ($batchService->isSupported() && $current_Sale->statut === 'completed') {
                        $batchService->reverseForSaleDetails($old_sale_details);
                    }

                    foreach ($old_sale_details as $key => $value) {

                        // check if detail has sale_unit_id Or Null
                        if ($value['sale_unit_id'] !== null) {
                            $old_unit = Unit::where('id', $value['sale_unit_id'])->first();
                        } else {
                            $product_unit_sale_id = Product::with('unitSale')
                                ->where('id', $value['product_id'])
                                ->first();
                            if ($product_unit_sale_id['unitSale']) {
                                $old_unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                            }
                            $old_unit = null;

                        }

                        if ($current_Sale->statut == 'completed') {

                            if ($value['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Sale->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->where('product_variant_id', $value['product_variant_id'])
                                    ->first();

                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }

                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Sale->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->first();
                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }
                        }

                    }

                    // Reverse client points for this sale (refund used, remove earned)
                    $client = Client::find($current_Sale->client_id);
                    if ($client) {
                        $used_points = (int) ($current_Sale->used_points ?? 0);
                        $earned_points = (int) ($current_Sale->earned_points ?? 0);

                        if ($used_points > 0) {
                            $client->increment('points', $used_points);
                        }

                        if ($earned_points > 0) {
                            $new_balance = max(0, (int) $client->points - $earned_points);
                            $client->update(['points' => $new_balance]);
                        }
                    }

                    if ($shipment_data) {
                        $shipment_data->delete();
                    }

                    $current_Sale->details()->delete();
                    $current_Sale->update([
                        'deleted_at' => Carbon::now(),
                        'shipping_status' => null,
                    ]);

                    $Payment_Sale_data = PaymentSale::where('sale_id', $sale_id)->get();
                    foreach ($Payment_Sale_data as $Payment_Sale) {
                        if ($Payment_Sale->Reglement == 'credit card') {
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $Payment_Sale->id)->first();
                            if ($PaymentWithCreditCard) {
                                $PaymentWithCreditCard->delete();
                            }
                        }

                        $account = Account::find($Payment_Sale->account_id);

                        if ($account) {
                            $account->update([
                                'balance' => $account->balance - $Payment_Sale->montant,
                            ]);
                        }

                        $Payment_Sale->delete();
                    }
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ---------------- Get Details Sale-----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $sale_data = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = [];

        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === sale->id
            $this->authorizeForUser($request->user('api'), 'check_record', $sale_data);
        }

        $sale_details['Ref'] = $sale_data->Ref;
        $sale_details['date'] = $sale_data->date.' '.$sale_data->time;
        $sale_details['note'] = $sale_data->notes;
        $sale_details['statut'] = $sale_data->statut;
        $sale_details['warehouse'] = $sale_data['warehouse']->name;
        $sale_details['discount'] = $sale_data->discount;
        // Include order-level discount method and discount from points for detail_sale.vue
        $sale_details['discount_Method'] = $sale_data->discount_Method ?? '2'; // '1' = percent, '2' = fixed
        $sale_details['shipping'] = $sale_data->shipping;
        $sale_details['tax_rate'] = $sale_data->tax_rate;
        $sale_details['TaxNet'] = $sale_data->TaxNet;
        $sale_details['client_name'] = $sale_data['client']->name;
        $sale_details['client_phone'] = $sale_data['client']->phone;
        $sale_details['client_adr'] = $sale_data['client']->adresse;
        $sale_details['client_email'] = $sale_data['client']->email;
        $sale_details['client_tax'] = $sale_data['client']->tax_number;
        $sale_details['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale_details['paid_amount'] = number_format($sale_data->paid_amount, 2, '.', '');
        $sale_details['due'] = number_format($sale_details['GrandTotal'] - $sale_details['paid_amount'], 2, '.', '');
        $sale_details['payment_status'] = $sale_data->payment_statut;
        $sale_details['discount_from_points'] = $sale_data->discount_from_points ?? 0;

        if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
            $sellReturn = SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->first();
            $sale_details['salereturn_id'] = $sellReturn->id;
            $sale_details['sale_has_return'] = 'yes';
        } else {
            $sale_details['sale_has_return'] = 'no';
        }

        // Preload existing batch pivots for this sale's details (pharmacy/batch module).
        $saleBatchesByDetail = app(\App\Services\BatchService::class)
            ->batchesForSaleDetails($sale_data['details']);

        foreach ($sale_data['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            } else {
                $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();

                if ($product_unit_sale_id['unitSale']) {
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }
                $unit = null;

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
            $data['total'] = $detail->total;
            $data['price'] = $detail->price;
            $data['unit_sale'] = $unit ? $unit->ShortName : '';

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;
            $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
            $data['batches'] = $saleBatchesByDetail[(int) $detail->id] ?? [];

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'sale' => $sale_details,
            'company' => $company,
        ]);

    }

    // -------------- Print Invoice ---------------\\

    public function Print_Invoice_POS(Request $request, $id)
    {
        $helpers = new helpers;
        $details = [];

        $sale = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $item['id'] = $sale->id;
        $item['Ref'] = $sale->Ref;
        $item['date'] = $sale->date.' '.$sale->time;
        $item['discount'] = number_format($sale->discount, 2, '.', '');
        $item['discount_Method'] = $sale->discount_Method ?? '2'; // '1' for percentage, '2' for fixed
        $item['discount_from_points'] = number_format($sale->discount_from_points ?? 0, 2, '.', ''); // Include points discount for receipt display
        $item['shipping'] = number_format($sale->shipping, 2, '.', '');
        $item['taxe'] = number_format($sale->TaxNet, 2, '.', '');
        $item['tax_rate'] = $sale->tax_rate;
        $item['client_name'] = $sale['client']->name;
        $item['warehouse_name'] = $sale['warehouse']->name;
        $item['seller_name'] = $sale['user']->username;
        $item['GrandTotal'] = number_format($sale->GrandTotal, 2, '.', '');
        $item['paid_amount'] = number_format($sale->paid_amount, 2, '.', '');
        $item['notes'] = $sale->notes;

        foreach ($sale['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            } else {
                $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();
                if ($product_unit_sale_id['unitSale']) {
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }
                $unit = null;

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

            $data['quantity'] = number_format($detail->quantity, 2, '.', '');
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unit_sale'] = $unit ? $unit->ShortName : '';

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $payments = PaymentSale::with('sale', 'payment_method')
            ->where('sale_id', $id)
            ->orderBy('id', 'DESC')
            ->get();

        $settings = Setting::where('deleted_at', '=', null)->first();
        $pos_settings = PosSetting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        // Build ZATCA QR payload if enabled and VAT number is set
        $zatcaQr = null;
        if ($settings && (bool) $settings->zatca_enabled && ! empty($settings->vat_number)) {
            $sellerName = $settings->company_name_ar ?: $settings->CompanyName;
            $timestampIso = ZatcaQr::toIso8601($item['date'], config('app.timezone'));
            $totalWithVat = $item['GrandTotal'];
            $vatAmount = $item['taxe'];
            $zatcaQr = ZatcaQr::generate($sellerName, (string) $settings->vat_number, $timestampIso, (string) $totalWithVat, (string) $vatAmount);
        }

        // Build signed public invoice URL for barcode scanning
        $invoiceSignature = substr(hash_hmac('sha256', (string) $sale->id, config('app.key')), 0, 32);
        $publicInvoiceUrl = url('/invoice/' . $sale->id . '/' . $invoiceSignature);

        return response()->json([
            'symbol' => $symbol,
            'payments' => $payments,
            'setting' => $settings,
            'pos_settings' => $pos_settings,
            'sale' => $item,
            'details' => $details,
            'zatca_qr' => $zatcaQr,
            'public_invoice_url' => $publicInvoiceUrl,
        ]);

    }

    // ------------- DIRECT NETWORK PRINT (RAW / port 9100) -----------\\
    // Sends a plain ESC/POS receipt for a sale directly to a configured
    // network printer. Purely additive: existing client-side print flow
    // (window.print / PDF popup) is untouched and remains the default.
    public function Direct_Network_Print_POS(Request $request, $id)
    {
        $pos_settings = PosSetting::where('deleted_at', '=', null)->first();

        if (!$pos_settings || !$pos_settings->direct_network_printing) {
            return response()->json([
                'success' => false,
                'message' => 'Direct Network Printing is disabled.',
            ], 422);
        }

        $ip = trim((string) ($pos_settings->network_printer_ip ?? ''));
        $port = (int) ($pos_settings->network_printer_port ?? 9100);

        if ($ip === '' || $port < 1 || $port > 65535) {
            return response()->json([
                'success' => false,
                'message' => 'Network printer IP or port is not configured.',
            ], 422);
        }

        $sale = Sale::with('details.product.unitSale', 'client', 'warehouse', 'user')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $settings = Setting::where('deleted_at', '=', null)->first();
        $helpers = new helpers;
        $symbol = $helpers->Get_Currency_Code();

        $ESC = chr(27);
        $GS  = chr(29);
        $LF  = chr(10);
        $initPrinter = $ESC . '@';
        $alignCenter = $ESC . 'a' . chr(1);
        $alignLeft   = $ESC . 'a' . chr(0);
        $boldOn      = $ESC . 'E' . chr(1);
        $boldOff     = $ESC . 'E' . chr(0);
        $cut         = $GS . 'V' . chr(0);

        $width = 42; // Default column width for 80mm thermal. 32 is typical for 58mm.
        if ($pos_settings && (int) $pos_settings->receipt_paper_size === 58) {
            $width = 32;
        }

        $line = str_repeat('-', $width) . $LF;
        $pad  = function ($left, $right) use ($width) {
            $left = (string) $left;
            $right = (string) $right;
            $space = $width - mb_strlen($left) - mb_strlen($right);
            if ($space < 1) {
                return $left . ' ' . $right . chr(10);
            }
            return $left . str_repeat(' ', $space) . $right . chr(10);
        };

        $out  = $initPrinter;
        $out .= $alignCenter . $boldOn;
        if ($settings && !empty($settings->CompanyName)) {
            $out .= $settings->CompanyName . $LF;
        }
        $out .= $boldOff;
        if ($settings && !empty($settings->CompanyAdress)) {
            $out .= $settings->CompanyAdress . $LF;
        }
        if ($settings && !empty($settings->CompanyPhone)) {
            $out .= $settings->CompanyPhone . $LF;
        }

        $out .= $alignLeft . $line;
        $out .= $pad('Ref:', (string) $sale->Ref);
        $out .= $pad('Date:', (string) ($sale->date . ' ' . $sale->time));
        $out .= $pad('Client:', (string) (optional($sale->client)->name ?? ''));
        $out .= $pad('Cashier:', (string) (optional($sale->user)->username ?? ''));
        $out .= $line;

        foreach ($sale->details as $detail) {
            $name = '';
            if ($detail->product_variant_id) {
                $pv = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();
                $name = ($pv ? ('[' . $pv->name . ']') : '') . ($detail->product->name ?? '');
            } else {
                $name = $detail->product->name ?? '';
            }
            $qty = rtrim(rtrim(number_format((float) $detail->quantity, 2, '.', ''), '0'), '.');
            $total = number_format((float) $detail->total, 2, '.', '');
            $out .= mb_substr($name, 0, $width) . $LF;
            $out .= $pad('  ' . $qty . ' x', $symbol . ' ' . $total);
        }

        $out .= $line;
        $out .= $pad('Subtotal:', $symbol . ' ' . number_format((float) ($sale->GrandTotal - $sale->TaxNet + $sale->discount - $sale->shipping), 2, '.', ''));
        if ((float) $sale->discount > 0) {
            $out .= $pad('Discount:', '-' . $symbol . ' ' . number_format((float) $sale->discount, 2, '.', ''));
        }
        if ((float) $sale->TaxNet > 0) {
            $out .= $pad('Tax:', $symbol . ' ' . number_format((float) $sale->TaxNet, 2, '.', ''));
        }
        if ((float) $sale->shipping > 0) {
            $out .= $pad('Shipping:', $symbol . ' ' . number_format((float) $sale->shipping, 2, '.', ''));
        }
        $out .= $boldOn . $pad('TOTAL:', $symbol . ' ' . number_format((float) $sale->GrandTotal, 2, '.', '')) . $boldOff;
        $out .= $pad('Paid:', $symbol . ' ' . number_format((float) $sale->paid_amount, 2, '.', ''));
        $due = (float) $sale->GrandTotal - (float) $sale->paid_amount;
        if ($due > 0) {
            $out .= $pad('Due:', $symbol . ' ' . number_format($due, 2, '.', ''));
        }

        $out .= $LF . $alignCenter . 'Thank you!' . $LF;
        $out .= str_repeat($LF, 4);
        $out .= $cut;

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        if (!$fp) {
            return response()->json([
                'success' => false,
                'message' => 'Could not reach printer at ' . $ip . ':' . $port . ' (' . $errstr . ')',
            ], 502);
        }

        @stream_set_timeout($fp, 5);
        $written = @fwrite($fp, $out);
        @fflush($fp);
        @fclose($fp);

        if ($written === false) {
            return response()->json([
                'success' => false,
                'message' => 'Printer connection established but write failed.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'bytes'   => $written,
            'printer' => $ip . ':' . $port,
        ]);
    }

    // ------------- GET PAYMENTS SALE -----------\\

    public function Payments_Sale(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentSale::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Sale = Sale::findOrFail($id);

        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === Sale->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Sale);
        }

        $payments = PaymentSale::with('sale', 'payment_method')
            ->where('sale_id', $id)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $Sale->GrandTotal - $Sale->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);

    }

    // ------------- Reference Number Order SALE -----------\\

    public function getNumberOrder()
    {
        // Get prefix from settings, fallback to 'SL' if not set
        $setting = \App\Models\Setting::where('deleted_at', '=', null)->first();
        $prefix = !empty($setting->sale_prefix) ? $setting->sale_prefix : 'SL';
        
        // Get the last sale with a reference that starts with the prefix
        // Support both formats: WO_12 and WO-12
        $last = DB::table('sales')
            ->where(function ($q) use ($prefix) {
                $q->where('Ref', 'like', $prefix.'_%')
                    ->orWhere('Ref', 'like', $prefix.'-%');
            })
            ->latest('id')
            ->first();

        if ($last) {
            $item = (string) $last->Ref;

            // Accept either '_' or '-' as separator
            if (strpos($item, '_') !== false) {
                $nwMsg = explode('_', $item);
                $sep = '_';
            } elseif (strpos($item, '-') !== false) {
                $nwMsg = explode('-', $item);
                $sep = '-';
            } else {
                $nwMsg = [$prefix];
                $sep = '_';
            }

            // Ensure valid structure before processing
            if (isset($nwMsg[1]) && is_numeric($nwMsg[1])) {
                $inMsg = $nwMsg[1] + 1;
                $code = $nwMsg[0].$sep.str_pad($inMsg, 4, '0', STR_PAD_LEFT);
            } else {
                $code = $prefix.'_0001'; // Fallback if reference is corrupted
            }
        } else {
            $code = $prefix.'_0001';
        }

        return $code;
    }

    // ---------------- get_import_sales ---------------\\

    public function get_import_sales(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        $user_auth = Auth::user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $clients = Client::whereNull('deleted_at')->get(['id', 'name']);
        $sales_agents = SalesAgent::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'warehouses' => $warehouses,
            'clients' => $clients,
            'sales_agents' => $sales_agents,
        ]);
    }

    // ------ store_import_sales -------------\\

    public function store_import_sales(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        $data = $this->request_products_csv($request);
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $request->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        $warehouse_id = (int) $request->warehouse_id;

        // Validate stock availability for all products before processing
        $insufficient = $this->validateStockForImportSales($data, $warehouse_id);
        if (! empty($insufficient)) {
            $lines = array_map(function ($item) {
                return sprintf(
                    '%s: requested %s, available %s',
                    $item['product_code'],
                    $item['requested'],
                    $item['available']
                );
            }, $insufficient);

            return response()->json([
                'msg' => 'Insufficient stock for one or more products. '.implode('; ', $lines),
                'status' => false,
                'insufficient' => $insufficient,
            ], 422);
        }

        DB::transaction(function () use ($request, $data) {
            $order = new Sale;
            $order->is_pos = 0;
            $order->date = $request->date;
            $order->time = now()->toTimeString();
            $order->Ref = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->GrandTotal = 0;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate ?? 0;
            $order->TaxNet = 0;
            $order->discount = $request->discount ?? 0;
            $order->discount_Method = '2';
            $order->shipping = $request->shipping ?? 0;
            $order->statut = $request->statut ?? 'completed';
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::id();
            $order->sales_agent_id = $request->sales_agent_id ?? null;
            $order->save();

            $total = 0;
            $orderDetails = [];

            foreach ($data as $value) {
                $productId = (int) ($value['product_id'] ?? 0);
                $productVariantId = isset($value['product_variant_id']) && $value['product_variant_id'] !== '' ? (int) $value['product_variant_id'] : null;
                $price = (float) ($value['price'] ?? 0);
                $unitSaleId = (int) ($value['unit_sale_id'] ?? 0);
                $qty = (float) ($value['qty'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $product = Product::whereNull('deleted_at')->find($productId);
                if (! $product) {
                    continue;
                }

                $unit = Unit::where('id', $unitSaleId)->first();
                $lineTotal = $qty * $price;
                $total += $lineTotal;

                $baseDate = $request->date ? \Carbon\Carbon::parse($request->date) : now();
                $warrantyGuarantee = SaleDetail::computeWarrantyGuaranteeDates($product, $baseDate);

                $orderDetails[] = array_merge([
                    'date' => $request->date,
                    'sale_id' => $order->id,
                    'sale_unit_id' => $unitSaleId,
                    'quantity' => $qty,
                    'price' => $price,
                    'TaxNet' => 0,
                    'tax_method' => 1,
                    'discount' => 0,
                    'discount_method' => 2,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'total' => $lineTotal,
                    'price_type' => 'retail',
                ], $warrantyGuarantee);

                if ($order->statut === 'completed' && $product->type !== 'is_service') {
                    $pwQuery = product_warehouse::whereNull('deleted_at')
                        ->where('warehouse_id', $order->warehouse_id)
                        ->where('product_id', $productId);
                    if ($productVariantId !== null) {
                        $pwQuery->where('product_variant_id', $productVariantId);
                    } else {
                        $pwQuery->whereNull('product_variant_id');
                    }
                    $product_warehouse = $pwQuery->first();

                    if ($unit && $product_warehouse) {
                        if ($unit->operator == '/') {
                            $product_warehouse->qte -= $qty / $unit->operator_value;
                        } else {
                            $product_warehouse->qte -= $qty * $unit->operator_value;
                        }
                        $product_warehouse->save();
                    }
                }
            }

            SaleDetail::insert($orderDetails);

            $sale_data = Sale::find($order->id);
            $total_without_discount = $total - $sale_data->discount;
            $TaxNet = ($total_without_discount * $sale_data->tax_rate) / 100;
            $sale_data->TaxNet = $TaxNet;
            $sale_data->GrandTotal = $total_without_discount + $TaxNet + $sale_data->shipping;
            $sale_data->save();
        }, 10);

        return response()->json(['success' => true, 'message' => 'Sale created successfully.']);
    }

    /**
     * Validate stock availability for all products in the import data.
     * Data rows must contain product_id, product_variant_id (nullable), productcode, qty (enriched by request_products_csv).
     * Returns array of items with insufficient stock: ['product_code' => ..., 'requested' => ..., 'available' => ...].
     */
    protected function validateStockForImportSales(array $data, int $warehouse_id): array
    {
        $byKey = [];
        foreach ($data as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $variantId = isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null;
            $qty = (float) ($row['qty'] ?? 0);
            $code = trim($row['productcode'] ?? '');
            if ($productId > 0 && $qty > 0) {
                $key = $variantId !== null ? "{$productId}_{$variantId}" : "{$productId}_";
                if (! isset($byKey[$key])) {
                    $byKey[$key] = ['product_id' => $productId, 'product_variant_id' => $variantId, 'product_code' => $code, 'qty' => 0];
                }
                $byKey[$key]['qty'] += $qty;
            }
        }

        $insufficient = [];
        foreach ($byKey as $key => $agg) {
            $productId = $agg['product_id'];
            $variantId = $agg['product_variant_id'];
            $requestedQty = $agg['qty'];
            $productCode = $agg['product_code'];

            $product = Product::whereNull('deleted_at')->find($productId);
            if (! $product) {
                continue;
            }
            if ($product->type === 'is_service') {
                continue;
            }

            $unit = Unit::where('id', $product->unit_sale_id)->first();
            $pwQuery = product_warehouse::whereNull('deleted_at')
                ->where('warehouse_id', $warehouse_id)
                ->where('product_id', $productId);
            if ($variantId !== null) {
                $pwQuery->where('product_variant_id', $variantId);
            } else {
                $pwQuery->whereNull('product_variant_id');
            }
            $pw = $pwQuery->first();

            $availableBase = $pw ? (float) $pw->qte : 0;
            $opVal = $unit ? (float) $unit->operator_value : 1;
            if ($opVal <= 0) {
                $opVal = 1;
            }

            if (! $unit) {
                $requiredBase = $requestedQty;
                $availableSale = $availableBase;
            } else {
                if ($unit->operator == '/') {
                    $requiredBase = $requestedQty / $opVal;
                    $availableSale = $availableBase * $opVal;
                } else {
                    $requiredBase = $requestedQty * $opVal;
                    $availableSale = $availableBase / $opVal;
                }
            }

            if ($availableBase < $requiredBase) {
                $insufficient[] = [
                    'product_code' => $productCode,
                    'requested' => round($requestedQty, 2),
                    'available' => round($availableSale, 2),
                ];
            }
        }

        return $insufficient;
    }

    // ------ batches_for_sale ---------------\\

    /**
     * Return FEFO-ordered active batches available for a product (+variant) at a warehouse.
     * Used by create_sale.vue to populate the batch selector for batch-tracked products.
     */
    public function batches_for_sale(Request $request, $product_id, $warehouse_id, $variant_id = null)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

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

    // ------ preview_import_sales -------------\\

    public function preview_import_sales(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        $data = $this->request_products_csv($request);
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }
        if (! is_array($data)) {
            return response()->json(['msg' => 'Could not read file.', 'status' => false], 422);
        }

        $rows = [];
        $grand = 0;
        foreach ($data as $value) {
            $productId = (int) ($value['product_id'] ?? 0);
            $variantId = isset($value['product_variant_id']) && $value['product_variant_id'] !== '' ? (int) $value['product_variant_id'] : null;
            $qty = (float) ($value['qty'] ?? 0);
            $price = (float) ($value['price'] ?? 0);
            $unitSaleId = (int) ($value['unit_sale_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }
            $product = Product::whereNull('deleted_at')->with('unitSale')->find($productId);
            if (! $product) {
                continue;
            }

            $code = trim($value['productcode'] ?? '');
            $name = $product->name;
            if ($variantId !== null) {
                $variant = ProductVariant::whereNull('deleted_at')->where('id', $variantId)->first();
                if ($variant) {
                    $code = $variant->code;
                    $name = '['.$variant->name.'] '.$product->name;
                }
            }

            $unitShort = null;
            if ($unitSaleId > 0) {
                $unit = Unit::where('id', $unitSaleId)->first();
                $unitShort = $unit ? $unit->ShortName : null;
            }
            if (! $unitShort && $product->unitSale) {
                $unitShort = $product->unitSale->ShortName;
            }

            $total = $qty * $price;
            $grand += $total;

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'qty' => $qty,
                'price' => $price,
                'total' => $total,
                'unit' => $unitShort,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
            ];
        }

        return response()->json([
            'status' => true,
            'rows' => $rows,
            'grand_total' => $grand,
            'count' => count($rows),
        ]);
    }

    /**
     * Resolve a CSV product code for import sales.
     * - If code is a variant code (product_variants.code): returns product + variant + price + unit_sale_id.
     * - If code is a simple product (no variants): returns product + price + unit_sale_id.
     * - If code is a product with is_variant=1: returns ['reason' => 'has_variants', ...] so caller can require variant codes.
     * - If code not found: returns null.
     *
     * @return array|null
     */
    protected function resolveCodeForImportSale(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $variant = ProductVariant::whereNull('deleted_at')->where('code', $code)->with('product')->first();
        if ($variant) {
            $product = $variant->product;
            if (! $product || $product->deleted_at !== null) {
                return null;
            }

            return [
                'product' => $product,
                'variant' => $variant,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'price' => (float) $variant->price,
                'unit_sale_id' => $product->unit_sale_id,
            ];
        }

        $product = Product::whereNull('deleted_at')->where('code', $code)->first();
        if (! $product) {
            return null;
        }

        if (! empty($product->is_variant)) {
            $variantCodes = ProductVariant::where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->pluck('code')
                ->toArray();

            return [
                'reason' => 'has_variants',
                'product' => $product,
                'variant_codes' => $variantCodes,
            ];
        }

        return [
            'product' => $product,
            'variant' => null,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'price' => (float) $product->price,
            'unit_sale_id' => $product->unit_sale_id,
        ];
    }

    // Parse CSV for import (productcode;qty). Returns array or JsonResponse on error.
    public function request_products_csv(Request $request)
    {
        ini_set('max_execution_time', 2000);

        if (! $request->hasFile('products')) {
            return response()->json(['msg' => 'CSV file is required.', 'status' => false], 422);
        }

        $file = $request->file('products');
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return response()->json(['msg' => 'File must be in CSV format.', 'status' => false], 422);
        }

        $data = [];
        $max_line_length = defined('MAX_LINE_LENGTH') ? MAX_LINE_LENGTH : 10000;
        if (($handle = fopen($file->getPathname(), 'r')) === false) {
            return response()->json(['msg' => 'Could not read file.', 'status' => false], 422);
        }

        $header = fgetcsv($handle, $max_line_length, ';');
        $escapedHeader = [];
        foreach ($header as $value) {
            $lheader = strtolower($value);
            $escapedHeader[] = preg_replace('/[^a-z]/', '', $lheader);
        }
        $header_colcount = count($header);

        while (($row = fgetcsv($handle, $max_line_length, ';')) !== false) {
            if (count($row) === $header_colcount) {
                $data[] = array_combine($escapedHeader, $row);
            }
        }
        fclose($handle);

        $cleanedData = [];
        foreach ($data as $row) {
            $cleanedRow = [];
            foreach ($row as $key => $value) {
                $cleanedRow[trim($key)] = $value;
            }
            $cleanedData[] = $cleanedRow;
        }

        $productCodes = array_unique(array_filter(array_column($cleanedData, 'productcode')));
        if (count(array_column($cleanedData, 'productcode')) !== count(array_unique(array_column($cleanedData, 'productcode')))) {
            return response()->json(['msg' => 'Duplicate product code found in CSV file.', 'status' => false], 422);
        }

        $missingProductCodes = [];
        $productsRequiringVariant = [];
        $codeToResolved = [];

        foreach ($productCodes as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $resolved = $this->resolveCodeForImportSale($code);
            if ($resolved === null) {
                $missingProductCodes[] = $code;
                continue;
            }
            if (isset($resolved['reason']) && $resolved['reason'] === 'has_variants') {
                $productsRequiringVariant[] = [
                    'code' => $code,
                    'variant_codes' => $resolved['variant_codes'],
                ];
                continue;
            }
            $codeToResolved[$code] = $resolved;
        }

        if (! empty($productsRequiringVariant)) {
            $lines = array_map(function ($item) {
                $list = implode(', ', $item['variant_codes']);

                return $item['code'].' has variants; use exact variant codes: '.$list;
            }, $productsRequiringVariant);

            return response()->json([
                'msg' => 'Product(s) with variants must use variant codes from the Variants table.',
                'status' => false,
                'details' => $lines,
            ], 422);
        }

        if (! empty($missingProductCodes)) {
            return response()->json([
                'msg' => 'The following product codes do not exist: '.implode(', ', $missingProductCodes),
                'status' => false,
            ], 422);
        }

        $rules = [];
        foreach ($cleanedData as $index => $row) {
            $rules[$index.'.productcode'] = 'required';
            $rules[$index.'.qty'] = 'required|numeric';
        }
        $validator = validator()->make($cleanedData, $rules);
        if ($validator->fails()) {
            return response()->json(['msg' => 'Validation failed.', 'errors' => $validator->errors(), 'status' => false], 422);
        }

        // Enrich each row with product_id, product_variant_id, price, unit_sale_id
        foreach ($cleanedData as $index => $row) {
            $code = trim($row['productcode'] ?? '');
            if ($code !== '' && isset($codeToResolved[$code])) {
                $r = $codeToResolved[$code];
                $cleanedData[$index]['product_id'] = $r['product_id'];
                $cleanedData[$index]['product_variant_id'] = $r['product_variant_id'];
                $cleanedData[$index]['price'] = $r['price'];
                $cleanedData[$index]['unit_sale_id'] = $r['unit_sale_id'];
            }
        }

        return $cleanedData;
    }

    // ------------- SALE PDF -----------\\

    public function Sale_PDF(Request $request, $id)
    {

        $details = [];
        $helpers = new helpers;
        $sale_data = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $sale['client_name'] = $sale_data['client']->name;
        $sale['client_phone'] = $sale_data['client']->phone;
        $sale['client_adr'] = $sale_data['client']->adresse;
        $sale['client_email'] = $sale_data['client']->email;
        $sale['client_tax'] = $sale_data['client']->tax_number;
        $sale['TaxNet'] = number_format($sale_data->TaxNet, 2, '.', '');
        $sale['discount'] = number_format($sale_data->discount, 2, '.', '');
        $sale['discount_Method'] = $sale_data->discount_Method ?? '2'; // '1' = percent, '2' = fixed
        $sale['discount_from_points'] = number_format($sale_data->discount_from_points ?? 0, 2, '.', '');
        $sale['shipping'] = number_format($sale_data->shipping, 2, '.', '');
        $sale['statut'] = $sale_data->statut;
        $sale['Ref'] = $sale_data->Ref;
        $sale['date'] = $sale_data->date.' '.$sale_data->time;
        $sale['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale['paid_amount'] = number_format($sale_data->paid_amount, 2, '.', '');
        $sale['due'] = number_format($sale['GrandTotal'] - $sale['paid_amount'], 2, '.', '');
        $sale['payment_status'] = $sale_data->payment_statut;

        $detail_id = 0;
        foreach ($sale_data['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            } else {
                $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();

                if ($product_unit_sale_id['unitSale']) {
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }
                $unit = null;

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
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unitSale'] = $unit ? $unit->ShortName : '';
            $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.sale_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'sale' => $sale,
            'details' => $details,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);

        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = PDF::loadHTML($Html, 'UTF-8');

        return $pdf->download('sale.pdf');

    }

    /**
     * SALE A4 HTML (for reliable browser print dialog)
     *
     * Uses the same template (`pdf.sale_pdf`) but returns raw HTML instead of a PDF.
     * This is used by the POS A4 flow, which opens a popup, injects the HTML, and
     * calls window.print() – mirroring the POS thermal invoice behavior.
     */
    public function Sale_PDF_Inline(Request $request, $id)
    {
        $details = [];
        $helpers = new helpers;
        $sale_data = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $sale['client_name'] = $sale_data['client']->name;
        $sale['client_phone'] = $sale_data['client']->phone;
        $sale['client_adr'] = $sale_data['client']->adresse;
        $sale['client_email'] = $sale_data['client']->email;
        $sale['client_tax'] = $sale_data['client']->tax_number;
        $sale['TaxNet'] = number_format($sale_data->TaxNet, 2, '.', '');
        $sale['discount'] = number_format($sale_data->discount, 2, '.', '');
        $sale['discount_Method'] = $sale_data->discount_Method ?? '2'; // '1' = percent, '2' = fixed
        $sale['discount_from_points'] = number_format($sale_data->discount_from_points ?? 0, 2, '.', '');
        $sale['shipping'] = number_format($sale_data->shipping, 2, '.', '');
        $sale['statut'] = $sale_data->statut;
        $sale['Ref'] = $sale_data->Ref;
        $sale['date'] = $sale_data->date.' '.$sale_data->time;
        $sale['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale['paid_amount'] = number_format($sale_data->paid_amount, 2, '.', '');
        $sale['due'] = number_format($sale['GrandTotal'] - $sale['paid_amount'], 2, '.', '');
        $sale['payment_status'] = $sale_data->payment_statut;

        $detail_id = 0;
        foreach ($sale_data['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            } else {
                $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();

                if ($product_unit_sale_id['unitSale']) {
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }
                $unit = null;
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
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unitSale'] = $unit ? $unit->ShortName : '';
            $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.sale_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'sale' => $sale,
            'details' => $details,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);

        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        // When rendering as HTML in the browser, filesystem paths like public_path('images/...')
        // do not work as <img src>. Convert any ".../public/images/<file>" path (Windows or Unix)
        // into a proper web URL so logos/images display.
        try {
            $webImagesPath = rtrim(url('images'), '/').'/';
            $Html = preg_replace_callback(
                '~(?:[A-Za-z]:)?[\/\\\\][^"\']*?[\/\\\\]public[\/\\\\]images[\/\\\\]([^"\'>]+)~',
                function ($m) use ($webImagesPath) {
                    $file = ltrim($m[1], '/\\');
                    return $webImagesPath.$file;
                },
                $Html
            );
        } catch (\Throwable $e) {
            // If anything goes wrong, fall back to the original HTML.
        }

        // Return raw HTML so the POS popup can inject it and call window.print().
        return response($Html);
    }

    // ----------------Show Form Create Sale ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);
        $sales_agents = SalesAgent::where('deleted_at', '=', null)->get(['id', 'name']);
        $stripe_key = config('app.STRIPE_KEY');
        $settings = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'stripe_key' => $stripe_key,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'sales_agents' => $sales_agents,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
        ]);

    }

    // ------------- Show Form Edit Sale -----------\\

    public function edit(Request $request, $id)
    {
        if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
            return response()->json(['success' => false, 'Return exist for the Transaction' => false], 403);
        } else {
            $this->authorizeForUser($request->user('api'), 'update', Sale::class);
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $Sale_data = Sale::with('details.product.unitSale')
                ->where('deleted_at', '=', null)
                ->findOrFail($id);

            /**
             * Warehouses restriction
             * Allow if:
             * - user has access to all warehouses (is_all_warehouses = 1)
             * - OR sale warehouse_id is in user's assigned warehouses
            */
            $user_auth = auth()->user();

            if (! $user_auth->is_all_warehouses) {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                    ->pluck('warehouse_id')
                    ->toArray();

                if (empty($Sale_data->warehouse_id) || ! in_array($Sale_data->warehouse_id, $warehouses_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not allowed to access this sale (warehouse restriction).',
                    ], 403);
                }
            }


            $details = [];
            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === sale->id
                $this->authorizeForUser($request->user('api'), 'check_record', $Sale_data);
            }

            if ($Sale_data->client_id) {
                if (Client::where('id', $Sale_data->client_id)
                    ->where('deleted_at', '=', null)
                    ->first()) {
                    $sale['client_id'] = $Sale_data->client_id;
                } else {
                    $sale['client_id'] = '';
                }
            } else {
                $sale['client_id'] = '';
            }

            if ($Sale_data->warehouse_id) {
                if (Warehouse::where('id', $Sale_data->warehouse_id)
                    ->where('deleted_at', '=', null)
                    ->first()) {
                    $sale['warehouse_id'] = $Sale_data->warehouse_id;
                } else {
                    $sale['warehouse_id'] = '';
                }
            } else {
                $sale['warehouse_id'] = '';
            }

            $sale['sales_agent_id'] = $Sale_data->sales_agent_id ?? null;

            $sale['date'] = $Sale_data->date;
            $sale['tax_rate'] = $Sale_data->tax_rate;
            $sale['TaxNet'] = $Sale_data->TaxNet;
            $sale['used_points'] = $Sale_data->used_points;
            $sale['discount'] = $Sale_data->discount;
            $sale['discount_Method'] = $Sale_data->discount_Method ?? '2'; // ensure method is sent
            $sale['shipping'] = $Sale_data->shipping;
            $sale['statut'] = $Sale_data->statut;
            $sale['notes'] = $Sale_data->notes;

            // Preload existing batch pivots for this sale's details so the edit UI can
            // render the same selector the create-sale page uses (optional override).
            $saleBatchesByDetail = app(\App\Services\BatchService::class)
                ->batchesForSaleDetails($Sale_data['details']);

            $detail_id = 0;
            foreach ($Sale_data['details'] as $detail) {

                // check if detail has sale_unit_id Or Null
                if ($detail->sale_unit_id !== null) {
                    $unit = Unit::where('id', $detail->sale_unit_id)->first();
                    $data['no_unit'] = 1;
                } else {
                    $product_unit_sale_id = Product::with('unitSale')
                        ->where('id', $detail->product_id)
                        ->first();

                    if ($product_unit_sale_id['unitSale']) {
                        $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                    }
                    $unit = null;

                    $data['no_unit'] = 0;
                }

                if ($detail->product_variant_id) {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('deleted_at', '=', null)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->where('warehouse_id', $Sale_data->warehouse_id)
                        ->first();

                    $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                        ->where('id', $detail->product_variant_id)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = $detail->product_variant_id;
                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } elseif ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }

                } else {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('deleted_at', '=', null)->where('warehouse_id', $Sale_data->warehouse_id)
                        ->where('product_variant_id', '=', null)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = null;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } elseif ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }

                }

                $data['id'] = $detail->id;
                $data['stock'] = $detail['product']['type'] != 'is_service' ? $stock : '---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['product_id'] = $detail->product_id;
                $data['is_batch_tracked'] = (bool) ($detail['product']['is_batch_tracked'] ?? false);
                $data['batches'] = $saleBatchesByDetail[(int) $detail->id] ?? [];
                $data['total'] = $detail->total;
                $data['quantity'] = $detail->quantity;
                $data['qte_copy'] = $detail->quantity;
                $data['etat'] = 'current';
                $data['unitSale'] = $unit ? $unit->ShortName : '';
                $data['sale_unit_id'] = $unit ? $unit->id : '';
                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }

                $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
                $data['Unit_price'] = $detail->price;

                $data['tax_percent'] = $detail->TaxNet;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                }

                $details[] = $data;
            }

            // get warehouses assigned to user
            $user_auth = auth()->user();
            if ($user_auth->is_all_warehouses) {
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            } else {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            }

            $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
            $sales_agents = SalesAgent::where('deleted_at', '=', null)->get(['id', 'name']);
            $settings = Setting::where('deleted_at', '=', null)->first();

            return response()->json([
                'details' => $details,
                'sale' => $sale,
                'clients' => $clients,
                'warehouses' => $warehouses,
                'sales_agents' => $sales_agents,
                'discount_from_points' => $Sale_data->discount_from_points,
                'point_to_amount_rate' => $settings->point_to_amount_rate,
            ]);
        }

    }

    // ------------- Show Form Convert To Sale -----------\\

    public function Elemens_Change_To_Sale(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Quotation::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Quotation = Quotation::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        /**
         * Warehouses restriction
         * Allow if:
         * - user has access to all warehouses (is_all_warehouses = 1)
         * - OR sale warehouse_id is in user's assigned warehouses
        */
        $user_auth = auth()->user();

        if (! $user_auth->is_all_warehouses) {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)
                ->pluck('warehouse_id')
                ->toArray();

            if (empty($Quotation->warehouse_id) || ! in_array($Quotation->warehouse_id, $warehouses_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to access this sale (warehouse restriction).',
                ], 403);
            }
        }

        $details = [];
        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === Quotation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
        }

        if ($Quotation->client_id) {
            if (Client::where('id', $Quotation->client_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $sale['client_id'] = $Quotation->client_id;
            } else {
                $sale['client_id'] = '';
            }
        } else {
            $sale['client_id'] = '';
        }

        if ($Quotation->warehouse_id) {
            if (Warehouse::where('id', $Quotation->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $sale['warehouse_id'] = $Quotation->warehouse_id;
            } else {
                $sale['warehouse_id'] = '';
            }
        } else {
            $sale['warehouse_id'] = '';
        }

        $sale['sales_agent_id'] = null;

        $sale['date'] = $Quotation->date;
        $sale['TaxNet'] = $Quotation->TaxNet;
        $sale['tax_rate'] = $Quotation->tax_rate;
        $sale['discount'] = $Quotation->discount;
        $sale['shipping'] = $Quotation->shipping;
        $sale['statut'] = 'completed';
        $sale['notes'] = $Quotation->notes;

        $detail_id = 0;
        foreach ($Quotation['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null || $detail['product']['type'] == 'is_service') {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();

                if ($detail->product_variant_id) {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('deleted_at', '=', null)
                        ->first();
                    $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                        ->where('id', $detail->product_variant_id)->where('deleted_at', null)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = $detail->product_variant_id;
                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } elseif ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }

                } else {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('product_variant_id', '=', null)
                        ->where('deleted_at', '=', null)
                        ->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = null;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } elseif ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }
                }

                $data['id'] = $id;
                $data['stock'] = $detail['product']['type'] != 'is_service' ? $stock : '---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['total'] = $detail->total;
                $data['etat'] = 'current';
                $data['qte_copy'] = $detail->quantity;
                $data['unitSale'] = $unit ? $unit->ShortName : '';
                $data['sale_unit_id'] = $unit ? $unit->id : '';

                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }
                $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
                $data['Unit_price'] = $detail->price;
                $data['tax_percent'] = $detail->TaxNet;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                }

                $details[] = $data;
            }
        }

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $sales_agents = SalesAgent::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'sale' => $sale,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'sales_agents' => $sales_agents,
        ]);

    }

    // ------------------- get_Products_by_sale -----------------\\

    public function get_Products_by_sale(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'create', SaleReturn::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $SaleReturn = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = [];

        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
        }

        $Return_detail['client_id'] = $SaleReturn->client_id;
        $Return_detail['warehouse_id'] = $SaleReturn->warehouse_id;
        $Return_detail['sale_id'] = $SaleReturn->id;
        $Return_detail['tax_rate'] = 0;
        $Return_detail['TaxNet'] = 0;
        $Return_detail['discount'] = 0;
        $Return_detail['shipping'] = 0;
        $Return_detail['statut'] = 'received';
        $Return_detail['notes'] = '';

        $detail_id = 0;
        foreach ($SaleReturn['details'] as $detail) {

            // check if detail has sale_unit_id Or Null
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
                $data['no_unit'] = 1;
            } else {
                $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();

                if ($product_unit_sale_id['unitSale']) {
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }
                $unit = null;

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name.']'.$detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } elseif ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } elseif ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            }

            $data['id'] = $detail->id;
            $data['stock'] = $detail['product']['type'] != 'is_service' ? $stock : '---';
            $data['detail_id'] = $detail_id += 1;
            $data['quantity'] = $detail->quantity;
            $data['sale_quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['unitSale'] = $unit->ShortName;
            $data['sale_unit_id'] = $unit->id;
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {

                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            }

            $details[] = $data;
        }

        return response()->json([
            'details' => $details,
            'sale_return' => $Return_detail,
        ]);

    }

    // ------------- Send sale on Email -----------\\

    public function Send_Email(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);

        // sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        // settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        // the custom msg of sale (by default locale with fallback to en)
        $emailMessage = EmailMessage::getForLocale('sale');

        if ($emailMessage) {
            $message_body = $emailMessage->body;
            $message_subject = $emailMessage->subject;
        } else {
            $message_body = '';
            $message_subject = '';
        }

        // Tags
        $random_number = Str::random(10);
        $invoice_url = url('/api/sale_pdf/'.$request->id.'?'.$random_number);
        $invoice_number = $sale->Ref;

        $total_amount = $currency.' '.number_format($sale->GrandTotal, 2, '.', ',');
        $paid_amount = $currency.' '.number_format($sale->paid_amount, 2, '.', ',');
        $due_amount = $currency.' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');

        $contact_name = $sale['client']->name;
        $business_name = $settings->CompanyName;

        // receiver email
        $receiver_email = $sale['client']->email;

        // replace the text with tags
        $message_body = str_replace('{contact_name}', $contact_name, $message_body);
        $message_body = str_replace('{business_name}', $business_name, $message_body);
        $message_body = str_replace('{invoice_url}', $invoice_url, $message_body);
        $message_body = str_replace('{invoice_number}', $invoice_number, $message_body);

        $message_body = str_replace('{total_amount}', $total_amount, $message_body);
        $message_body = str_replace('{paid_amount}', $paid_amount, $message_body);
        $message_body = str_replace('{due_amount}', $due_amount, $message_body);

        $email['subject'] = $message_subject;
        $email['body'] = $message_body;
        $email['company_name'] = $business_name;

        $this->Set_config_mail();

        Mail::to($receiver_email)->send(new CustomEmail($email));

        return response()->json(['message' => 'Email sent successfully'], 200);

    }

    // -------------------Sms Notifications -----------------\\

    public function Send_SMS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);

        // sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        // settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

        // the custom msg of sale (by default locale with fallback to en)
        $smsMessage = SMSMessage::getForLocale('sale');

        if ($smsMessage) {
            $message_text = $smsMessage->text;
        } else {
            $message_text = '';
        }

        // Tags
        $random_number = Str::random(10);
        $invoice_url = url('/api/sale_pdf/'.$request->id.'?'.$random_number);
        $invoice_number = $sale->Ref;

        $total_amount = $currency.' '.number_format($sale->GrandTotal, 2, '.', ',');
        $paid_amount = $currency.' '.number_format($sale->paid_amount, 2, '.', ',');
        $due_amount = $currency.' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');

        $contact_name = $sale['client']->name;
        $business_name = $settings->CompanyName;

        // receiver Number
        $receiverNumber = $sale['client']->phone;

        // replace the text with tags
        $message_text = str_replace('{contact_name}', $contact_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
        $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);

        $message_text = str_replace('{total_amount}', $total_amount, $message_text);
        $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
        $message_text = str_replace('{due_amount}', $due_amount, $message_text);

        // twilio
        if ($default_sms_gateway->title == 'twilio') {
            try {

                $account_sid = env('TWILIO_SID');
                $auth_token = env('TWILIO_TOKEN');
                $twilio_number = env('TWILIO_FROM');

                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number,
                    'body' => $message_text]);

            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
        // termii
        elseif ($default_sms_gateway->title == 'termii') {

            $client = new Client_termi;
            $url = 'https://api.ng.termii.com/api/sms/send';

            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post($url, [
                    'json' => $payload,
                ]);

                $result = json_decode($response->getBody(), true);

                return response()->json($result);
            } catch (\Exception $e) {
                Log::error('Termii SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }

        }
        //  //---- infobip
        elseif ($default_sms_gateway->title == 'infobip') {

            $BASE_URL = env('base_url');
            $API_KEY = env('api_key');
            $SENDER = env('sender_from');

            $configuration = (new Configuration)
                ->setHost($BASE_URL)
                ->setApiKeyPrefix('Authorization', 'App')
                ->setApiKey('Authorization', $API_KEY);

            $client = new Client_guzzle;

            $sendSmsApi = new SendSMSApi($client, $configuration);
            $destination = (new SmsDestination)->setTo($receiverNumber);
            $message = (new SmsTextualMessage)
                ->setFrom($SENDER)
                ->setText($message_text)
                ->setDestinations([$destination]);

            $request = (new SmsAdvancedTextualRequest)->setMessages([$message]);

            try {
                $smsResponse = $sendSmsApi->sendSmsMessage($request);
                echo 'Response body: '.$smsResponse;
            } catch (Throwable $apiException) {
                echo 'HTTP Code: '.$apiException->getCode()."\n";
            }

        }
        // custom gateway
        elseif ($default_sms_gateway->title == 'custom') {
            try {
                (new \App\Services\CustomSmsGateway)->send($receiverNumber, $message_text);
            } catch (\Throwable $e) {
                Log::error('Custom SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => true]);

    }

    // sales_send_whatsapp
    public function sales_send_whatsapp(Request $request)
    {

        // sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        // settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        // the custom msg of sale (by default locale with fallback to en)
        $smsMessage = SMSMessage::getForLocale('sale');

        if ($smsMessage) {
            $message_text = $smsMessage->text;
        } else {
            $message_text = '';
        }

        // Tags
        $random_number = Str::random(10);
        $invoice_url = url('/api/sale_pdf/'.$request->id.'?'.$random_number);
        $invoice_number = $sale->Ref;

        $total_amount = $currency.' '.number_format($sale->GrandTotal, 2, '.', ',');
        $paid_amount = $currency.' '.number_format($sale->paid_amount, 2, '.', ',');
        $due_amount = $currency.' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');

        $contact_name = $sale['client']->name;
        $business_name = $settings->CompanyName;

        // receiver Number
        $receiverNumber = $sale['client']->phone;

        // Check if the phone number is empty or null
        if (empty($receiverNumber) || $receiverNumber == null || $receiverNumber == 'null' || $receiverNumber == '') {
            return response()->json(['error' => 'Phone number is missing'], 400);
        }

        // replace the text with tags
        $message_text = str_replace('{contact_name}', $contact_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
        $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);

        $message_text = str_replace('{total_amount}', $total_amount, $message_text);
        $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
        $message_text = str_replace('{due_amount}', $due_amount, $message_text);

        return response()->json(['message' => $message_text, 'phone' => $receiverNumber]);

    }

    // get_today_sales
    public function get_today_sales(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
 
        // Determine whether the current user has access to all warehouses or only specific ones.
        // Users with is_all_warehouses = false are restricted to their assigned warehouses only.
        $user_auth = auth()->user();
        $is_all_warehouses = $user_auth->is_all_warehouses;
 
        $today = Carbon::today()->toDateString();
        $data['today'] = $today;
 
        // If the user is restricted, fetch their assigned warehouse IDs once and reuse below.
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user_auth->id)
                ->pluck('warehouse_id')
                ->toArray();
        }
 
        // Total sales amount for today, scoped to the user's accessible warehouses.
        $salesQuery = Sale::whereNull('deleted_at')->whereDate('date', $today);
        if (! $is_all_warehouses) {
            $salesQuery->whereIn('warehouse_id', $warehouse_ids);
        }
        $data['total_sales_amount'] = $salesQuery->sum('GrandTotal');
 
        // Total amount paid for today, scoped the same way.
        $paidQuery = Sale::whereNull('deleted_at')->whereDate('date', $today);
        if (! $is_all_warehouses) {
            $paidQuery->whereIn('warehouse_id', $warehouse_ids);
        }
        $data['total_amount_paid'] = $paidQuery->sum('paid_amount');
 
        // Aggregate payment totals per payment method for today.
        $methods = PaymentMethod::where('deleted_at', '=', null)->get();
 
        $paymentTotalsQuery = PaymentSale::whereNull('deleted_at')
            ->whereDate('date', $today);
 
        if (! $is_all_warehouses) {
            // PaymentSale has no warehouse_id column, so we scope by the sale_ids
            // that belong to the user's permitted warehouses for today.
            $sale_ids = Sale::whereNull('deleted_at')
                ->whereDate('date', $today)
                ->whereIn('warehouse_id', $warehouse_ids)
                ->pluck('id')
                ->toArray();
            $paymentTotalsQuery->whereIn('sale_id', $sale_ids);
        }
 
        $paymentTotals = $paymentTotalsQuery
            ->select('payment_method_id', DB::raw('SUM(montant) as total'))
            ->groupBy('payment_method_id')
            ->get()
            ->keyBy('payment_method_id');
 
        $methodsSummary = [];
 
        $data['total_cash'] = 0;
        $data['total_credit_card'] = 0;
        $data['total_cheque'] = 0;
 
        foreach ($methods as $method) {
            $methodTotal = 0;
 
            if (isset($paymentTotals[$method->id])) {
                $methodTotal = (float) $paymentTotals[$method->id]->total;
            }
 
            $methodsSummary[] = [
                'id' => $method->id,
                'name' => $method->name,
                'total' => $methodTotal,
            ];
 
            // Preserve legacy top-level keys for Cash, Credit Card, Cheque
            if (strcasecmp($method->name, 'Cash') === 0) {
                $data['total_cash'] = $methodTotal;
            } elseif (strcasecmp($method->name, 'Credit Card') === 0) {
                $data['total_credit_card'] = $methodTotal;
            } elseif (strcasecmp($method->name, 'Cheque') === 0) {
                $data['total_cheque'] = $methodTotal;
            }
        }
 
        $data['payment_methods'] = $methodsSummary;
 
        return response()->json($data);
    }



    public function Send_Subscription_Reminder_SMS($subscription_id)
    {
        // Load Subscription details with client relationship
        $subscription = Subscription::with('client')->findOrFail($subscription_id);

        // Retrieve currency and settings
        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        $settings = Setting::whereNull('deleted_at')->first();
        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->whereNull('deleted_at')->first();

        // Get SMS message template
        $smsMessage = SMSMessage::getForLocale('subscription_reminder');
        $message_text = $smsMessage ? $smsMessage->text : '';

        // Prepare tags replacement
        $client_name = $subscription->client->name;
        $business_name = $settings->CompanyName;
        $next_billing_date = Carbon::parse($subscription->next_billing_date)->format('Y-m-d');

        // Replace tags in SMS template
        $message_text = str_replace('{client_name}', $client_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{next_billing_date}', $next_billing_date, $message_text);

        // Receiver phone number
        $receiverNumber = $subscription->client->phone;

        // Send SMS based on the gateway
        if ($default_sms_gateway->title == 'twilio') {
            try {
                $account_sid = env('TWILIO_SID');
                $auth_token = env('TWILIO_TOKEN');
                $twilio_number = env('TWILIO_FROM');

                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number,
                    'body' => $message_text,
                ]);

            } catch (Exception $e) {
                Log::error('Twilio SMS Error: '.$e->getMessage());

                ErrorLog::create([
                    'context' => 'Twilio SMS',
                    'message' => $e->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'trace' => $e->getTraceAsString(),
                    ]),
                ]);

                return response()->json(['message' => $e->getMessage()], 500);
            }
        } elseif ($default_sms_gateway->title == 'termii') {
            $client = new Client_termi;
            $url = 'https://api.ng.termii.com/api/sms/send';

            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post($url, ['json' => $payload]);
                $result = json_decode($response->getBody(), true);

                return response()->json($result);
            } catch (\Exception $e) {
                Log::error('Termii SMS Error: '.$e->getMessage());

                ErrorLog::create([
                    'context' => 'Termii SMS',
                    'message' => $e->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'payload' => $payload,
                        'trace' => $e->getTraceAsString(),
                    ]),
                ]);

                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }
        } elseif ($default_sms_gateway->title == 'infobip') {
            $BASE_URL = env('base_url');
            $API_KEY = env('api_key');
            $SENDER = env('sender_from');

            $configuration = (new Configuration)
                ->setHost($BASE_URL)
                ->setApiKeyPrefix('Authorization', 'App')
                ->setApiKey('Authorization', $API_KEY);

            $client = new Client_guzzle;

            $sendSmsApi = new SendSMSApi($client, $configuration);
            $destination = (new SmsDestination)->setTo($receiverNumber);
            $message = (new SmsTextualMessage)
                ->setFrom($SENDER)
                ->setText($message_text)
                ->setDestinations([$destination]);

            $request = (new SmsAdvancedTextualRequest)->setMessages([$message]);

            try {
                $smsResponse = $sendSmsApi->sendSmsMessage($request);
                Log::info('Infobip SMS sent successfully', [$smsResponse]);
            } catch (Throwable $apiException) {
                Log::error('Infobip SMS Error: '.$apiException->getMessage());

                ErrorLog::create([
                    'context' => 'Infobip SMS',
                    'message' => $apiException->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'trace' => $apiException->getTraceAsString(),
                    ]),
                ]);

                return response()->json(['status' => 'error', 'message' => $apiException->getMessage()], 500);
            }
        } elseif ($default_sms_gateway->title == 'custom') {
            try {
                (new \App\Services\CustomSmsGateway)->send($receiverNumber, $message_text);
            } catch (\Throwable $e) {
                Log::error('Custom SMS Error: '.$e->getMessage());

                ErrorLog::create([
                    'context' => 'Custom SMS',
                    'message' => $e->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'trace' => $e->getTraceAsString(),
                    ]),
                ]);

                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => true]);
    }

    public function Send_Subscription_Payment_Success_SMS($subscription_id, $invoice_id)
    {
        $subscription = Subscription::with('client')->findOrFail($subscription_id);
        $invoice = Sale::findOrFail($invoice_id);

        $settings = Setting::first();
        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)->first();

        $message_text = 'Hello {client_name}, your subscription at {business_name} has been successfully renewed.';

        $tags = [
            '{client_name}' => $subscription->client->name,
            '{business_name}' => $settings->CompanyName,
        ];

        $message_text = str_replace(array_keys($tags), array_values($tags), $message_text);

        $receiverNumber = $subscription->client->phone;

        // Example using Termii Gateway
        if ($default_sms_gateway->title == 'termii') {
            $client = new Client_termi;
            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post('https://api.ng.termii.com/api/sms/send', ['json' => $payload]);
                $result = json_decode($response->getBody(), true);

                return response()->json($result);
            } catch (\Exception $e) {
                Log::error('Termii SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }

        } elseif ($default_sms_gateway->title == 'custom') {
            try {
                (new \App\Services\CustomSmsGateway)->send($receiverNumber, $message_text);

                return response()->json(['success' => true]);
            } catch (\Throwable $e) {
                Log::error('Custom SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }
    }

    // ------------- Get Sale Documents ----------\\
    public function getDocuments($saleId)
    {
        $this->authorizeForUser(request()->user('api'), 'view', Sale::class);
        
        $sale = Sale::findOrFail($saleId);
        
        $documents = DB::table('sale_documents')
            ->where('sale_id', $saleId)
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'documents' => $documents,
            'status' => true,
        ]);
    }

    // ------------- Upload Sale Documents ----------\\
    public function uploadDocuments(Request $request, $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Sale::class);
        
        $sale = Sale::findOrFail($saleId);

        $request->validate([
            'documents.*' => 'required|file|max:10240', // Max 10MB per file
        ]);

        $uploadedDocuments = [];

        if ($request->hasFile('documents')) {
            // Create directory if it doesn't exist
            $uploadPath = public_path('images/sale_documents');
            if (! file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            foreach ($request->file('documents') as $file) {
                // Capture metadata BEFORE moving the file (tmp file is still readable)
                $originalName = $file->getClientOriginalName();
                $size = $file->getSize();
                $mimeType = $file->getMimeType();

                $filename = time() . '_' . Str::random(10) . '_' . $originalName;
                
                // Move file to public/images/sale_documents
                $file->move($uploadPath, $filename);
                
                $relativePath = 'images/sale_documents/' . $filename;

                $documentId = DB::table('sale_documents')->insertGetId([
                    'sale_id' => $saleId,
                    'name' => $originalName,
                    'path' => $relativePath,
                    'size' => $size,
                    'mime_type' => $mimeType,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $uploadedDocuments[] = $documentId;
            }
        }

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'documents' => $uploadedDocuments,
            'status' => true,
        ]);
    }

    // ------------- Download Sale Document ----------\\
    public function downloadDocument($documentId)
    {
        $this->authorizeForUser(request()->user('api'), 'view', Sale::class);
        
        $document = DB::table('sale_documents')
            ->where('id', $documentId)
            ->where('deleted_at', null)
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found in database',
                'status' => false,
            ], 404);
        }

        $filePath = public_path($document->path);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Physical file not found on server',
                'status' => false,
                'path' => $document->path,
            ], 404);
        }

        return response()->download($filePath, $document->name);
    }

    // ------------- Delete Sale Document ----------\\
    public function deleteDocument($documentId)
    {
        $this->authorizeForUser(request()->user('api'), 'delete', Sale::class);
        
        $document = DB::table('sale_documents')
            ->where('id', $documentId)
            ->where('deleted_at', null)
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found',
                'status' => false,
            ], 404);
        }

        // Soft delete
        DB::table('sale_documents')
            ->where('id', $documentId)
            ->update(['deleted_at' => Carbon::now()]);

        // Optionally delete the physical file
        $filePath = public_path($document->path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return response()->json([
            'message' => 'Document deleted successfully',
            'status' => true,
        ]);
    }

    //------------- Public Invoice View (signed URL, no auth) -----------\\

    public function publicInvoiceView($id, $signature)
    {
        // Verify HMAC signature
        $expected = substr(hash_hmac('sha256', (string) $id, config('app.key')), 0, 32);
        if (! hash_equals($expected, $signature)) {
            abort(404);
        }

        $helpers = new helpers;
        $sale = Sale::with('details.product.unitSale', 'client', 'warehouse', 'user')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = [];
        foreach ($sale->details as $detail) {
            $unit = null;
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::find($detail->sale_unit_id);
            } else {
                $product = Product::with('unitSale')->find($detail->product_id);
                if ($product && $product->unitSale) {
                    $unit = $product->unitSale;
                }
            }

            if ($detail->product_variant_id) {
                $variant = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();
                $name = '[' . ($variant ? $variant->name : '') . ']' . $detail->product->name;
            } else {
                $name = $detail->product->name;
            }

            $details[] = [
                'name' => $name,
                'quantity' => $detail->quantity,
                'total' => $detail->total,
                'unit_sale' => $unit ? $unit->ShortName : '',
            ];
        }

        $payments = PaymentSale::with('payment_method')
            ->where('sale_id', $id)
            ->orderBy('id', 'DESC')
            ->get();

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        return view('invoice.public_invoice', [
            'sale' => $sale,
            'details' => $details,
            'payments' => $payments,
            'setting' => $settings,
            'symbol' => $symbol,
        ]);
    }
}
