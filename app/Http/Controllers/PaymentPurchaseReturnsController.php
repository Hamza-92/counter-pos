<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReturn;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\PaymentPurchaseReturns;
use App\Models\Provider;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Setting;
use App\Models\sms_gateway;
use App\utils\helpers;
use ArPHP\I18N\Arabic;
use Carbon\Carbon;
use DB;
use GuzzleHttp\Client as Client_termi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use PDF;
use Twilio\Rest\Client as Client_Twilio;

class PaymentPurchaseReturnsController extends BaseController
{
    // ------------- Get All Payment Purchase Returns --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_payments_purchase_Return', PaymentPurchaseReturns::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        
        $warehouse_ids = [];
        $is_all_warehouses = $user->is_all_warehouses;
        // If the user is restricted, fetch their assigned warehouse IDs once and reuse below.
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }

        // Filter fields With Params to retriever
        $param = [0 => 'like', 1 => '=', 2 => '='];
        $columns = [0 => 'Ref', 1 => 'purchase_return_id', 2 => 'payment_method_id'];
        $data = [];

        // Check If User Has Permission View  All Records
        $Payments = PaymentPurchaseReturns::with('account', 'PurchaseReturn', 'PurchaseReturn.provider', 'user')
            ->where('deleted_at', '=', null)
            ->whereBetween('date', [$request->from, $request->to])
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        if (! $is_all_warehouses) {
            $Payments->whereHas('PurchaseReturn', function ($q) use ($warehouse_ids) {
                $q->whereIn('warehouse_id', $warehouse_ids);
            });
        }

        // Multiple Filter
            $Payments->where(function ($query) use ($request) {
                return $query->when($request->filled('provider_id'), function ($query) use ($request) {
                    return $query->whereHas('PurchaseReturn.provider', function ($q) use ($request) {
                        $q->where('id', '=', $request->provider_id);
                    });
                });
            });
        $Filtred = $helpers->filter($Payments, $columns, $param, $request)

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('payment_method', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $Payments = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Payments as $Payment) {

            $item['date'] = $Payment->date;
            $item['Ref'] = $Payment->Ref;
            $item['Ref_return'] = $Payment['PurchaseReturn']->Ref;
            $item['purchase_return_id'] = $Payment['PurchaseReturn']->id;
            $item['provider_name'] = $Payment['PurchaseReturn']['provider']->name;
            $item['payment_method'] = $Payment['payment_method']->name;
            $item['montant'] = $Payment->montant;
            $item['account_name'] = $Payment['account'] ? $Payment['account']->account_name : '---';
            $item['user_name'] = $Payment['user'] ? $Payment['user']->username : '---';
            $data[] = $item;
        }

        $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);
        $purchase_returns = PurchaseReturn::get(['Ref', 'id']);
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $data,
            'purchase_returns' => $purchase_returns,
            'suppliers' => $suppliers,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ----------- Store New Payment Purchase Return --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentPurchaseReturns::class);

        if ($request['montant'] > 0) {
            \DB::transaction(function () use ($request) {
                $user = Auth::user();
                // New way: Check user's record_view field (user-level boolean)
                // Backward compatibility: If record_view is null, fall back to role permission check
                $view_records = $user->hasRecordView();
                $PurchaseReturn = PurchaseReturn::findOrFail($request['purchase_return_id']);

                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === purchase return->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $PurchaseReturn);
                }

                $total_paid = $PurchaseReturn->paid_amount + $request['montant'];
                $due = $PurchaseReturn->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } elseif ($due !== $PurchaseReturn->GrandTotal) {
                    $payment_statut = 'partial';
                } elseif ($due === $PurchaseReturn->GrandTotal) {
                    $payment_statut = 'unpaid';
                }

                PaymentPurchaseReturns::create([
                    'purchase_return_id' => $request['purchase_return_id'],
                    'account_id' => $request['account_id'] ? $request['account_id'] : null,
                    'Ref' => $this->getNumberOrder(),
                    'date' => $request['date'],
                    'payment_method_id' => $request['payment_method_id'],
                    'montant' => $request['montant'],
                    'change' => $request['change'],
                    'notes' => $request['notes'],
                    'user_id' => Auth::user()->id,
                ]);

                $account = Account::where('id', $request['account_id'])->exists();

                if ($account) {
                    // Account exists, perform the update
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance + $request['montant'],
                    ]);
                }

                $PurchaseReturn->update([
                    'paid_amount' => $total_paid,
                    'payment_statut' => $payment_statut,
                ]);

            }, 10);
        }

        return response()->json(['success' => true, 'message' => 'Payment Create successfully'], 200);
    }

    // ------------ function show -----------\\

    public function show($id)
    {
        //

    }

    // ----------- Update Payment Purchase Return --------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', PaymentPurchaseReturns::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $payment = PaymentPurchaseReturns::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $PurchaseReturn = PurchaseReturn::find($payment->purchase_return_id);
            $old_total_paid = $PurchaseReturn->paid_amount - $payment->montant;
            $new_total_paid = $old_total_paid + $request['montant'];
            $due = $PurchaseReturn->GrandTotal - $new_total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } elseif ($due !== $PurchaseReturn->GrandTotal) {
                $payment_statut = 'partial';
            } elseif ($due === $PurchaseReturn->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            // delete old balance
            $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance - $payment->montant,
                ]);
            }

            $payment->update([
                'date' => $request['date'],
                'payment_method_id' => $request['payment_method_id'],
                'account_id' => $request['account_id'] ? $request['account_id'] : null,
                'montant' => $request['montant'],
                'change' => $request['change'],
                'notes' => $request['notes'],
            ]);

            // update new account

            $new_account = Account::where('id', $request['account_id'])->exists();

            if ($new_account) {
                // Account exists, perform the update
                $new_account = Account::find($request['account_id']);
                $new_account->update([
                    'balance' => $new_account->balance + $request['montant'],
                ]);
            }

            $PurchaseReturn->update([
                'paid_amount' => $new_total_paid,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Update successfully'], 200);
    }

    // ----------- Remove Payment Purchase Return --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PaymentPurchaseReturns::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $payment = PaymentPurchaseReturns::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $PurchaseReturn = PurchaseReturn::find($payment->purchase_return_id);
            $total_paid = $PurchaseReturn->paid_amount - $payment->montant;
            $due = $PurchaseReturn->GrandTotal - $total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } elseif ($due !== $PurchaseReturn->GrandTotal) {
                $payment_statut = 'partial';
            } elseif ($due === $PurchaseReturn->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            PaymentPurchaseReturns::whereId($id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance - $payment->montant,
                ]);
            }

            $PurchaseReturn->update([
                'paid_amount' => $total_paid,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Delete successfully'], 200);
    }

    // ------------- Send Payment Purchase Return To Email -----------\\

    public function SendEmail(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentPurchaseReturns::class);

        $payment['id'] = $request->id;
        $payment['Ref'] = $request->Ref;
        $settings = Setting::where('deleted_at', '=', null)->first();
        $payment['company_name'] = $settings->CompanyName;

        $pdf = $this->payment_return($request, $payment['id']);
        $this->Set_config_mail(); // Set_config_mail => BaseController
        Mail::to($request->to)->send(new PaymentReturn($payment, $pdf));

        return response()->json(['message' => 'Email sent successfully'], 200);
        // return $mail;
    }

    // ----------- Number Order Payment Purchase Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('payment_purchase_returns')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0].'_'.$inMsg;

        } else {
            $code = 'INV/RT_1111';
        }

        return $code;
    }

    // ----------- Payment Purchase Return PDF --------------\\

    public function payment_return(Request $request, $id)
    {
        $payment = PaymentPurchaseReturns::with('PurchaseReturn', 'PurchaseReturn.provider')->findOrFail($id);

        $payment_data['return_Ref'] = $payment['PurchaseReturn']->Ref;
        $payment_data['supplier_name'] = $payment['PurchaseReturn']['provider']->name;
        $payment_data['supplier_phone'] = $payment['PurchaseReturn']['provider']->phone;
        $payment_data['supplier_adr'] = $payment['PurchaseReturn']['provider']->adresse;
        $payment_data['supplier_email'] = $payment['PurchaseReturn']['provider']->email;
        $payment_data['montant'] = $payment->montant;
        $payment_data['Ref'] = $payment->Ref;
        $payment_data['date'] = $payment->date;
        $payment_data['payment_method'] = $payment['payment_method']->name;

        $helpers = new helpers;
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.Payment_Purchase_Return', [
            'symbol' => $symbol,
            'setting' => $settings,
            'payment' => $payment_data,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);

        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Payment_Purchase_Return.pdf');

    }

    // -------------------Sms Notifications -----------------\\
    public function Send_SMS(Request $request)
    {
        $payment = PaymentPurchaseReturns::with('PurchaseReturn', 'PurchaseReturn.provider')->findOrFail($request->id);
        $settings = Setting::where('deleted_at', '=', null)->first();
        $gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

        $url = url('/api/payment_return_purchase_pdf/'.$request->id);
        $receiverNumber = $payment['PurchaseReturn']['provider']->phone;
        $message = 'Dear'.' '.$payment['PurchaseReturn']['provider']->name." \n We are contacting you in regard to a Payment #".$payment['PurchaseReturn']->Ref.' '.$url.' '."that has been created on your account. \n We look forward to conducting future business with you.";

        // twilio
        if ($gateway->title == 'twilio') {
            try {

                $account_sid = env('TWILIO_SID');
                $auth_token = env('TWILIO_TOKEN');
                $twilio_number = env('TWILIO_FROM');

                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number,
                    'body' => $message]);

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
        // custom
        elseif ($default_sms_gateway->title == 'custom') {
            try {
                (new \App\Services\CustomSmsGateway)->send($receiverNumber, $message_text);

                return response()->json(['success' => true]);
            } catch (\Throwable $e) {
                Log::error('Custom SMS Error: '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

    }
}
