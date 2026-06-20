<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\EcommerceClient;
use App\Models\PaymentMethod;
use App\Models\PaymentOpeningBalance;
use App\Models\PaymentSale;
use App\Models\PaymentSaleReturns;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use ArPHP\I18N\Arabic;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends BaseController
{
    // ------------- Get ALL Customers -------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $order = $request->SortField ?: 'id';
        $dir = strtolower($request->SortType ?: 'desc');
        // Validate sort direction
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }
        $helpers = new helpers;
        // Filter fields With Params to retrieve
        $columns = [0 => 'name', 1 => 'code', 2 => 'phone', 3 => 'email'];
        $param = [0 => 'like', 1 => 'like', 2 => 'like', 3 => 'like'];
        $data = [];
        $clients = Client::where('deleted_at', '=', null);

        // Multiple Filter
        $Filtred = $helpers->filter($clients, $columns, $param, $request)
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
        
        // Handle pagination - MySQL requires LIMIT when using OFFSET
        if ($perPage == '-1' || empty($perPage) || $perPage <= 0) {
            // Get all records without pagination (no offset/limit)
            $clients = $Filtred->orderBy($order, $dir)->get();
        } else {
            // Ensure perPage is a positive integer
            $perPage = (int) $perPage;
            if ($perPage <= 0) {
                $perPage = 10; // Default to 10 if invalid
            }
            // Calculate offset for pagination
            $offSet = max(0, ($pageStart * $perPage) - $perPage);
            $clients = $Filtred->offset($offSet)
                ->limit($perPage)
                ->orderBy($order, $dir)
                ->get();
        }

        foreach ($clients as $client) {

            $client_exist = EcommerceClient::where('deleted_at', '=', null)
                ->where('client_id', $client->id)->exists();

            if ($client_exist) {
                $item['client_ecommerce'] = 'yes';
            } else {
                $item['client_ecommerce'] = 'no';
            }

            $item['total_amount'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('paid_amount');

            $item['due'] = $item['total_amount'] - $item['total_paid'];

            $item['total_amount_return'] = DB::table('sale_returns')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('sale_returns')
                ->where('sale_returns.deleted_at', '=', null)
                ->where('sale_returns.client_id', $client->id)
                ->sum('paid_amount');

            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['id'] = $client->id;
            $item['firstname'] = $client->firstname;
            $item['lastname'] = $client->lastname;
            $item['name'] = $client->name;
            $item['phone'] = $client->phone;
            $item['tax_number'] = $client->tax_number;
            $item['code'] = $client->code;
            $item['email'] = $client->email;
            $item['woocommerce_id'] = $client->woocommerce_id;
            $item['country'] = $client->country;
            $item['city'] = $client->city;
            $item['state'] = $client->state;
            $item['zip'] = $client->zip;
            $item['adresse'] = $client->adresse;
            $item['is_royalty_eligible'] = $client->is_royalty_eligible;
            $item['points'] = $client->points;
            $item['opening_balance'] = $client->opening_balance ?? 0;
            $item['credit_limit'] = $client->credit_limit ?? 0;
            $item['net_balance'] = ($client->opening_balance ?? 0) + $item['due'] - $item['return_Due'];
            $data[] = $item;
        }

        $company_info = Setting::where('deleted_at', '=', null)->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);

        $clientsWithoutEcommerce = \App\Models\Client::where('deleted_at', '=', null)
            ->whereNotIn('id', function ($query) {
                $query->select('client_id')->from('ecommerce_clients');
            })->count();

        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

        return response()->json([
            'clients' => $data,
            'company_info' => $company_info,
            'totalRows' => $totalRows,
            'clients_without_ecommerce' => $clientsWithoutEcommerce,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ------------- Store new Customer -------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Client::class);

        $this->validate($request, [
            'name' => 'required',
            'firstname' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Ensure email is unique in clients table (exclude soft-deleted)
                Rule::unique('clients', 'email')->whereNull('deleted_at'),
                // Ensure email is unique in ecommerce_clients table (exclude soft-deleted)
                Rule::unique('ecommerce_clients', 'email')->whereNull('deleted_at'),
            ],
        ]);

        if ($request['is_royalty_eligible'] == '1' || $request['is_royalty_eligible'] == 'true') {
            $is_royalty_eligible = 1;
        } else {
            $is_royalty_eligible = 0;
        }

        $client = Client::create([
            'firstname' => $request['firstname'],
            'lastname' => $request['lastname'],
            'name' => $request['name'],
            'code' => $this->getNumberOrder(),
            'adresse' => $request['adresse'],
            'phone' => $request['phone'],
            'email' => $request['email'],
            'country' => $request['country'],
            'city' => $request['city'],
            'state' => $request['state'],
            'zip' => $request['zip'],
            'tax_number' => $request['tax_number'],
            'is_royalty_eligible' => $is_royalty_eligible,
            'opening_balance' => $request['opening_balance'] ?? 0,
            'credit_limit' => $request['credit_limit'] ?? 0,
        ]);

        return response()->json($client);
    }

    // ------------ function show -----------\\

    public function show($id)
    {
        $client = Client::where('deleted_at', '=', null)->findOrFail($id);
        
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);
        
        return response()->json([
            'client' => $client,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }

    // ------------- Update Customer -------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        // Get existing ecommerce_client id if it exists (for ignoring in validation)
        $existingEcommerceClient = EcommerceClient::where('client_id', $id)
            ->whereNull('deleted_at')
            ->first();
        $ecommerceClientId = $existingEcommerceClient ? $existingEcommerceClient->id : null;

        // Validate input for both Client and EcommerceClient
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'firstname' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Ensure email is unique in clients table (ignore current client, exclude soft-deleted)
                Rule::unique('clients', 'email')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
                // Ensure email is unique in ecommerce_clients table (ignore current ecommerce_client if exists, exclude soft-deleted)
                Rule::unique('ecommerce_clients', 'email')
                    ->ignore($ecommerceClientId)
                    ->whereNull('deleted_at'),
            ],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],

            // EcommerceClient-specific (optional)
            'username' => [
                'nullable', 'string', 'max:100',
                Rule::unique('ecommerce_clients', 'username')->ignore($id, 'client_id'),
            ],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:6'],

            // flags
            'is_royalty_eligible' => ['nullable'],
        ]);

        // Normalize boolean flag from various inputs: '1', 'true', true, etc.
        $isRoyaltyEligible = filter_var($request->input('is_royalty_eligible'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        DB::transaction(function () use ($request, $id, $isRoyaltyEligible) {
            // 1) Update Client
            // opening_balance is adjusted via the dedicated adjust-opening-balance endpoint
            Client::whereKey($id)->update([
                'firstname' => $request->input('firstname'),
                'lastname' => $request->input('lastname'),
                'name' => $request->input('name'),
                'adresse' => $request->input('adresse'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'country' => $request->input('country'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'zip' => $request->input('zip'),
                'tax_number' => $request->input('tax_number'),
                'is_royalty_eligible' => $isRoyaltyEligible,
                'credit_limit' => $request->input('credit_limit', 0),
            ]);

            // 2) Upsert EcommerceClient linked by client_id
            $payload = [
                'username' => $request->input('username', $request->input('name')), // default to name if username absent
                'email' => $request->input('email'),
            ];

            if ($request->filled('password')) {
                $payload['password'] = Hash::make($request->input('password'));
            }

            EcommerceClient::updateOrCreate(
                ['client_id' => $id], // lookup by client_id
                $payload
            );
        });

        return response()->json(['success' => true]);
    }

    // ------------- delete client -------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Client::class);

        DB::transaction(function () use ($id) {
            $now = Carbon::now();

            // Soft delete Client
            Client::whereKey($id)->update(['deleted_at' => $now]);

            // Soft delete linked EcommerceClient (and optionally inactivate)
            EcommerceClient::where('client_id', $id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'status' => 0,
                ]);
        });

        return response()->json(['success' => true]);
    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Client::class);

        $data = $request->validate([
            'selectedIds' => ['required', 'array', 'min:1'],
            'selectedIds.*' => ['integer'],
        ]);

        $ids = $data['selectedIds'];
        $now = Carbon::now();

        DB::transaction(function () use ($ids, $now) {
            // Soft delete all selected Clients
            Client::whereIn('id', $ids)->update(['deleted_at' => $now]);

            // Soft delete all linked EcommerceClient rows
            EcommerceClient::whereIn('client_id', $ids)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'status' => 0,
                ]);
        });

        return response()->json(['success' => true]);
    }

    // ------------- get Number Order Customer -------------\\

    public function getNumberOrder()
    {
        $last = DB::table('clients')->latest('id')->first();

        if ($last) {
            $code = $last->code + 1;
        } else {
            $code = 1;
        }

        return $code;
    }

    public function getOpeningBalancePaymentNumberOrder()
    {
        $last = DB::table('client_opening_balance_payments')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            // Get the last part (the number) and all parts before it (the prefix)
            $lastPart = end($nwMsg); // Get the number part
            $prefix = implode('_', array_slice($nwMsg, 0, -1)); // Get everything before the number
            $inMsg = (int) $lastPart + 1;
            $code = $prefix . '_' . $inMsg;
        } else {
            $code = 'OB_PAY_1111';
        }

        return $code;
    }

    // ------------- Get Clients Without Paginate -------------\\

    public function Get_Clients_Without_Paginate()
    {
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name', 'phone']);

        return response()->json($clients);
    }

    // ------------- Get Clients get_client_store_data Paginate -------------\\

    public function get_client_store_data($id)
    {
        $client = EcommerceClient::where('client_id', $id)->first();

        $data['id'] = $client->id;
        $data['client_id'] = $client->client_id;
        $item['name'] = $client->username;
        $data['email'] = $client->email;
        $data['NewPassword'] = null;

        return response()->json($data);
    }

    public function import(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'customers_import', Client::class);

        // 1) File validation that returns errors[] as array of strings
        $v = Validator::make($request->all(), [
            'customers' => 'required|file|mimes:xls,xlsx|max:20480', // 20MB
        ]);
        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $v->errors()->all(),  // <-- array of strings
            ], 422);
        }

        $rows = Excel::toArray([], $request->file('customers'));
        $sheet = $rows[0] ?? [];
        if (empty($sheet)) {
            return response()->json([
                'status' => false,
                'message' => 'The imported file is empty.',
                'errors' => ['No data found in the uploaded file.'],
            ], 422);
        }

        // Normalize rows (with/without headers)
        $first = $sheet[0] ?? [];
        $assocInput = is_array($first) && count(array_filter(array_keys($first), 'is_string')) > 0;

        $normalized = [];
        if ($assocInput) {
            foreach ($sheet as $r) {
                $normalized[] = $this->normalizeAssocRow($r);
            }
        } else {
            $header = array_map(function ($h) {
                return $this->normalizeKey((string) $h);
            }, $first);
            for ($i = 1; $i < count($sheet); $i++) {
                $row = $sheet[$i];
                $assoc = [];
                foreach ($header as $idx => $key) {
                    $assoc[$key] = $row[$idx] ?? null;
                }
                $normalized[] = $this->normalizeAssocRow($assoc);
            }
        }

        $errors = [];
        $prepared = [];
        $codesInFile = [];
        $emailsInFile = [];
        $lineBase = 2; // data start line if first row is header

        foreach ($normalized as $i => $row) {
            $line = $i + $lineBase;
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $firstname = isset($row['firstname']) ? trim((string) $row['firstname']) : '';
            $lastname = isset($row['lastname']) ? trim((string) $row['lastname']) : '';
            $codeRaw = $row['code'] ?? null;
            $email = isset($row['email']) ? trim((string) $row['email']) : '';
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            $country = isset($row['country']) ? trim((string) $row['country']) : '';
            $city = isset($row['city']) ? trim((string) $row['city']) : '';
            $state = isset($row['state']) ? trim((string) $row['state']) : '';
            $zip = isset($row['zip']) ? trim((string) $row['zip']) : '';
            $adresse = isset($row['adresse']) ? trim((string) $row['adresse']) : '';
            $tax_number = isset($row['tax_number']) ? trim((string) $row['tax_number']) : '';
            $opening_balance_raw = $row['opening_balance'] ?? null;

            if ($name === '') {
                $errors[] = "Row {$line}: name is required.";
            }

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

            if ($code !== null) {
                if (isset($codesInFile[$code])) {
                    $errors[] = "Row {$line}: duplicate code '{$code}' found in the file (also on row {$codesInFile[$code]}).";
                } else {
                    $codesInFile[$code] = $line;
                }
            }

            // Check for duplicate emails within the file
            if ($email !== '') {
                if (isset($emailsInFile[$email])) {
                    $errors[] = "Row {$line}: duplicate email '{$email}' found in the file (also on row {$emailsInFile[$email]}).";
                } else {
                    $emailsInFile[$email] = $line;
                }
            }

            // opening_balance: optional numeric; default 0 when empty
            $opening_balance = 0.0;
            if ($opening_balance_raw !== null && $opening_balance_raw !== '') {
                if (is_numeric($opening_balance_raw)) {
                    $opening_balance = (float) $opening_balance_raw;
                } else {
                    $errors[] = "Row {$line}: opening_balance '{$opening_balance_raw}' is not a valid number.";
                }
            }

            $prepared[] = [
                'name' => $name,
                'firstname' => $firstname ?: null,
                'lastname' => $lastname ?: null,
                'code' => $code,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'country' => $country ?: null,
                'city' => $city ?: null,
                'state' => $state ?: null,
                'zip' => $zip ?: null,
                'adresse' => $adresse ?: null,
                'tax_number' => $tax_number ?: null,
                'opening_balance' => $opening_balance,
            ];
        }

        // DB duplicates - codes
        $codes = array_values(array_filter(array_map(function ($r) {
            return $r['code'];
        }, $prepared), function ($v) {
            return $v !== null;
        }));
        if (! empty($codes)) {
            $dupes = Client::whereNull('deleted_at')->whereIn('code', $codes)->pluck('code')->all();
            foreach ($dupes as $dup) {
                $errors[] = "code '{$dup}' already exists in the system.";
            }
        }

        // DB duplicates - emails (check both clients and ecommerce_clients tables)
        $emails = array_values(array_filter(array_map(function ($r) {
            return $r['email'];
        }, $prepared), function ($v) {
            return $v !== null && $v !== '';
        }));
        if (! empty($emails)) {
            // Check for duplicate emails in clients table
            $dupesInClients = Client::whereNull('deleted_at')
                ->whereIn('email', $emails)
                ->pluck('email')
                ->all();
            foreach ($dupesInClients as $dupEmail) {
                $errors[] = "email '{$dupEmail}' already exists in clients table.";
            }

            // Check for duplicate emails in ecommerce_clients table
            $dupesInEcommerce = EcommerceClient::whereNull('deleted_at')
                ->whereIn('email', $emails)
                ->pluck('email')
                ->all();
            foreach ($dupesInEcommerce as $dupEmail) {
                $errors[] = "email '{$dupEmail}' already exists in ecommerce_clients table.";
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $errors,   // <-- array of strings (UI now uses ONLY this)
            ], 422);
        }

        // Insert
        $now = now();
        $insertRows = [];
        foreach ($prepared as $r) {
            $insertRows[] = [
                'name' => $r['name'],
                'firstname' => $r['firstname'] ?? null,
                'lastname' => $r['lastname'] ?? null,
                'code' => $r['code'],
                'email' => $r['email'],
                'phone' => $r['phone'],
                'country' => $r['country'],
                'city' => $r['city'],
                'state' => $r['state'] ?? null,
                'zip' => $r['zip'] ?? null,
                'adresse' => $r['adresse'],
                'tax_number' => $r['tax_number'],
                'opening_balance' => $r['opening_balance'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($insertRows) {
            foreach (array_chunk($insertRows, 1000) as $chunk) {
                Client::insert($chunk);
            }
        });

        return response()->json([
            'status' => true,
            'imported' => count($insertRows),
        ]);
    }

    private function normalizeAssocRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = $this->normalizeKey((string) $k);
            $out[$this->resolveSynonym($key)] = $v;
        }

        return $out;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim(mb_strtolower($key));

        return preg_replace('/[.\\s\\-]+/u', '_', $key);
    }

    private function resolveSynonym(string $key): string
    {
        $map = [
            'fullname' => 'name', 'full_name' => 'name', 'client' => 'name', 'customer' => 'name', 'username' => 'name',
            'first_name' => 'firstname', 'firstname' => 'firstname',
            'last_name' => 'lastname', 'lastname' => 'lastname',
            'customer_code' => 'code', 'client_code' => 'code',
            'address' => 'adresse', 'addr' => 'adresse', 'tax' => 'tax_number', 'taxnumber' => 'tax_number',
            'tax_no' => 'tax_number', 'vat' => 'tax_number',
            'state_province' => 'state', 'province' => 'state', 'region' => 'state',
            'postal_code' => 'zip', 'postcode' => 'zip', 'zipcode' => 'zip',
            // opening balance synonyms
            'openingbalance' => 'opening_balance',
            'opening_bal' => 'opening_balance',
            'opening_balances' => 'opening_balance',
            'previous_due' => 'opening_balance',
            'previous_dues' => 'opening_balance',
        ];

        return isset($map[$key]) ? $map[$key] : $key;
    }

    // ------------- clients_pay_due -------------\\

    public function clients_pay_due(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'pay_due', Client::class);

        if ($request['amount'] > 0) {
            $client = Client::findOrFail($request->client_id);
            $paid_amount_total = $request->amount;

            // STEP 1: Pay Opening Balance First (if exists)
            $opening_balance_paid = 0;
            if ($client->opening_balance > 0 && $paid_amount_total > 0) {
                if ($paid_amount_total >= $client->opening_balance) {
                    // Payment covers full opening balance
                    $opening_balance_paid = $client->opening_balance;
                    $paid_amount_total -= $client->opening_balance;
                    $client->opening_balance = 0;
                } else {
                    // Payment partially covers opening balance
                    $opening_balance_paid = $paid_amount_total;
                    $client->opening_balance -= $paid_amount_total;
                    $paid_amount_total = 0; // No money left for sales
                }
                $client->save();

                // Record opening balance payment in history table
                $payment_opening_balance = new PaymentOpeningBalance;
                $payment_opening_balance->client_id = $request->client_id;
                $payment_opening_balance->account_id = $request['account_id'] ? $request['account_id'] : null;
                $payment_opening_balance->Ref = $this->getOpeningBalancePaymentNumberOrder();
                $payment_opening_balance->date = Carbon::now();
                $payment_opening_balance->payment_method_id = $request['payment_method_id'];
                $payment_opening_balance->montant = $opening_balance_paid;
                $payment_opening_balance->change = 0;
                $payment_opening_balance->notes = $request['notes'];
                $payment_opening_balance->user_id = Auth::user()->id;
                $payment_opening_balance->save();

                // Update account balance if account is provided
                $account = Account::where('id', $request['account_id'])->exists();
                if ($account) {
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance + $opening_balance_paid,
                    ]);
                }
            }

            // STEP 2: Pay Current Sales (existing logic)
            if ($paid_amount_total > 0) {
                $client_sales_due = Sale::where('deleted_at', '=', null)
                    ->where('statut', 'completed')
                    ->where([
                        ['payment_statut', '!=', 'paid'],
                        ['client_id', $request->client_id],
                    ])->orderBy('date', 'asc')->get(); // Oldest first

                foreach ($client_sales_due as $key => $client_sale) {
                    if ($paid_amount_total == 0) {
                        break;
                    }
                    $due = $client_sale->GrandTotal - $client_sale->paid_amount;

                    if ($paid_amount_total >= $due) {
                        $amount = $due;
                        $payment_status = 'paid';
                    } else {
                        $amount = $paid_amount_total;
                        $payment_status = 'partial';
                    }

                    $payment_sale = new PaymentSale;
                    $payment_sale->sale_id = $client_sale->id;
                    $payment_sale->account_id = $request['account_id'] ? $request['account_id'] : null;
                    $payment_sale->Ref = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                    $payment_sale->date = Carbon::now();
                    $payment_sale->payment_method_id = $request['payment_method_id'];
                    $payment_sale->montant = $amount;
                    $payment_sale->change = 0;
                    $payment_sale->notes = $request['notes'];
                    $payment_sale->user_id = Auth::user()->id;
                    $payment_sale->save();

                    $account = Account::where('id', $request['account_id'])->exists();

                    if ($account) {
                        // Account exists, perform the update
                        $account = Account::find($request['account_id']);
                        $account->update([
                            'balance' => $account->balance + $amount,
                        ]);
                    }

                    $client_sale->paid_amount += $amount;
                    $client_sale->payment_statut = $payment_status;
                    $client_sale->save();

                    $paid_amount_total -= $amount;
                }
            }
        }

        return response()->json(['success' => true]);

    }

    // ------------- clients_pay_sale_return_due -------------\\

    public function pay_sale_return_due(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'pay_sale_return_due', Client::class);

        if ($request['amount'] > 0) {
            $client_sell_return_due = SaleReturn::where('deleted_at', '=', null)
                ->where([
                    ['payment_statut', '!=', 'paid'],
                    ['client_id', $request->client_id],
                ])->get();

            $paid_amount_total = $request->amount;

            foreach ($client_sell_return_due as $key => $client_sale_return) {
                if ($paid_amount_total == 0) {
                    break;
                }
                $due = $client_sale_return->GrandTotal - $client_sale_return->paid_amount;

                if ($paid_amount_total >= $due) {
                    $amount = $due;
                    $payment_status = 'paid';
                } else {
                    $amount = $paid_amount_total;
                    $payment_status = 'partial';
                }

                $payment_sale_return = new PaymentSaleReturns;
                $payment_sale_return->sale_return_id = $client_sale_return->id;
                $payment_sale_return->account_id = $request['account_id'] ? $request['account_id'] : null;
                $payment_sale_return->Ref = app('App\Http\Controllers\PaymentSaleReturnsController')->getNumberOrder();
                $payment_sale_return->date = Carbon::now();
                $payment_sale_return->payment_method_id = $request['payment_method_id'];
                $payment_sale_return->montant = $amount;
                $payment_sale_return->change = 0;
                $payment_sale_return->notes = $request['notes'];
                $payment_sale_return->user_id = Auth::user()->id;
                $payment_sale_return->save();

                $account = Account::where('id', $request['account_id'])->exists();

                if ($account) {
                    // Account exists, perform the update
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance - $amount,
                    ]);
                }

                $client_sale_return->paid_amount += $amount;
                $client_sale_return->payment_statut = $payment_status;
                $client_sale_return->save();

                $paid_amount_total -= $amount;
            }
        }

        return response()->json(['success' => true]);

    }

    public function getPoints(Request $request, $id)
    {
        $client = Client::find($id);

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'points' => $client->points,
            'is_royalty_eligible' => $client->is_royalty_eligible,
            'name' => $client->name,
        ]);
    }

    public function updatePoints(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $request->validate([
            'points' => 'required',
        ]);

        $client = Client::findOrFail($id);
        $client->points = $request->points;
        $client->save();

        return response()->json(['success' => true]);
    }

    public function adjustOpeningBalance(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        $request->validate([
            'mode' => ['required', Rule::in(['add', 'subtract', 'set'])],
            'amount' => ['required', 'numeric'],
        ]);

        $client = Client::findOrFail($id);
        $current = (float) ($client->opening_balance ?? 0);
        $amount = (float) $request->input('amount');

        switch ($request->input('mode')) {
            case 'add':
                $new = $current + abs($amount);
                break;
            case 'subtract':
                $new = $current - abs($amount);
                break;
            case 'set':
            default:
                $new = $amount;
                break;
        }

        $client->opening_balance = round($new, 2);
        $client->save();

        return response()->json([
            'success' => true,
            'previous_opening_balance' => round($current, 2),
            'opening_balance' => round((float) $client->opening_balance, 2),
        ]);
    }

    public function salesByClient(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
            'limit' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'search' => 'sometimes|string|nullable',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        // Pagination
        $perPage = (int) ($request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $offSet = ($page - 1) * ($perPage > 0 ? $perPage : 0);

        $q = Sale::query()
            ->whereNull('deleted_at')
            ->with(['client:id,name', 'warehouse:id,name'])
            ->where('client_id', $request->id)

            // Search (Ref, statut, payment_statut, warehouse.name, client.name)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('Ref', 'LIKE', "%{$s}%")
                        ->orWhere('statut', 'LIKE', "%{$s}%")
                        ->orWhere('payment_statut', 'LIKE', "%{$s}%")
                        ->orWhereHas('warehouse', function ($wq) use ($s) {
                            $wq->where('name', 'LIKE', "%{$s}%");
                        })
                        ->orWhereHas('client', function ($cq) use ($s) {
                            $cq->where('name', 'LIKE', "%{$s}%");
                        });
                });
            });

        $totalRows = (clone $q)->count();

        if ($perPage === -1) {
            $perPage = $totalRows;
            $offSet = 0;
        }

        $rows = $q->orderByDesc('id')
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        $data = [];
        foreach ($rows as $sale) {
            $item = [
                'id' => $sale->id,
                'date' => $sale->date,
                'Ref' => $sale->Ref,
                'warehouse_name' => optional($sale->warehouse)->name,
                'client_name' => optional($sale->client)->name,
                'statut' => $sale->statut,
                'GrandTotal' => $sale->GrandTotal,
                'paid_amount' => $sale->paid_amount,
                'due' => (float) $sale->GrandTotal - (float) $sale->paid_amount,
                'payment_status' => $sale->payment_statut,
                'shipping_status' => $sale->shipping_status,
            ];
            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);
    }

    /**
     * GET /api/reports/payments_client
     * Params: id (client_id, required), limit, page, search
     * Returns: { totalRows, payments: [] }
     */
    public function paymentsByClient(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
            'limit' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'search' => 'sometimes|string|nullable',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $perPage = (int) ($request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $offSet = ($page - 1) * ($perPage > 0 ? $perPage : 0);

        // Get sales payments
        $salesPaymentsQuery = DB::table('payment_sales')
            ->whereNull('payment_sales.deleted_at')
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'payment_sales.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.client_id', $request->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('payment_sales.Ref', 'LIKE', "%{$s}%")
                        ->orWhere('payment_sales.date', 'LIKE', "%{$s}%")
                        ->orWhere('payment_methods.name', 'LIKE', "%{$s}%");
                });
            })
            ->select(
                'payment_sales.id',
                'payment_sales.date',
                'payment_sales.Ref as Ref',
                'sales.Ref as Sale_Ref',
                'payment_methods.name as payment_method',
                'payment_sales.montant',
                DB::raw("'sale' as payment_type")
            );

        $salesPayments = $salesPaymentsQuery->get()->map(function ($item) {
            return (array) $item;
        });

        // Get opening balance payments
        $openingBalancePaymentsQuery = DB::table('client_opening_balance_payments')
            ->whereNull('client_opening_balance_payments.deleted_at')
            ->join('payment_methods', 'client_opening_balance_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('client_opening_balance_payments.client_id', $request->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('client_opening_balance_payments.Ref', 'LIKE', "%{$s}%")
                        ->orWhere('client_opening_balance_payments.date', 'LIKE', "%{$s}%")
                        ->orWhere('payment_methods.name', 'LIKE', "%{$s}%");
                });
            })
            ->select(
                'client_opening_balance_payments.id',
                'client_opening_balance_payments.date',
                'client_opening_balance_payments.Ref as Ref',
                DB::raw("NULL as Sale_Ref"),
                'payment_methods.name as payment_method',
                'client_opening_balance_payments.montant',
                DB::raw("'opening_balance' as payment_type")
            );

        $openingBalancePayments = $openingBalancePaymentsQuery->get()->map(function ($item) {
            return (array) $item;
        });

        // Combine and sort all payments
        $allPayments = $salesPayments->merge($openingBalancePayments)
            ->sortByDesc('id')
            ->values()
            ->all();

        $totalRows = count($allPayments);

        if ($perPage === -1) {
            $perPage = $totalRows;
            $offSet = 0;
        }

        // Get paginated results
        $rows = collect($allPayments)
            ->slice($offSet, $perPage)
            ->values()
            ->all();

        return response()->json([
            'payments' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * GET /api/clients/{id}/brief
     * Minimal client info for the ledger header.
     */
    public function clientBrief(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $client = Client::query()
            ->select('id', 'name', 'email', 'phone', 'code', 'adresse', 'country', 'city', 'state', 'zip', 'tax_number', 'opening_balance', 'credit_limit')
            ->whereNull('deleted_at')
            ->findOrFail($id);

        // -------- SALES TOTALS --------
        $total_amount = DB::table('sales')
            ->whereNull('deleted_at')
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $total_paid = DB::table('sales')
            ->whereNull('deleted_at')
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $sale_due = $total_amount - $total_paid;

        // -------- RETURNS TOTALS --------
        $total_amount_return = DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $total_paid_return = DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $return_due = $total_amount_return - $total_paid_return;

        // -------- PAYMENTS TOTALS --------

        $payments_total = DB::table('payment_sales')
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->whereNull('payment_sales.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.client_id', $client->id)
            ->sum('payment_sales.montant');

        // -------- ATTACH STATS TO CLIENT --------
        $client->salesGrand = $total_amount;
        $client->salesPaid = $total_paid;
        $client->sale_due = $sale_due;
        $client->return_due = $return_due;
        $client->paymentsTotal = $payments_total;
        $client->netBalance = ($client->opening_balance ?? 0) + $sale_due - $return_due;

        return response()->json($client);
    }

    public function export(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $client = Client::select('id', 'name', 'email', 'phone', 'code', 'adresse', 'country', 'city', 'state', 'zip', 'tax_number', 'opening_balance', 'credit_limit')
            ->whereNull('deleted_at')
            ->findOrFail($request->id);

        // ---------------- GLOBAL TOTALS ----------------
        $total_amount = DB::table('sales')
            ->whereNull('deleted_at')
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $total_paid = DB::table('sales')
            ->whereNull('deleted_at')
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $sale_due = $total_amount - $total_paid;

        $total_amount_return = DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $total_paid_return = DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $return_due = $total_amount_return - $total_paid_return;

        $payments_total = DB::table('payment_sales')
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->whereNull('payment_sales.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.client_id', $client->id)
            ->sum('payment_sales.montant');

        $quotations_total = DB::table('quotations')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        // Attach stats to client
        $client->salesGrand = $total_amount;
        $client->salesPaid = $total_paid;
        $client->sale_due = $sale_due;
        $client->return_due = $return_due;
        $client->paymentsTotal = $payments_total;
        $client->quotationsTotal = $quotations_total;
        $client->netBalance = ($client->opening_balance ?? 0) + $sale_due - $return_due;

        // ---------------- FULL DATA (NO FILTERS) ----------------
        $sales = Sale::with('warehouse:id,name')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->orderByDesc('id')->get();

        // Get sales payments
        $salesPayments = DB::table('payment_sales')
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'payment_sales.payment_method_id', '=', 'payment_methods.id')
            ->whereNull('payment_sales.deleted_at')
            ->where('sales.client_id', $client->id)
            ->select(
                'payment_sales.date',
                'payment_sales.Ref',
                'sales.Ref as Sale_Ref',
                'payment_methods.name as payment_method',
                'payment_sales.montant',
                DB::raw("'sale' as payment_type")
            )
            ->orderByDesc('payment_sales.id')->get();

        // Get opening balance payments
        $openingBalancePayments = DB::table('client_opening_balance_payments')
            ->join('payment_methods', 'client_opening_balance_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereNull('client_opening_balance_payments.deleted_at')
            ->where('client_opening_balance_payments.client_id', $client->id)
            ->select(
                'client_opening_balance_payments.date',
                'client_opening_balance_payments.Ref',
                DB::raw("NULL as Sale_Ref"),
                'payment_methods.name as payment_method',
                'client_opening_balance_payments.montant',
                DB::raw("'opening_balance' as payment_type")
            )
            ->orderByDesc('client_opening_balance_payments.id')->get();

        // Combine both payment types
        $payments = $salesPayments->merge($openingBalancePayments)
            ->sortByDesc(function($payment) {
                return $payment->date;
            })
            ->values();

        $quotations = Quotation::with('warehouse:id,name')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->orderByDesc('id')->get();

        $returns = SaleReturn::with(['warehouse:id,name', 'sale:id,Ref'])
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->orderByDesc('id')->get();

        // ---------------- PDF ----------------
        $settings = Setting::where('deleted_at', '=', null)->first();
        $html = view('pdf.customer_ledger', compact(
            'client', 'sales', 'payments', 'quotations', 'returns', 'settings'
        ))->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $html = substr_replace($html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = \PDF::loadHTML($html, 'UTF-8')->setPaper('a4', 'portrait');

        return $pdf->download("customer_ledger_{$client->id}.pdf");
    }

    // ---------- Quotations ----------
    public function quotationsByClient(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
            'limit' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'search' => 'sometimes|string|nullable',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $perPage = (int) ($request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $offSet = ($page - 1) * ($perPage > 0 ? $perPage : 0);

        $q = Quotation::query()
            ->with('client:id,name', 'warehouse:id,name')
            ->whereNull('deleted_at')
            ->where('client_id', $request->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('Ref', 'LIKE', "%{$s}%")
                        ->orWhere('statut', 'LIKE', "%{$s}%")
                        ->orWhereHas('warehouse', fn ($wq) => $wq->where('name', 'LIKE', "%{$s}%"))
                        ->orWhereHas('client', fn ($cq) => $cq->where('name', 'LIKE', "%{$s}%"));
                });
            });

        $totalRows = (clone $q)->count();

        if ($perPage === -1) {
            $perPage = $totalRows;
            $offSet = 0;
        }

        $rows = $q->orderByDesc('id')
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        $data = [];
        foreach ($rows as $Quotation) {
            $data[] = [
                'id' => $Quotation->id,
                'date' => $Quotation->date,
                'Ref' => $Quotation->Ref,
                'statut' => $Quotation->statut,
                'warehouse_name' => optional($Quotation->warehouse)->name,
                'client_name' => optional($Quotation->client)->name,
                'GrandTotal' => $Quotation->GrandTotal,
            ];
        }

        return response()->json([
            'quotations' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    // ---------- Returns ----------
    public function returnsByClient(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
            'limit' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'search' => 'sometimes|string|nullable',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $perPage = (int) ($request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $offSet = ($page - 1) * ($perPage > 0 ? $perPage : 0);

        $q = SaleReturn::query()
            ->with('sale:id,Ref', 'client:id,name', 'warehouse:id,name')
            ->whereNull('deleted_at')
            ->where('client_id', $request->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('Ref', 'LIKE', "%{$s}%")
                        ->orWhere('statut', 'LIKE', "%{$s}%")
                        ->orWhere('payment_statut', 'LIKE', "%{$s}%")
                        ->orWhereHas('client', fn ($cq) => $cq->where('name', 'LIKE', "%{$s}%"))
                        ->orWhereHas('sale', fn ($sq) => $sq->where('Ref', 'LIKE', "%{$s}%"))
                        ->orWhereHas('warehouse', fn ($wq) => $wq->where('name', 'LIKE', "%{$s}%"));
                });
            });

        $totalRows = (clone $q)->count();

        if ($perPage === -1) {
            $perPage = $totalRows;
            $offSet = 0;
        }

        $rows = $q->orderByDesc('id')
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id' => $r->id,
                'Ref' => $r->Ref,
                'date' => $r->date,
                'statut' => $r->statut,
                'client_name' => optional($r->client)->name,
                'sale_ref' => $r->sale ? $r->sale->Ref : '---',
                'sale_id' => $r->sale ? $r->sale->id : null,
                'warehouse_name' => optional($r->warehouse)->name,
                'GrandTotal' => $r->GrandTotal,
                'paid_amount' => $r->paid_amount,
                'due' => (float) $r->GrandTotal - (float) $r->paid_amount,
                'payment_status' => $r->payment_statut,
            ];
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns' => $data,
        ]);
    }

    /**
     * GET /api/payment_returns_client
     * Params: id (client_id, required), limit, page, search
     * Returns: { totalRows, payment_returns: [] }
     */
    public function paymentReturnsByClient(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:clients,id',
            'limit' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'search' => 'sometimes|string|nullable',
        ]);

        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $perPage = (int) ($request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $offSet = ($page - 1) * ($perPage > 0 ? $perPage : 0);

        $q = DB::table('payment_sale_returns')
            ->whereNull('payment_sale_returns.deleted_at')
            ->join('sale_returns', 'payment_sale_returns.sale_return_id', '=', 'sale_returns.id')
            ->join('payment_methods', 'payment_sale_returns.payment_method_id', '=', 'payment_methods.id')
            ->where('sale_returns.client_id', $request->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = $request->input('search');
                $query->where(function ($qr) use ($s) {
                    $qr->where('payment_sale_returns.Ref', 'LIKE', "%{$s}%")
                        ->orWhere('payment_sale_returns.date', 'LIKE', "%{$s}%")
                        ->orWhere('payment_methods.name', 'LIKE', "%{$s}%")
                        ->orWhere('sale_returns.Ref', 'LIKE', "%{$s}%");
                });
            })
            ->select(
                'payment_sale_returns.id',
                'payment_sale_returns.date',
                'payment_sale_returns.Ref',
                'sale_returns.Ref as Sale_Return_Ref',
                'payment_methods.name as payment_method',
                'payment_sale_returns.montant'
            );

        $totalRows = (clone $q)->count();

        if ($perPage === -1) {
            $perPage = $totalRows;
            $offSet = 0;
        }

        $rows = $q->orderByDesc('payment_sale_returns.id')
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        return response()->json([
            'payment_returns' => $rows,
            'totalRows' => $totalRows,
        ]);
    }
}
