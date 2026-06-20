<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\PaymentPurchase;
use App\Models\PaymentPurchaseReturns;
use App\Models\Provider;
use App\Models\ProviderPaymentOpeningBalance;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Setting;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProvidersController extends BaseController
{
    // ----------- Get ALL Suppliers-------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Provider::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        // Filter fields With Params to retrieve
        $columns = [0 => 'name', 1 => 'code', 2 => 'phone', 3 => 'email'];
        $param = [0 => 'like', 1 => 'like', 2 => 'like', 3 => 'like'];
        $data = [];

        $providers = Provider::where('deleted_at', '=', null);

        // Multiple Filter
        $Filtred = $helpers->filter($providers, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('code', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $providers = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($providers as $provider) {

            $item['total_amount'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['due'] = $item['total_amount'] - $item['total_paid'];

            $item['total_amount_return'] = DB::table('purchase_returns')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('purchase_returns')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['id'] = $provider->id;
            $item['name'] = $provider->name;
            $item['phone'] = $provider->phone;
            $item['tax_number'] = $provider->tax_number;
            $item['code'] = $provider->code;
            $item['email'] = $provider->email;
            $item['country'] = $provider->country;
            $item['city'] = $provider->city;
            $item['adresse'] = $provider->adresse;
            $data[] = $item;
        }

        $company_info = Setting::where('deleted_at', '=', null)->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

        return response()->json([
            'providers' => $data,
            'company_info' => $company_info,
            'totalRows' => $totalRows,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ----------- Store new Supplier -------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Provider::class);

        request()->validate([
            'name' => 'required',
        ]);
        $provider = Provider::create([
            'name' => $request['name'],
            'code' => $this->getNumberOrder(),
            'adresse' => $request['adresse'],
            'phone' => $request['phone'],
            'email' => $request['email'],
            'country' => $request['country'],
            'city' => $request['city'],
            'tax_number' => $request['tax_number'],
            'opening_balance' => $request['opening_balance'] ?? 0,
            'credit_limit' => $request['credit_limit'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'id' => $provider->id,
            'provider' => $provider
        ]);

    }

    // ------------ function show -----------\\

    public function show($id)
    {
        $this->authorizeForUser(request()->user('api'), 'view', Provider::class);

        $provider = Provider::where('deleted_at', '=', null)->findOrFail($id);

        return response()->json([
            'provider' => $provider,
        ]);

    }

    // ----------- Update Supplier-------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Provider::class);

        request()->validate([
            'name' => 'required',
        ]);

        Provider::whereId($id)->update([
            'name' => $request['name'],
            'adresse' => $request['adresse'],
            'phone' => $request['phone'],
            'email' => $request['email'],
            'country' => $request['country'],
            'city' => $request['city'],
            'tax_number' => $request['tax_number'],
            'credit_limit' => $request->input('credit_limit', 0),
        ]);

        return response()->json(['success' => true]);

    }

    // ----------- Remdeleteove Provider-------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Provider::class);

        Provider::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);

    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Provider::class);

        $selectedIds = $request->selectedIds;
        foreach ($selectedIds as $Provider_id) {
            Provider::whereId($Provider_id)->update([
                'deleted_at' => Carbon::now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ----------- get Number Order Of Suppliers-------\\

    public function getNumberOrder()
    {

        $last = DB::table('providers')->latest('id')->first();

        if ($last) {
            $code = $last->code + 1;
        } else {
            $code = 1;
        }

        return $code;
    }

    // ----------- get Ref Order Of Supplier Opening Balance Payments -------\\

    public function getOpeningBalancePaymentNumberOrder()
    {
        $last = DB::table('provider_opening_balance_payments')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            $lastPart = end($nwMsg);
            $prefix = implode('_', array_slice($nwMsg, 0, -1));
            $inMsg = (int) $lastPart + 1;
            $code = $prefix . '_' . $inMsg;
        } else {
            $code = 'SUP_OB_PAY_1111';
        }

        return $code;
    }

    public function import(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Suppliers_import', Provider::class);

        // 1) File-level validation
        $v = Validator::make($request->all(), [
            'suppliers' => 'required|file|mimes:xls,xlsx|max:20480', // 20MB
        ]);
        if ($v->fails()) {
            return response()->json([
                'status' => false,
                // intentionally omit "message" to avoid leaking "Validation failed"
                'errors' => $v->errors()->all(),
            ], 422);
        }

        $rows = Excel::toArray([], $request->file('suppliers'));
        $sheet = $rows[0] ?? [];
        if (empty($sheet)) {
            return response()->json([
                'status' => false,
                'errors' => ['No data found in the uploaded file.'],
            ], 422);
        }

        // Header detection
        $first = $sheet[0] ?? [];
        $assocInput = is_array($first) && count(array_filter(array_keys($first), 'is_string')) > 0;

        $normalized = [];
        if ($assocInput) {
            foreach ($sheet as $r) {
                $normalized[] = $this->normalizeAssocRowSupplier($r);
            }
            $lineBase = 1; // header already applied in source
        } else {
            $header = array_map(function ($h) {
                return $this->normalizeKeySupplier((string) $h);
            }, $first);
            for ($i = 1; $i < count($sheet); $i++) {
                $row = $sheet[$i];
                $assoc = [];
                foreach ($header as $idx => $key) {
                    $assoc[$key] = $row[$idx] ?? null;
                }
                $normalized[] = $this->normalizeAssocRowSupplier($assoc);
            }
            $lineBase = 2; // data start line if first row was header labels
        }

        $errors = [];
        $prepared = [];
        $codesInFile = [];

        foreach ($normalized as $i => $row) {
            $line = $i + $lineBase;

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $codeRaw = array_key_exists('code', $row) ? $row['code'] : null;
            $email = isset($row['email']) ? trim((string) $row['email']) : '';
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            $country = isset($row['country']) ? trim((string) $row['country']) : '';
            $city = isset($row['city']) ? trim((string) $row['city']) : '';
            $adresse = isset($row['adresse']) ? trim((string) $row['adresse']) : '';
            $tax_number = isset($row['tax_number']) ? trim((string) $row['tax_number']) : '';
            $opening_balance = isset($row['opening_balance']) && is_numeric($row['opening_balance']) ? (float) $row['opening_balance'] : 0;
            $credit_limit = isset($row['credit_limit']) && is_numeric($row['credit_limit']) ? (float) $row['credit_limit'] : 0;

            if ($name === '') {
                $errors[] = "Row {$line}: name is required.";
            }

            // code must be integer
            $code = null;
            if ($codeRaw === null || $codeRaw === '') {
                $errors[] = "Row {$line}: code is required and must be an integer.";
            } elseif (is_numeric($codeRaw) && intval($codeRaw) == $codeRaw) {
                $code = intval($codeRaw);
            } else {
                $errors[] = "Row {$line}: code '{$codeRaw}' is not a valid integer.";
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$line}: email '{$email}' is not valid.";
            }

            // in-file duplicate codes
            if ($code !== null) {
                if (isset($codesInFile[$code])) {
                    $errors[] = "Row {$line}: duplicate code '{$code}' found in the file (also on row {$codesInFile[$code]}).";
                } else {
                    $codesInFile[$code] = $line;
                }
            }

            $prepared[] = [
                'name' => $name,
                'code' => $code,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'country' => $country ?: null,
                'city' => $city ?: null,
                'adresse' => $adresse ?: null,
                'tax_number' => $tax_number ?: null,
                'opening_balance' => $opening_balance,
                'credit_limit' => $credit_limit,
            ];
        }

        // DB duplicate codes
        $codes = array_values(array_filter(array_map(function ($r) {
            return $r['code'];
        }, $prepared), function ($v) {
            return $v !== null;
        }));
        if (! empty($codes)) {
            $dupes = Provider::whereNull('deleted_at')->whereIn('code', $codes)->pluck('code')->all();
            foreach ($dupes as $dup) {
                $errors[] = "code '{$dup}' already exists in the system.";
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'status' => false,
                'errors' => $errors, // ONLY errors[]
            ], 422);
        }

        // Insert suppliers
        $now = now();
        $insertRows = [];
        foreach ($prepared as $r) {
            $insertRows[] = [
                'name' => $r['name'],
                'code' => $r['code'],
                'email' => $r['email'],
                'phone' => $r['phone'],
                'country' => $r['country'],
                'city' => $r['city'],
                'adresse' => $r['adresse'],
                'tax_number' => $r['tax_number'],
                'opening_balance' => $r['opening_balance'],
                'credit_limit' => $r['credit_limit'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($insertRows) {
            foreach (array_chunk($insertRows, 1000) as $chunk) {
                Provider::insert($chunk);
            }
        });

        return response()->json([
            'status' => true,
            'imported' => count($insertRows),
            // optionally: 'warnings' => [...]
        ]);
    }

    // --- helpers (private methods in the same controller) ---
    private function normalizeAssocRowSupplier(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = $this->normalizeKeySupplier((string) $k);
            $out[$this->resolveSupplierSynonym($key)] = $v;
        }

        return $out;
    }

    private function normalizeKeySupplier(string $key): string
    {
        $key = trim(mb_strtolower($key));

        return preg_replace('/[.\s\-]+/u', '_', $key);
    }

    private function resolveSupplierSynonym(string $key): string
    {
        $map = [
            'supplier' => 'name',
            'vendor' => 'name',
            'supplier_name' => 'name',
            'vendor_name' => 'name',

            'supplier_code' => 'code',
            'vendor_code' => 'code',

            'address' => 'adresse',
            'addr' => 'adresse',

            'tax' => 'tax_number',
            'taxno' => 'tax_number',
            'tax_no' => 'tax_number',
            'vat' => 'tax_number',

            'openingbalance' => 'opening_balance',
            'opening_bal' => 'opening_balance',
            'opening_balances' => 'opening_balance',
            'previous_due' => 'opening_balance',
            'previous_dues' => 'opening_balance',

            'creditlimit' => 'credit_limit',
            'credit' => 'credit_limit',
        ];

        return isset($map[$key]) ? $map[$key] : $key;
    }

    // ------------- pay_supplier_due -------------\\

    public function pay_supplier_due(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'pay_supplier_due', Provider::class);

        if ($request['amount'] > 0) {
            $provider = Provider::findOrFail($request->provider_id);
            $paid_amount_total = $request->amount;

            // Pay opening balance first (only when this supplier has one).
            // Suppliers created before this feature have opening_balance = 0,
            // so this block is skipped and the original purchase-payment flow runs unchanged.
            if ($provider->opening_balance > 0 && $paid_amount_total > 0) {
                if ($paid_amount_total >= $provider->opening_balance) {
                    $opening_balance_paid = $provider->opening_balance;
                    $paid_amount_total -= $provider->opening_balance;
                    $provider->opening_balance = 0;
                } else {
                    $opening_balance_paid = $paid_amount_total;
                    $provider->opening_balance -= $paid_amount_total;
                    $paid_amount_total = 0;
                }
                $provider->save();

                $payment_opening_balance = new ProviderPaymentOpeningBalance;
                $payment_opening_balance->provider_id = $request->provider_id;
                $payment_opening_balance->account_id = $request['account_id'] ? $request['account_id'] : null;
                $payment_opening_balance->Ref = $this->getOpeningBalancePaymentNumberOrder();
                $payment_opening_balance->date = Carbon::now();
                $payment_opening_balance->payment_method_id = $request['payment_method_id'];
                $payment_opening_balance->montant = $opening_balance_paid;
                $payment_opening_balance->change = 0;
                $payment_opening_balance->notes = $request['notes'];
                $payment_opening_balance->user_id = Auth::user()->id;
                $payment_opening_balance->save();

                $account = Account::where('id', $request['account_id'])->exists();
                if ($account) {
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance - $opening_balance_paid,
                    ]);
                }
            }

            $provider_purchases_due = Purchase::where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where([
                    ['payment_statut', '!=', 'paid'],
                    ['provider_id', $request->provider_id],
                ])->get();

            foreach ($provider_purchases_due as $key => $provider_purchase) {
                if ($paid_amount_total == 0) {
                    break;
                }
                $due = $provider_purchase->GrandTotal - $provider_purchase->paid_amount;

                if ($paid_amount_total >= $due) {
                    $amount = $due;
                    $payment_status = 'paid';
                } else {
                    $amount = $paid_amount_total;
                    $payment_status = 'partial';
                }

                $payment_purchase = new PaymentPurchase;
                $payment_purchase->purchase_id = $provider_purchase->id;
                $payment_purchase->account_id = $request['account_id'] ? $request['account_id'] : null;
                $payment_purchase->Ref = app('App\Http\Controllers\PaymentPurchasesController')->getNumberOrder();
                $payment_purchase->date = Carbon::now();
                $payment_purchase->payment_method_id = $request['payment_method_id'];
                $payment_purchase->montant = $amount;
                $payment_purchase->change = 0;
                $payment_purchase->notes = $request['notes'];
                $payment_purchase->user_id = Auth::user()->id;
                $payment_purchase->save();

                $account = Account::where('id', $request['account_id'])->exists();

                if ($account) {
                    // Account exists, perform the update
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance - $amount,
                    ]);
                }

                $provider_purchase->paid_amount += $amount;
                $provider_purchase->payment_statut = $payment_status;
                $provider_purchase->save();

                $paid_amount_total -= $amount;
            }
        }

        return response()->json(['success' => true]);

    }

    // ------------- pay_purchase_return_due -------------\\

    public function pay_purchase_return_due(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'pay_purchase_return_due', Provider::class);

        if ($request['amount'] > 0) {
            $supplier_purchase_return_due = PurchaseReturn::where('deleted_at', '=', null)
                ->where([
                    ['payment_statut', '!=', 'paid'],
                    ['provider_id', $request->provider_id],
                ])->get();

            $paid_amount_total = $request->amount;

            foreach ($supplier_purchase_return_due as $key => $supplier_purchase_return) {
                if ($paid_amount_total == 0) {
                    break;
                }
                $due = $supplier_purchase_return->GrandTotal - $supplier_purchase_return->paid_amount;

                if ($paid_amount_total >= $due) {
                    $amount = $due;
                    $payment_status = 'paid';
                } else {
                    $amount = $paid_amount_total;
                    $payment_status = 'partial';
                }

                $payment_purchase_return = new PaymentPurchaseReturns;
                $payment_purchase_return->purchase_return_id = $supplier_purchase_return->id;
                $payment_purchase_return->account_id = $request['account_id'] ? $request['account_id'] : null;
                $payment_purchase_return->Ref = app('App\Http\Controllers\PaymentPurchaseReturnsController')->getNumberOrder();
                $payment_purchase_return->date = Carbon::now();
                $payment_purchase_return->payment_method_id = $request['payment_method_id'];
                $payment_purchase_return->montant = $amount;
                $payment_purchase_return->change = 0;
                $payment_purchase_return->notes = $request['notes'];
                $payment_purchase_return->user_id = Auth::user()->id;
                $payment_purchase_return->save();

                $account = Account::where('id', $request['account_id'])->exists();

                if ($account) {
                    // Account exists, perform the update
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance + $amount,
                    ]);
                }

                $supplier_purchase_return->paid_amount += $amount;
                $supplier_purchase_return->payment_statut = $payment_status;
                $supplier_purchase_return->save();

                $paid_amount_total -= $amount;
            }
        }

        return response()->json(['success' => true]);

    }
}
