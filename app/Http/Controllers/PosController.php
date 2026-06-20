<?php

namespace App\Http\Controllers;

use App\Mail\CustomEmail;
use App\Models\Account;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\DraftSale;
use App\Models\DraftSaleDetail;
use App\Models\EmailMessage;
use App\Models\Language;
use App\Models\PaymentMethod;
use App\Models\PaymentSale;
use App\Models\PaymentWithCreditCard;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\SMSMessage;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Services\BatchService;
use App\utils\helpers;
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
use Stripe;
use Twilio\Rest\Client as Client_Twilio;

class PosController extends BaseController
{
    // ------------ Create New  POS --------------\\

    public function CreatePOS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric',
            'payments.*.payment_method_id' => 'required',
        ]);

        // Block overpayment if multiple methods used
        $totalPaid = collect($request->payments)->sum('amount');
        if (count($request->payments) > 1 && $totalPaid > $request->GrandTotal) {
            return response()->json([
                'message' => 'Total Paid Exceeds Grand Total',
                'change_return' => $totalPaid - $request->GrandTotal,
            ], 422);
        }

        // Optional idempotency key generated on the frontend (crypto.randomUUID).
        // When provided, we must:
        //  - avoid creating duplicate sales (and therefore duplicate stock/payments)
        //  - treat a repeat request with the same UUID as a success that returns the existing sale
        $saleUuid = $request->input('sale_uuid');

        if ($saleUuid) {
            $existing = Sale::where('sale_uuid', $saleUuid)->first();
            if ($existing) {
                // Short‑circuit: this sale was already created earlier with the same UUID.
                // Do NOT run stock/payment logic again; simply return a success payload
                // compatible with the original response shape.
                return response()->json([
                    'success' => true,
                    'id' => $existing->id,
                    'qbo_sync' => 'skipped',
                ], 200);
            }
        }

       

        try {
            $sale = \DB::transaction(function () use ($request, $totalPaid, $saleUuid) {
                $helpers = new helpers;
                $user = Auth::user();
                // New way: Check user's record_view field (user-level boolean)
                // Backward compatibility: If record_view is null, fall back to role permission check
                $view_records = $user->hasRecordView();
                $order = new Sale;

                $order->is_pos = 1;
                $order->date = Carbon::now();
                $order->time = now()->toTimeString();
                $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
                $order->client_id = $request->client_id;
                $order->warehouse_id = $request->warehouse_id;
                $order->tax_rate = $request->tax_rate;
                $order->TaxNet = $request->TaxNet;
                $order->discount = $request->discount;
                // Ensure discount_Method is saved correctly: '1' for percentage, '2' for fixed
                $order->discount_Method = $request->has('discount_Method') ? (string) $request->discount_Method : '2';
                $order->shipping = $request->shipping;
                $order->GrandTotal = $request->GrandTotal;
                $order->notes = $request->notes;
                $order->statut = 'completed';
                $order->payment_statut = 'unpaid';
                $order->user_id = Auth::user()->id;
                if (! empty($saleUuid)) {
                    $order->sale_uuid = $saleUuid;
                }
                $order->save();

                $data = $request['details'];
                $total_points_earned = 0;

                foreach ($data as $key => $value) {
                    $product = Product::find($value['product_id']);
                    $isService = isset($value['product_type']) && $value['product_type'] === 'is_service';

                    // Resolve sale unit:
                    //  - Prefer explicit sale_unit_id from payload
                    //  - Fallback to product.unit_sale_id (unitSale relation) for backward compatibility
                    $unit = null;
                    if (! $isService) {
                        if (isset($value['sale_unit_id']) && $value['sale_unit_id'] !== null && $value['sale_unit_id'] !== '') {
                            $unit = Unit::where('id', $value['sale_unit_id'])->first();
                        }

                        if (! $unit) {
                            $productWithUnit = Product::with('unitSale')
                                ->where('id', $value['product_id'])
                                ->first();
                            if ($productWithUnit && $productWithUnit->unitSale) {
                                $unit = $productWithUnit->unitSale;
                                $value['sale_unit_id'] = $unit->id;
                            }
                        }
                    }

                    $total_points_earned += $value['quantity'] * $product->points;

                    $baseDate = Carbon::now();
                    $warrantyGuarantee = SaleDetail::computeWarrantyGuaranteeDates($product, $baseDate);

                    $orderDetails[] = array_merge([
                        'date' => Carbon::now(),
                        'sale_id' => $order->id,
                        'sale_unit_id' => $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                        'price_type' => isset($value['price_type']) ? $value['price_type'] : 'retail',
                    ], $warrantyGuarantee);

                    // Stock deduction only applies to non-service items.
                    // If unit or product_warehouse cannot be resolved, we skip stock adjustment
                    // but still create the sale; this prevents hard failures for legacy/offline data.
                    if (! $isService && $unit) {
                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();
                        } else {
                            $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                        }

                        if ($product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }
                    }
                }

                SaleDetail::insert($orderDetails);

                // Pharmacy: for batch-tracked products in the cart, consume batches FEFO.
                // We keep the bulk insert above for POS speed; only when at least one line is
                // batch-tracked do we query back the persisted details and invoke BatchService.
                $batchService = app(BatchService::class);
                if ($batchService->isSupported()) {
                    $productIds = collect($data)->pluck('product_id')->filter()->unique()->values()->all();
                    $hasBatchTracked = ! empty($productIds) && Product::whereIn('id', $productIds)
                        ->where('is_batch_tracked', true)
                        ->exists();

                    if ($hasBatchTracked) {
                        // Insert order is preserved in auto-increment id order; match by ordinal.
                        $persistedDetails = SaleDetail::where('sale_id', $order->id)
                            ->orderBy('id')
                            ->get();
                        $order->warehouse_id = (int) $request->warehouse_id;
                        $batchService->applyForSaleWithAutoFallback(
                            $order,
                            array_values($data),
                            $persistedDetails
                        );
                    }
                }

                $sale = Sale::findOrFail($order->id);
                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $sale);
                }

                // Optional Stripe per-line metadata coming from modern payment modal / legacy POS
                // NOTE: Saved-card usage has been disabled; we only accept per-transaction tokens.
                $cardTokensByLine = $request->card_tokens_by_line ?? [];

                foreach ($request->payments as $index => $payment) {
                    if (isset($payment['amount']) && $payment['amount'] > 0) {
                        // Prefer per-line account selection; no global account fallback
                        $accountId = $payment['account_id'] ?? null;

                        $originalAmount = $payment['amount'];
                        $paymentAmount = $originalAmount;
                        $changeReturn = 0;

                        // Adjust if overpaid in single-payment mode
                        if (count($request->payments) === 1 && $originalAmount > $request->GrandTotal) {
                            $paymentAmount = $request->GrandTotal;
                            $changeReturn = $originalAmount - $request->GrandTotal;
                        }

                        if ($payment['payment_method_id'] == 1 || $payment['payment_method_id'] == '1') {

                            $helpers  = new helpers();
                            $currency = strtolower($helpers->Get_Currency_Code() ?? 'usd');

                            $client = Client::findOrFail($request->client_id);
                            \Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                            $existing = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();

                            try {

                                // 1️⃣ Ensure Stripe customer (no legacy card "source" – we rely on PaymentMethods / PaymentIntents)
                                if (! $existing) {
                                    $customer = \Stripe\Customer::create([
                                        'email' => $client->email,
                                        'name'  => $client->name,
                                    ]);

                                    $customerStripeId = $customer->id;
                                } else {
                                    $customerStripeId = $existing->customer_stripe_id;
                                }

                                // 2️⃣ Resolve Stripe Payment Method ID (pm_...)
                                // Prefer explicit PaymentMethod id sent from frontend (new POS flow)
                                $paymentMethodId = $payment['payment_method_id_stripe'] ?? null;

                                // If not provided, but we have a card token (legacy token-based flow),
                                // create a PaymentMethod from the token so we can still use PaymentIntents.
                                if (! $paymentMethodId) {
                                    $tokenLine = $cardTokensByLine[$index] ?? ($request->token ?? null);
                                    if ($tokenLine) {
                                        $paymentMethod = \Stripe\PaymentMethod::create([
                                            'type' => 'card',
                                            'card' => [
                                                'token' => $tokenLine,
                                            ],
                                        ]);
                                        $paymentMethodId = $paymentMethod->id;
                                    }
                                }

                                if (! $paymentMethodId) {
                                    throw new \Exception('Stripe payment method is missing');
                                }

                                // 3️⃣ Create & confirm PaymentIntent
                                $intent = \Stripe\PaymentIntent::create([
                                    'amount' => (int) round($paymentAmount * 100),
                                    'currency' => $currency, // usd or mxn
                                    'customer' => $customerStripeId,
                                    'payment_method' => $paymentMethodId,
                                    'confirm' => true,
                                    'automatic_payment_methods' => [
                                        'enabled' => true,
                                        'allow_redirects' => 'never',
                                    ],
                                ]);

                                if ($intent->status !== 'succeeded') {
                                    throw new \Exception('Payment failed');
                                }

                                // Save for DB usage
                                $stripeChargeId = $intent->latest_charge;

                            } catch (\Stripe\Exception\CardException $e) {

                                // ✅ Correct: Stripe card error → bubble up
                                throw new \Exception($e->getError()->message);

                            } catch (\Throwable $e) {

                                // ✅ Correct: Throwable has NO getError()
                                throw new \Exception($e->getMessage());
                            }
                        }


                        $paymentSale = PaymentSale::create([
                            'sale_id' => $order->id,
                            'account_id' => $accountId,
                            'Ref' => app('App\\Http\\Controllers\\PaymentSalesController')->getNumberOrder(),
                            'date' => Carbon::now(),
                            'payment_method_id' => $payment['payment_method_id'],
                            'montant' => $paymentAmount,
                            'change' => $changeReturn,
                            'notes' => $request['payment_note'] ?? null,
                            'user_id' => Auth::user()->id,
                        ]);



                        if ($payment['payment_method_id'] == 1 || $payment['payment_method_id'] == '1') {
                            PaymentWithCreditCard::create([
                                'customer_id' => $request->client_id,
                                'payment_id' => $paymentSale->id,
                                'customer_stripe_id' => $customerStripeId,
                                'charge_id' => $stripeChargeId,
                            ]);
                        }

                        if ($accountId) {
                            $account = Account::find($accountId);
                            if ($account) {
                                $account->update(['balance' => $account->balance + $paymentAmount]);
                            }
                        }
                    }
                }

                $totalPaidAdjusted = min($totalPaid, $request->GrandTotal);
                $due = $order->GrandTotal - $totalPaidAdjusted;

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
                    'paid_amount' => $totalPaidAdjusted,
                    'payment_statut' => $due <= 0 ? 'paid' : ($due < $order->GrandTotal ? 'partial' : 'unpaid'),
                ]);

                // If converting from a draft, remove the draft after successful sale creation
                if ($request->filled('draft_sale_id')) {
                    try {
                        $draft = DraftSale::find($request->draft_sale_id);
                        if ($draft) {
                            $draft->details()->delete();
                            $draft->update(['deleted_at' => Carbon::now()]);
                        }
                    } catch (\Throwable $e) {
                        // best-effort cleanup; do not fail the sale creation on draft cleanup issues
                    }
                }

                return $order;

            }, 10);

        } catch (\Throwable $e) {
            // If a concurrent request created the same sale with the same UUID, the unique
            // constraint on sale_uuid will trigger here. In that case, treat this as a
            // successful, idempotent result by returning the already‑created sale instead
            // of an error. This avoids duplicate stock deduction and payment creation.
            if ($saleUuid) {
                try {
                    $existing = Sale::where('sale_uuid', $saleUuid)->first();
                    if ($existing) {
                        return response()->json([
                            'success' => true,
                            'id' => $existing->id,
                            'qbo_sync' => 'skipped',
                            'updated_stock' => [],
                            'server_time' => now()->toIso8601String(),
                        ], 200);
                    }
                } catch (\Throwable $lookupError) {
                    // Fallback to original behavior below if lookup fails for any reason.
                }
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }


        // ---------- AFTER COMMIT: QBO sync (best effort, non-blocking) ----------
        $qboSync = 'skipped';
        try {
            if (class_exists(\App\Jobs\SyncSaleToQuickBooks::class)) {
                \App\Jobs\SyncSaleToQuickBooks::dispatch($sale->id)->afterCommit();
                $qboSync = 'queued';
            } else {
                $sale->load(['saleDetails.product', 'client']);
                /** @var \App\Services\QuickBooksService $qb */
                $qb = app(\App\Services\QuickBooksService::class);

                $res = ! empty($sale->quickbooks_invoice_id)
                    ? $qb->updateInvoice($sale)
                    : $qb->createInvoice($sale);

                if (! ($res['ok'] ?? false)) {
                    \Log::warning('QBO POS sync failed', [
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
                    $sale->update([
                        'quickbooks_invoice_id' => $sale->quickbooks_invoice_id ?: ($res['id'] ?? null),
                        'quickbooks_realm_id' => $res['realm'] ?? ($sale->quickbooks_realm_id ?? null),
                        'quickbooks_synced_at' => now(),
                        'quickbooks_sync_error' => null,
                    ]);
                    $qboSync = 'ok';
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('QuickBooks sync failed (non-blocking on POS): '.$e->getMessage(), [
                'sale_id' => $sale->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $qboSync = 'failed';
        }

        $emailError = null;
        $smsError = null;

        try {
            if ($request->send_email) {
                $this->Send_Email($sale->id);
            }
        } catch (\Throwable $e) {
            \Log::error('Send_Email failed: '.$e->getMessage());
            $emailError = $e->getMessage();
        }

        try {
            if ($request->send_sms) {
                $this->Send_SMS($sale->id);
            }
        } catch (\Throwable $e) {
            \Log::error('Send_SMS failed: '.$e->getMessage());
            $smsError = $e->getMessage();
        }

        // ---------- Collect updated stock for sold lines ----------
        // After the sale transaction has committed we query fresh stock for
        // the items that were just sold so the POS UI can patch its in-memory
        // products list without re-fetching the entire catalog.
        $updatedStock = [];
        try {
            $warehouseId = $sale->warehouse_id;
            $seen = [];
            foreach ((array) $request->details as $line) {
                if (!is_array($line) || !isset($line['product_id'])) continue;
                $pid = $line['product_id'];
                $vid = isset($line['product_variant_id']) ? $line['product_variant_id'] : null;
                $key = $pid . ':' . ($vid === null || $vid === '' ? 'null' : $vid);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                $q = product_warehouse::where('warehouse_id', $warehouseId)
                    ->where('product_id', $pid)
                    ->whereNull('deleted_at')
                    ->with('product.unitSale');
                if ($vid !== null && $vid !== '') {
                    $q->where('product_variant_id', $vid);
                } else {
                    $q->whereNull('product_variant_id');
                }
                $pw = $q->first();
                $formatted = $this->formatStockRow($pw);
                if ($formatted !== null) {
                    $updatedStock[] = $formatted;
                }
            }
        } catch (\Throwable $e) {
            $updatedStock = [];
        }

        $response = [
            'success' => true,
            'id' => $sale->id,
            'qbo_sync' => $qboSync,
            'updated_stock' => $updatedStock,
            'server_time' => now()->toIso8601String(),
        ];

        if ($emailError) {
            $response['email_error'] = $emailError;
        }

        if ($smsError) {
            $response['sms_error'] = $smsError;
        }

        return response()->json($response, 200);
    }

    public function Send_Email($id)
    {

        // sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($id);

        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        // settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        // the custom msg of sale
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
        $invoice_url = url('/api/sale_pdf/'.$id.'?'.$random_number);
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

    public function Send_SMS($id)
    {

        // sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($id);

        $helpers = new helpers;
        $currency = $helpers->Get_Currency();

        // settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

        // the custom msg of sale
        $smsMessage = SMSMessage::getForLocale('sale');

        if ($smsMessage) {
            $message_text = $smsMessage->text;
        } else {
            $message_text = '';
        }

        // Tags
        $random_number = Str::random(10);
        $invoice_url = url('/api/sale_pdf/'.$id.'?'.$random_number);
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
                echo 'Response body: '.$smsResponse;
            } catch (Throwable $apiException) {
                echo 'HTTP Code: '.$apiException->getCode()."\n";
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
                $response = $client->post($url, [
                    'json' => $payload,
                ]);

                $result = json_decode($response->getBody(), true);

                return response()->json($result);
            } catch (\Exception $e) {
                Log::error('Termii SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }

        } elseif ($default_sms_gateway->title == 'custom') {
            try {
                (new \App\Services\CustomSmsGateway)->send($receiverNumber, $message_text);
            } catch (\Throwable $e) {
                Log::error('Custom SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => true]);

    }

    // ------------- get_draft_sales -----------\\

    public function get_draft_sales(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
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
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = 'id';
        $dir = 'DESC';
        $helpers = new helpers;

        $data = [];

        // Check If User Has Permission View  All Records
        $draft_sales = DraftSale::with('client', 'warehouse', 'user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        if (! $is_all_warehouses) {
            $draft_sales->whereIn('warehouse_id', $warehouse_ids);
        }

        $totalRows = $draft_sales->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $drafts = $draft_sales->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($drafts as $draft) {

            $item['id'] = $draft['id'];
            $item['date'] = $draft['date'];
            $item['Ref'] = $draft['Ref'];
            $item['warehouse_name'] = $draft['warehouse']['name'];
            $item['client_name'] = $draft['client']['name'];
            $item['GrandTotal'] = number_format($draft['GrandTotal'], 2, '.', '');
            $item['actions'] = '';

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'draft_sales' => $data,
        ]);
    }

    // ------------ Create Draft --------------\\

    public function CreateDraft(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers;
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check

            // If draft_sale_id present, update existing draft; otherwise create new
            if ($request->filled('draft_sale_id')) {
                $order = DraftSale::whereNull('deleted_at')->findOrFail($request->draft_sale_id);
               
                // Update header
                $order->update([
                    'date' => Carbon::now(),
                    'client_id' => $request->client_id,
                    'warehouse_id' => $request->warehouse_id,
                    'tax_rate' => $request->tax_rate,
                    'TaxNet' => $request->TaxNet,
                    'discount' => $request->discount,
                    // Ensure discount_Method is saved correctly: '1' for percentage, '2' for fixed
                    'discount_Method' => $request->has('discount_Method') ? (string) $request->discount_Method : '2',
                    'shipping' => $request->shipping,
                    'GrandTotal' => $request->GrandTotal,
                    'user_id' => Auth::user()->id,
                ]);

                // Replace details
                $order->details()->delete();
                $data = $request['details'];
                $orderDetails = [];
                foreach ($data as $key => $value) {
                    $orderDetails[] = [
                        'date' => Carbon::now(),
                        'draft_sale_id' => $order->id,
                        'sale_unit_id' => $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                        'price_type' => isset($value['price_type']) ? $value['price_type'] : 'retail',
                    ];
                }
                if (! empty($orderDetails)) {
                    DraftSaleDetail::insert($orderDetails);
                }
            } else {
                $order = new DraftSale;
                $order->date = Carbon::now();
                $order->Ref = $this->getNumberOrderDraft();
                $order->client_id = $request->client_id;
                $order->warehouse_id = $request->warehouse_id;
                $order->tax_rate = $request->tax_rate;
                $order->TaxNet = $request->TaxNet;
                $order->discount = $request->discount;
                // Ensure discount_Method is saved correctly: '1' for percentage, '2' for fixed
                $order->discount_Method = $request->has('discount_Method') ? (string) $request->discount_Method : '2';
                $order->shipping = $request->shipping;
                $order->GrandTotal = $request->GrandTotal;
                $order->user_id = Auth::user()->id;
                $order->save();

                $data = $request['details'];
                $orderDetails = [];
                foreach ($data as $key => $value) {
                    $orderDetails[] = [
                        'date' => Carbon::now(),
                        'draft_sale_id' => $order->id,
                        'sale_unit_id' => $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                        'price_type' => isset($value['price_type']) ? $value['price_type'] : 'retail',
                    ];
                }
                if (! empty($orderDetails)) {
                    DraftSaleDetail::insert($orderDetails);
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------ remove_draft_sale -------------\\

    public function remove_draft_sale(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        \DB::transaction(function () use ($id, $request) {

            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $draft = DraftSale::findOrFail($id);

            $draft->details()->delete();
            $draft->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------ submit_sale_from_draft --------------\\

    public function submit_sale_from_draft(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payment.amount' => 'required',
        ]);

        $draft = DraftSale::findOrFail($request['draft_sale_id']);
        if ($draft) {

            $sale = \DB::transaction(function () use ($request, $draft) {
                $helpers = new helpers;
                $user = Auth::user();
                // New way: Check user's record_view field (user-level boolean)
                // Backward compatibility: If record_view is null, fall back to role permission check
                $order = new Sale;

                $order->is_pos = 1;
                $order->date = Carbon::now();
                $order->time = now()->toTimeString();
                $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
                $order->client_id = $request->client_id;
                $order->warehouse_id = $request->warehouse_id;
                $order->tax_rate = $request->tax_rate;
                $order->TaxNet = $request->TaxNet;
                $order->discount = $request->discount;
                // Ensure discount_Method is saved correctly: '1' for percentage, '2' for fixed
                $order->discount_Method = $request->has('discount_Method') ? (string) $request->discount_Method : '2';
                $order->shipping = $request->shipping;
                $order->GrandTotal = $request->GrandTotal;
                $order->notes = $request->notes;
                $order->statut = 'completed';
                $order->payment_statut = 'unpaid';
                $order->user_id = Auth::user()->id;

                $order->save();

                $data = $request['details'];
                $total_points_earned = 0;
                foreach ($data as $key => $value) {

                    $product = Product::find($value['product_id']);
                    $unit = Unit::where('id', $value['sale_unit_id'])->first();
                    $total_points_earned += $value['quantity'] * $product->points;

                    $baseDate = Carbon::now();
                    $warrantyGuarantee = SaleDetail::computeWarrantyGuaranteeDates($product, $baseDate);

                    $orderDetails[] = array_merge([
                        'date' => Carbon::now(),
                        'sale_id' => $order->id,
                        'sale_unit_id' => $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                        'price_type' => isset($value['price_type']) ? $value['price_type'] : 'retail',
                    ], $warrantyGuarantee);

                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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

                SaleDetail::insert($orderDetails);

                $sale = Sale::findOrFail($order->id);
               
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

                    if ($request['amount'] > 0) {
                        if ($request->payment['payment_method_id'] == 1 || $request->payment['payment_method_id'] == '1') {

                            $Client = Client::whereId($request->client_id)->first();
                            Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                            // Always require a fresh card token for each transaction.
                            if (! $request->token) {
                                throw new \Exception('Stripe card token is missing');
                            }

                            // Optionally reuse the Stripe customer, but never reuse a saved card without a new token.
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();

                            if (! $PaymentWithCreditCard) {
                                // Create a new customer and charge with the provided card token
                                $customer = \Stripe\Customer::create([
                                    'source' => $request->token,
                                    'email' => $Client->email,
                                    'name' => $Client->name,
                                ]);

                                $charge = \Stripe\Charge::create([
                                    'amount' => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer->id,
                                ]);

                                $PaymentCard['customer_stripe_id'] = $customer->id;
                            } else {
                                // Reuse the Stripe customer but attach/charge using the new card token
                                $customer_id = $PaymentWithCreditCard->customer_stripe_id;

                                // Attach new card source to existing customer
                                $card = \Stripe\Customer::createSource(
                                    $customer_id,
                                    [
                                        'source' => $request->token,
                                    ]
                                );

                                $charge = \Stripe\Charge::create([
                                    'amount' => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source' => $card->id,
                                ]);

                                $PaymentCard['customer_stripe_id'] = $customer_id;
                            }

                            $PaymentSale = new PaymentSale;
                            $PaymentSale->sale_id = $order->id;
                            $PaymentSale->Ref = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                            $PaymentSale->date = Carbon::now();
                            $PaymentSale->payment_method_id = $request->payment['payment_method_id'];
                            $PaymentSale->montant = $request['amount'];
                            $PaymentSale->change = $request['change'];
                            $PaymentSale->notes = $request->payment['notes'];
                            $PaymentSale->user_id = Auth::user()->id;
                            $PaymentSale->account_id = $request->payment['account_id'] ? $request->payment['account_id'] : null;

                            $PaymentSale->save();

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

                            $PaymentCard['customer_id'] = $request->client_id;
                            $PaymentCard['payment_id'] = $PaymentSale->id;
                            $PaymentCard['charge_id'] = $charge->id;
                            PaymentWithCreditCard::create($PaymentCard);

                            // Paying Method Cash
                        } else {

                            PaymentSale::create([
                                'sale_id' => $order->id,
                                'account_id' => $request->payment['account_id'] ? $request->payment['account_id'] : null,
                                'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                                'date' => Carbon::now(),
                                'payment_method_id' => $request->payment['payment_method_id'],
                                'montant' => $request['amount'],
                                'change' => $request['change'],
                                'notes' => $request->payment['notes'],
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

                    }

                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
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

                $draft->details()->delete();
                $draft->update([
                    'deleted_at' => Carbon::now(),
                ]);

                return $order;

            }, 10);

            // ============================ END TRANSACTION ============================

            // ---------- AFTER COMMIT: QBO sync (best effort, non-blocking) ----------
            $qboSync = 'skipped';
            try {
                if (class_exists(\App\Jobs\SyncSaleToQuickBooks::class)) {
                    \App\Jobs\SyncSaleToQuickBooks::dispatch($sale->id)->afterCommit();
                    $qboSync = 'queued';
                } else {
                    $sale->load(['saleDetails.product', 'client']);
                    /** @var \App\Services\QuickBooksService $qb */
                    $qb = app(\App\Services\QuickBooksService::class);

                    $res = ! empty($sale->quickbooks_invoice_id)
                        ? $qb->updateInvoice($sale)
                        : $qb->createInvoice($sale);

                    if (! ($res['ok'] ?? false)) {
                        \Log::warning('QBO sync failed (submit_sale_from_draft)', [
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
                        $sale->update([
                            'quickbooks_invoice_id' => $sale->quickbooks_invoice_id ?: ($res['id'] ?? null),
                            'quickbooks_realm_id' => $res['realm'] ?? ($sale->quickbooks_realm_id ?? null),
                            'quickbooks_synced_at' => now(),
                            'quickbooks_sync_error' => null,
                        ]);
                        $qboSync = 'ok';
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('QuickBooks sync failed (non-blocking from draft): '.$e->getMessage(), [
                    'sale_id' => $sale->id,
                    'trace' => $e->getTraceAsString(),
                ]);
                $qboSync = 'failed';
            }

            return response()->json([
                'success' => true,
                'id' => $sale->id,
                'qbo_sync' => $qboSync, // ok | queued | failed | skipped
            ]);

        } else {
            return response()->json(['success' => false], 404);
        }

    }

    // --------------------- data_draft_convert_sale ------------------------\\

    public function data_draft_convert_sale(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name', 'phone']);
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);

        $draft_sale_data = DraftSale::with('details.product.unitSale')->where('deleted_at', '=', null)->findOrFail($id);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        if ($draft_sale_data->client_id) {
            $client = Client::where('id', $draft_sale_data->client_id)
                ->whereNull('deleted_at')
                ->first();

            if ($client) {
                $sale['client_id'] = $client->id;
                $client_name = $client->name;

                // ✅ Pass royalty data for frontend usage
                $defaultClient = $client->id;
                $default_client_points = $client->points;
                $default_client_eligible = $client->is_royalty_eligible;
            } else {
                $defaultClient = '';
                $client_name = '';
                $default_client_points = 0;
                $default_client_eligible = false;
            }
        } else {
            $defaultClient = '';
            $client_name = '';
            $default_client_points = 0;
            $default_client_eligible = false;
        }

        if ($draft_sale_data->warehouse_id) {
            if (Warehouse::where('id', $draft_sale_data->warehouse_id)->where('deleted_at', '=', null)->first()) {
                $sale['warehouse_id'] = $draft_sale_data->warehouse_id;
            } else {
                $sale['warehouse_id'] = '';
            }
        } else {
            $sale['warehouse_id'] = '';
        }

        $sale['tax_rate'] = $draft_sale_data->tax_rate;
        $sale['TaxNet'] = $draft_sale_data->TaxNet;
        $sale['discount'] = $draft_sale_data->discount;
        $sale['discount_Method'] = $draft_sale_data->discount_Method ?? '2'; // '1' for percentage, '2' for fixed
        $sale['shipping'] = $draft_sale_data->shipping;
        $GrandTotal = $draft_sale_data->GrandTotal;

        $detail_id = 0;
        foreach ($draft_sale_data['details'] as $detail) {

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
                    ->where('warehouse_id', $draft_sale_data->warehouse_id)
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
                    ->where('deleted_at', '=', null)->where('warehouse_id', $draft_sale_data->warehouse_id)
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
            $data['fix_stock'] = $detail['product']['type'] != 'is_service' ? $stock : '---';
            $data['current'] = $detail['product']['type'] != 'is_service' ? $stock : '---';

            $data['product_type'] = $detail['product']['type'];
            $data['detail_id'] = $detail_id += 1;
            $data['product_id'] = $detail->product_id;
            $data['total'] = $detail->total;
            $data['quantity'] = $detail->quantity;
            $data['qte_copy'] = $detail->quantity;
            $data['etat'] = 'current';
            $data['unitSale'] = $unit ? $unit->ShortName : '';
            $data['sale_unit_id'] = $unit ? $unit->id : '';
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;
            $data['subtotal'] = $detail->total;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['price_type'] = method_exists($detail, 'getAttribute') && $detail->getAttribute('price_type') ? $detail->price_type : 'retail';

            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['Total_price'] = $data['Net_price'] + $data['taxe'];
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['Total_price'] = $data['Net_price'] + $data['taxe'];
            }

            $details[] = $data;
        }

        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'stripe_key' => $stripe_key,
            'brands' => $brands,
            'warehouse_id' => $sale['warehouse_id'],
            'client_id' => $sale['client_id'],
            'client_name' => $client_name,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
            'sale' => $sale,
            'GrandTotal' => $GrandTotal,
            'details' => $details,
            'defaultClient' => $defaultClient,
            'default_client_points' => $default_client_points,
            'default_client_eligible' => $default_client_eligible,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
        ]);
    }

    // ------------ Get Products (POS) --------------\\
    //
    // NOTE:
    // Historically this endpoint implemented server‑side pagination
    // (offset/limit based on PosSetting::products_per_page and a `page`
    // query parameter). The POS UI has since been refactored to use a
    // single API call that loads the full filtered list for a warehouse
    // and performs pagination purely on the frontend.
    //
    // To support that, this method now always returns the complete
    // filtered collection for the given warehouse (no offset/limit),
    // while preserving the existing response shape:
    //   { products: [...], totalRows: <int> }
    // `totalRows` is the total number of matching products.

    public function GetProductsByParametre(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $data = [];

        $product_warehouse_query = product_warehouse::where('warehouse_id', $request->warehouse_id)
            ->with('product', 'product.unitSale')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->whereHas('product', function ($q) {
                    $q->where('not_selling', '=', 0);
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
            })

        // Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('category_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('category_id', '=', $request->category_id);
                    });
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('brand_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('brand_id', '=', $request->brand_id);
                    });
                });
            });

        $totalRows = $product_warehouse_query->count();

        $product_warehouse_data = $product_warehouse_query->get();

        foreach ($product_warehouse_data as $product_warehouse) {
            if ($product_warehouse->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $product_warehouse->product_id)
                    ->where('id', $product_warehouse->product_variant_id)
                    ->where('deleted_at', null)
                    ->first();

                $item['product_variant_id'] = $product_warehouse->product_variant_id;
                $item['Variant'] = '['.$productsVariants->name.']'.$product_warehouse['product']->name;
                $item['name'] = '['.$productsVariants->name.']'.$product_warehouse['product']->name;

                $item['code'] = $productsVariants->code;
                $item['barcode'] = $productsVariants->code;

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
            $firstimage = explode(',', $product_warehouse['product']->image);
            $item['image'] = $firstimage[0];

            // Common product flags & meta
            $item['product_type'] = $product_warehouse['product']->type;
            $item['is_imei'] = $product_warehouse['product']->is_imei;
            $item['is_batch_tracked'] = (bool) ($product_warehouse['product']->is_batch_tracked ?? false);
            $item['not_selling'] = $product_warehouse['product']->not_selling;
            $item['hide_from_online_store'] = $product_warehouse['product']->hide_from_online_store ?? 0;

            // Persist tax/discount metadata so the POS can work without per‑click calls
            $item['tax_method'] = $product_warehouse['product']->tax_method;
            $item['tax_percent'] = $product_warehouse['product']->TaxNet;
            $item['discount_method'] = $product_warehouse['product']->discount_method;
            // Legacy camel-cased name used in some existing JS
            $item['discount_Method'] = $product_warehouse['product']->discount_method;
            $item['discount'] = $product_warehouse['product']->discount;

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

            $item['unitSale'] = $product_warehouse['product']['unitSale'] ? $product_warehouse['product']['unitSale']->ShortName : '';
            $item['sale_unit_id'] = $product_warehouse['product']['unitSale'] ? $product_warehouse['product']['unitSale']->id : null;
            $item['qte'] = $product_warehouse['product']->type != 'is_service' ? $product_warehouse->qte : '---';

            // Base (retail) price per sale unit and original/base price (fix_price)
            $item['Unit_price'] = $price;
            $item['fix_price'] = $product_price;

            // --- Compute wholesale price per sale unit (min_price is returned raw from DB) ---
            // For variant products, prefer variant-level wholesale/min prices when available.
            $baseWholesale = null;
            $baseMinPrice = null;

            if ($product_warehouse->product_variant_id && isset($productsVariants)) {
                // Variant wholesale column is `wholesale` (NOT wholesale_price)
                if (isset($productsVariants->wholesale) && $productsVariants->wholesale > 0) {
                    $baseWholesale = $productsVariants->wholesale;
                }
                if (isset($productsVariants->min_price) && $productsVariants->min_price > 0) {
                    $baseMinPrice = $productsVariants->min_price;
                }
            }

            // Fallbacks for non-variant products or when variant values are empty/zero
            if ($baseWholesale === null || $baseWholesale <= 0) {
                if (isset($product_warehouse['product']->wholesale_price) && $product_warehouse['product']->wholesale_price > 0) {
                    $baseWholesale = $product_warehouse['product']->wholesale_price;
                } else {
                    // If no explicit wholesale is configured, use the regular product price
                    $baseWholesale = $product_price;
                }
            }

            if ($baseMinPrice === null || $baseMinPrice <= 0) {
                $baseMinPrice = $product_warehouse['product']->min_price ?? 0;
            }

            $wholesale_product_price = $baseWholesale;

            if ($product_warehouse['product']['unitSale']) {
                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $wholesale_unit_price = $wholesale_product_price / $product_warehouse['product']['unitSale']->operator_value;
                } else {
                    $wholesale_unit_price = $wholesale_product_price * $product_warehouse['product']['unitSale']->operator_value;
                }
            } else {
                $wholesale_unit_price = $wholesale_product_price;
            }

            // ---------------- Discount for base (retail) price ----------------
            if ($product_warehouse['product']->discount !== 0.0) {
                // percent %
                if ($product_warehouse['product']->discount_method == '1') {
                    $discount_price = $price * $product_warehouse['product']->discount / 100;
                    $price_discounted = $price - $discount_price;
                } else {
                    // fixed
                    $discount_price = $product_warehouse['product']->discount;
                    $price_discounted = $price - $product_warehouse['product']->discount;
                }
            } else {
                $discount_price = 0;
                $price_discounted = $price;
            }

            // Expose effective discount amount and meta so frontend can work without extra calls
            $item['DiscountNet'] = $discount_price;

            // ---------------- Tax & Net/Total price (base / retail) ----------------
            if ($product_warehouse['product']->TaxNet !== 0.0) {
                // Exclusive
                if ($product_warehouse['product']->tax_method == '1') {
                    $tax_price = $price_discounted * $product_warehouse['product']->TaxNet / 100;
                    $item['Net_price'] = $price_discounted;
                    $item['tax_price'] = $tax_price;
                    $item['Total_price'] = $price_discounted + $tax_price;

                    // Inclusive
                } else {
                    $tax_price = $price_discounted * $product_warehouse['product']->TaxNet / 100;
                    $item['Total_price'] = $price_discounted;
                    $item['Net_price'] = $price_discounted - $tax_price;
                    $item['tax_price'] = $tax_price;
                }
            } else {
                $tax_price = 0;
                $item['Net_price'] = $price_discounted;
                $item['Total_price'] = $price_discounted;
                $item['tax_price'] = 0;
            }

            // ---------------- Wholesale discount/tax/net ----------------
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
            // Return raw min price from DB without discount/tax/unit conversion
            $item['min_price'] = $baseMinPrice;

            $data[] = $item;
        }

        return response()->json([
            'products' => $data,
            'totalRows' => $totalRows,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // --------------------- Delta: products stock changes -----------\\
    // Returns only the product_warehouse rows whose updated_at is newer
    // than the provided `since` timestamp. Used by the POS UI to keep
    // stock fresh across multiple cashiers without re-downloading the
    // full product list after every sale.
    public function GetProductsChanges(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $warehouseId = $request->warehouse_id;
        $since = $request->since;
        $serverTime = now()->toIso8601String();

        if (!$warehouseId) {
            return response()->json([
                'products' => [],
                'server_time' => $serverTime,
            ]);
        }

        $query = product_warehouse::where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->with('product.unitSale');

        if (!empty($since)) {
            try {
                $query->where('updated_at', '>', $since);
            } catch (\Throwable $e) {
                // Ignore malformed since; fall through to full warehouse read.
            }
        }

        $rows = $query->get();
        $data = [];
        foreach ($rows as $pw) {
            $formatted = $this->formatStockRow($pw);
            if ($formatted !== null) {
                $data[] = $formatted;
            }
        }

        return response()->json([
            'products' => $data,
            'server_time' => $serverTime,
        ]);
    }

    // Shared helper: format a product_warehouse row for stock-only responses.
    // Mirrors the qte / qte_sale computation used by GetProductsByParametre.
    private function formatStockRow($pw)
    {
        if (!$pw) return null;
        $product = $pw->product;
        if (!$product) return null;

        $isService = $product->type === 'is_service';
        $qte = $isService ? '---' : $pw->qte;

        if ($product->unitSale) {
            if ($product->unitSale->operator == '/') {
                $qteSale = $pw->qte * $product->unitSale->operator_value;
            } else {
                $qteSale = $pw->qte / $product->unitSale->operator_value;
            }
        } else {
            $qteSale = $isService ? '---' : $pw->qte;
        }

        return [
            'id' => $pw->product_id,
            'product_variant_id' => $pw->product_variant_id,
            'qte' => $qte,
            'qte_sale' => $qteSale,
        ];
    }

    // --------------------- Get Element POS ------------------------\\

    public function GetELementPos(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name', 'phone']);
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

            if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }

        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);

            if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->whereIn('id', $warehouses_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }
        }

        if ($settings->client_id) {
            $client = Client::where('id', $settings->client_id)
                ->whereNull('deleted_at')
                ->first();

            if ($client) {
                $defaultClient = $client->id;
                $default_client_name = $client->name;
                $default_client_points = $client->points;
                $default_client_eligible = $client->is_royalty_eligible; // ✅ New
            } else {
                $defaultClient = '';
                $default_client_name = '';
                $default_client_points = 0;
                $default_client_eligible = false;
            }
        } else {
            $defaultClient = '';
            $default_client_name = '';
            $default_client_points = 0;
            $default_client_eligible = false;
        }

        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');
        $pos_setting = PosSetting::where('deleted_at', '=', null)->first();
        $products_per_page = $pos_setting ? $pos_setting->products_per_page : 12;
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);
        $languages_available = Language::where('is_active', true)->get(['name', 'locale', 'flag']);

        return response()->json([
            // Company / receipt header info (used for POS receipt + offline printing fallback)
            'setting' => [
                'logo' => $settings ? $settings->logo : null,
                'CompanyName' => $settings ? $settings->CompanyName : null,
                'CompanyAdress' => $settings ? $settings->CompanyAdress : null,
                'email' => $settings ? $settings->email : null,
                'CompanyPhone' => $settings ? $settings->CompanyPhone : null,
                'vat_number' => $settings ? ($settings->vat_number ?? null) : null,
                'company_name_ar' => $settings ? ($settings->company_name_ar ?? null) : null,
                'zatca_enabled' => $settings ? (bool) ($settings->zatca_enabled ?? false) : false,
                // Preferred invoice format for POS printing
                'invoice_format' => $settings && in_array($settings->invoice_format, ['thermal', 'a4'], true)
                    ? $settings->invoice_format
                    : 'thermal',
            ],
            'stripe_key' => $stripe_key,
            'brands' => $brands,
            'defaultWarehouse' => $defaultWarehouse,
            'defaultClient' => $defaultClient,
            'default_client_name' => $default_client_name,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
            'languages_available' => $languages_available,
            'products_per_page' => $products_per_page,
            'default_client_points' => $default_client_points,
            'default_client_eligible' => $default_client_eligible,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
            'default_tax' => $settings->default_tax ?? 0,
            'default_account_id' => $settings->default_account_id ?? null,
            'default_payment_method_id' => $settings->default_payment_method_id ?? null,
            'pos_settings' => $pos_setting,
        ]);
    }

    // ------------- Reference Number Draft -----------\\

    public function getNumberOrderDraft()
    {

        $last = DB::table('draft_sales')->latest('id')->first();

        if ($last) {
            $item = (string) ($last->Ref ?? '');
            // Accept DR_1111 or DR-1111 (and similar) without assuming array indexes.
            if (preg_match('/^([A-Za-z]+)[_-]?(\d+)$/', $item, $m)) {
                $prefix = $m[1];
                $inMsg = ((int) $m[2]) + 1;
                $code = $prefix.'_'.$inMsg;
            } else {
                $code = 'DR_1111';
            }
        } else {
            $code = 'DR_1111';
        }

        return $code;
    }
}