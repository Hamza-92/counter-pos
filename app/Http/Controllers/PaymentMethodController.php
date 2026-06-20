<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentMethodController extends BaseController
{
    // -------------- Get All methods ---------------\\

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PaymentMethod::class);
        // How many items do you want to display.
        $perPage = $request->limit ?: 10;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = $request->SortType ?: 'desc';
        $helpers = new helpers;

        $methods = PaymentMethod::where('deleted_at', '=', null)

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $methods->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $methods = $methods->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        return response()->json([
            'methods' => $methods,
            'totalRows' => $totalRows,
        ]);
    }

    // -------------- Store New PaymentMethod ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentMethod::class);

        request()->validate([
            'name' => 'required',
        ]);

        PaymentMethod::create([
            'name' => $request['name'],
        ]);

        return response()->json(['success' => true]);
    }

    // ------------ function show -----------\\

    public function show($id)
    {
        //

    }

    // -------------- Update PaymentMethod ---------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', PaymentMethod::class);

        request()->validate([
            'name' => 'required',
        ]);

        PaymentMethod::whereId($id)->update([
            'name' => $request['name'],
        ]);

        return response()->json(['success' => true]);

    }

    // -------------- Remove PaymentMethod ---------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PaymentMethod::class);

        PaymentMethod::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);
    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Category::class);
        $selectedIds = $request->selectedIds;

        foreach ($selectedIds as $payment_id) {
            PaymentMethod::whereId($payment_id)->update([
                'deleted_at' => Carbon::now(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
