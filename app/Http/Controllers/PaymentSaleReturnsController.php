<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReturn;
use App\Models\Account;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PaymentSaleReturns;
use App\Models\Role;
use App\Models\SaleReturn;
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

class PaymentSaleReturnsController extends BaseController
{
    // ------------- Get All Payment Sale Returns --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_payments_Sale_Returns', PaymentSaleReturns::class);

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
        $columns = [0 => 'Ref', 1 => 'sale_return_id', 2 => 'payment_method_id'];
        $data = [];

        // Check If User Has Permission View  All Records
        $Payments = PaymentSaleReturns::with('SaleReturn', 'SaleReturn.client', 'account', 'user')
            ->where('deleted_at', '=', null)
            ->whereBetween('date', [$request->from, $request->to])
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        if (! $is_all_warehouses) {
            $Payments->whereHas('SaleReturn', function ($q) use ($warehouse_ids) {
                $q->whereIn('warehouse_id', $warehouse_ids);
            });
        }

        // Multiple Filter
            $Payments->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->whereHas('SaleReturn.client', function ($q) use ($request) {
                        $q->where('id', '=', $request->client_id);
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
                            return $query->whereHas('SaleReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('payment_method', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn.client', function ($q) use ($request) {
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
            $item['Ref_return'] = $Payment['SaleReturn']->Ref;
            $item['sale_return_id'] = $Payment['SaleReturn']->id;
            $item['client_name'] = $Payment['SaleReturn']['client']->name;
            $item['payment_method'] = $Payment['payment_method']->name;
            $item['montant'] = $Payment->montant;
            $item['account_name'] = $Payment['account'] ? $Payment['account']->account_name : '---';
            $item['user_name'] = $Payment['user'] ? $Payment['user']->username : '---';
            $data[] = $item;
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $sale_returns = SaleReturn::get(['Ref', 'id']);
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $data,
            'sale_returns' => $sale_returns,
            'clients' => $clients,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ----------- Store New Payment Sale Return --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentSaleReturns::class);

        if ($request['montant'] > 0) {
            \DB::transaction(function () use ($request) {
                $user = Auth::user();
                // New way: Check user's record_view field (user-level boolean)
                // Backward compatibility: If record_view is null, fall back to role permission check
                $view_records = $user->hasRecordView();
                $SaleReturn = SaleReturn::findOrFail($request['sale_return_id']);

                // Check If User Has Permission view All Records
                if (! $view_records) {
                    // Check If User->id === Sale Return->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
                }

                $total_paid = $SaleReturn->paid_amount + $request['montant'];
                $due = $SaleReturn->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } elseif ($due !== $SaleReturn->GrandTotal) {
                    $payment_statut = 'partial';
                } elseif ($due === $SaleReturn->GrandTotal) {
                    $payment_statut = 'unpaid';
                }

                PaymentSaleReturns::create([
                    'sale_return_id' => $request['sale_return_id'],
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
                        'balance' => $account->balance - $request['montant'],
                    ]);
                }

                $SaleReturn->update([
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

    // ----------- Update Payment Sale Return --------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', PaymentSaleReturns::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $payment = PaymentSaleReturns::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $SaleReturn = SaleReturn::find($payment->sale_return_id);
            $old_total_paid = $SaleReturn->paid_amount - $payment->montant;
            $new_total_paid = $old_total_paid + $request['montant'];
            $due = $SaleReturn->GrandTotal - $new_total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } elseif ($due !== $SaleReturn->GrandTotal) {
                $payment_statut = 'partial';
            } elseif ($due === $SaleReturn->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            // delete old balance
            $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance + $payment->montant,
                ]);
            }

            $payment->update([
                'date' => $request['date'],
                'account_id' => $request['account_id'] ? $request['account_id'] : null,
                'payment_method_id' => $request['payment_method_id'],
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
                    'balance' => $new_account->balance - $request['montant'],
                ]);
            }

            $SaleReturn->update([
                'paid_amount' => $new_total_paid,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Update successfully'], 200);
    }

    // ----------- Remove Payment Sale Return --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PaymentSaleReturns::class);

        \DB::transaction(function () use ($id, $request) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $payment = PaymentSaleReturns::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $SaleReturn = SaleReturn::find($payment->sale_return_id);
            $total_paid = $SaleReturn->paid_amount - $payment->montant;
            $due = $SaleReturn->GrandTotal - $total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } elseif ($due !== $SaleReturn->GrandTotal) {
                $payment_statut = 'partial';
            } elseif ($due === $SaleReturn->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            PaymentSaleReturns::whereId($id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance + $payment->montant,
                ]);
            }

            $SaleReturn->update([
                'paid_amount' => $total_paid,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Delete successfully'], 200);

    }

    // ----------- Number Order Payment Sale Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('payment_sale_returns')->latest('id')->first();

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

    // ------------- Send Payment Sale Return on Email -----------\\

    public function SendEmail(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentSaleReturns::class);

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

    // ----------- Payment Sale Return PDF --------------\\

    public function payment_return(Request $request, $id)
    {

        $payment = PaymentSaleReturns::with('SaleReturn', 'SaleReturn.client')->findOrFail($id);

        $payment_data['return_Ref'] = $payment['SaleReturn']->Ref;
        $payment_data['client_name'] = $payment['SaleReturn']['client']->name;
        $payment_data['client_phone'] = $payment['SaleReturn']['client']->phone;
        $payment_data['client_adr'] = $payment['SaleReturn']['client']->adresse;
        $payment_data['client_email'] = $payment['SaleReturn']['client']->email;
        $payment_data['montant'] = $payment->montant;
        $payment_data['Ref'] = $payment->Ref;
        $payment_data['date'] = $payment->date;
        $payment_data['payment_method'] = $payment['payment_method']->name;

        $helpers = new helpers;
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.Payment_Sale_Return', [
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

        return $pdf->download('Payment_Sale_Return.pdf');

    }

    // -------------------Sms Notifications -----------------\\
    public function Send_SMS(Request $request)
    {
        $payment = PaymentSaleReturns::with('SaleReturn', 'SaleReturn.client')->findOrFail($request->id);
        $settings = Setting::where('deleted_at', '=', null)->first();
        $gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

        $url = url('/api/payment_return_sale_pdf/'.$request->id);
        $receiverNumber = $payment['SaleReturn']['client']->phone;
        $message = 'Dear'.' '.$payment['SaleReturn']['client']->name." \n We are contacting you in regard to a Payment #".$payment['SaleReturn']->Ref.' '.$url.' '."that has been created on your account. \n We look forward to conducting future business with you.";

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
