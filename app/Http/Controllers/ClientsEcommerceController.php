<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EcommerceClient;
use App\Models\Setting;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use DB;
use Illuminate\Http\Request;

class ClientsEcommerceController extends BaseController
{

    //------------- Get ALL clients_without_ecommerce -------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'name', 1 => 'code', 2 => 'phone', 3 => 'email');
        $param = array(0 => 'like', 1 => 'like', 2 => 'like', 3 => 'like');
        $data = array();
        // $clients = Client::where('deleted_at', '=', null);
        $clients = \App\Models\Client::where('deleted_at', '=', null)
        ->whereNotIn('id', function($query){
            $query->select('client_id')->from('ecommerce_clients');
        });

        //Multiple Filter
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
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $clients = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($clients as $client) {

            $item['id'] = $client->id;
            $item['name'] = $client->name;
            $item['phone'] = $client->phone;
            $item['code'] = $client->code;
            $item['email'] = $client->email;
            $data[] = $item;
        }

        $clientsWithoutEcommerce = \App\Models\Client::where('deleted_at', '=', null)
        ->whereNotIn('id', function($query){
            $query->select('client_id')->from('ecommerce_clients');
        })->count();
        
        return response()->json([
            'clients' => $data,
            'totalRows' => $totalRows,
            'clients_without_ecommerce' => $clientsWithoutEcommerce,
        ]);
    }

    //------------- List existing ecommerce client accounts -------------\\

    public function accounts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Client::class);

        $perPage   = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet    = ($pageStart * $perPage) - $perPage;
        $order     = $request->SortField ?: 'id';
        $dir       = $request->SortType ?: 'desc';

        $query = EcommerceClient::with('client')
            ->whereNull('deleted_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'LIKE', "%{$search}%")
                  ->orWhere('username', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function ($qc) use ($search) {
                      $qc->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('code', 'LIKE', "%{$search}%")
                         ->orWhere('phone', 'LIKE', "%{$search}%");
                  });
            });
        }

        $totalRows = $query->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $accounts = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($accounts as $acc) {
            $data[] = [
                'id'         => $acc->id,
                'client_id'  => $acc->client_id,
                'client_code'=> optional($acc->client)->code,
                'client_name'=> optional($acc->client)->name,
                'phone'      => optional($acc->client)->phone,
                'email'      => $acc->email,
                'username'   => $acc->username,
                'status'     => (int) $acc->status,
            ];
        }

        return response()->json([
            'accounts'  => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //------------- Update ecommerce client account (email/password/status) -------------\\

    public function updateAccount(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        /** @var EcommerceClient $account */
        $account = EcommerceClient::whereNull('deleted_at')->findOrFail($id);

        $this->validate($request, [
            'email'    => [
                'required',
                'email',
                // Ensure email is unique in clients table (ignore linked client if exists, exclude soft-deleted)
                Rule::unique('clients', 'email')
                    ->ignore($account->client_id)
                    ->whereNull('deleted_at'),
                // Ensure email is unique in ecommerce_clients table (ignore current account, exclude soft-deleted)
                Rule::unique('ecommerce_clients', 'email')
                    ->ignore($account->id)
                    ->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'status'   => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'This Email is already taken.',
        ]);

        DB::transaction(function () use ($request, $account) {
            $account->email = $request->email;

            if ($request->filled('password')) {
                $account->password = Hash::make($request->password);
            }

            if ($request->has('status')) {
                $account->status = $request->boolean('status');
            }

            $account->save();

            // Keep linked Client email in sync
            if ($account->client_id) {
                Client::where('id', $account->client_id)->update([
                    'email' => $request->email,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    //------------- Remove ecommerce client account (without deleting Client) -------------\\

    public function destroyAccount(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Client::class);

        $account = EcommerceClient::whereNull('deleted_at')->findOrFail($id);

        // Only delete the ecommerce account; keep the core Client record
        $account->delete();

        return response()->json(['success' => true]);
    }

    //------------- Store new Customer -------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Client::class);

        // Email must be a valid email address and unique in both clients and ecommerce_clients tables
        $this->validate($request, [
            'email'    => [
                'required',
                'email',
                Rule::unique('clients', 'email')->whereNull('deleted_at')->ignore($request->client_id),
                Rule::unique('ecommerce_clients', 'email')->whereNull('deleted_at'),
            ],
            'password' => 'required|string|min:6',
        ], [
            'email.unique' => 'This Email already taken.',
        ]);

         // Check if the client_id already exists in the users table
         $client_exist = EcommerceClient::where('client_id', $request->client_id)->exists();

         if($client_exist){
            return response()->json(['success' => false] , 403);
         }else{
            $client = Client::where('id' , $request->client_id)->first();

            \DB::transaction(function () use ($request , $client) {

                EcommerceClient::create([
                    'client_id' => $request->client_id,
                    'username'  => $client->name,
                    'email'     => $request['email'],
                    'password'  => Hash::make($request['password']),
                    'status'    => 1,
                ]);
    
            }, 10);
         }


        return response()->json(['success' => true]);

    }

    //------------ function show -----------\\

    public function show($id){
        //
        
    }

    //------------- Update Customer -------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);
        
        $client_ecommerce = EcommerceClient::where('client_id', $id)
            ->whereNull('deleted_at')
            ->first();
        
        $ecommerceClientId = $client_ecommerce ? $client_ecommerce->id : null;
      
        $this->validate($request, [
            'email' => [
                'required',
                'email',
                // Ensure email is unique in clients table (ignore current client, exclude soft-deleted)
                Rule::unique('clients', 'email')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
                // Ensure email is unique in ecommerce_clients table (ignore current ecommerce_client if exists, exclude soft-deleted)
                Rule::unique('ecommerce_clients', 'email')
                    ->ignore($ecommerceClientId)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'email.unique' => 'This Email is already taken.',
        ]);


        \DB::transaction(function () use ($id , $client_ecommerce , $request) {
            $current = $client_ecommerce->password;

            if ($request->NewPassword == 'null' || $request->NewPassword === null || $request->NewPassword == '') {
                $pass = $client_ecommerce->password;
            }else{

                if ($request->NewPassword != $current) {
                    $pass = Hash::make($request->NewPassword);
                } else {
                    $pass = $client_ecommerce->password;
                }

            }
                  
            EcommerceClient::where('client_id' , $id)->update([
                'email' => $request['email'],
                'password' => $pass,
            ]);

            Client::whereId($id)->update([
                'email' => $request['email'],
            ]);

        }, 10);
        
        return response()->json(['success' => true]);

    }

    //------------- delete client -------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Client::class);

        Client::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);
        return response()->json(['success' => true]);
    }



    //------------- get Number Order Customer -------------\\

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


}
