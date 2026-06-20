<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\EcommerceClient;
use App\Models\StoreSetting;
use Illuminate\Http\Request;

class PendingCustomersController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $query = EcommerceClient::with('client', 'inviteCode')
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $customers = $query->paginate($request->input('per_page', 15));

        return response()->json($customers);
    }

    public function approve(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $ecomClient = EcommerceClient::findOrFail($id);
        $ecomClient->status = 1;
        $ecomClient->save();

        return response()->json([
            'success' => true,
            'message' => 'Customer approved successfully.',
            'customer' => $ecomClient->load('client'),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $ecomClient = EcommerceClient::findOrFail($id);
        $ecomClient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer registration rejected.',
        ]);
    }

    public function approveAll(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $count = EcommerceClient::where('status', 0)
            ->whereNull('deleted_at')
            ->update(['status' => 1]);

        return response()->json([
            'success' => true,
            'approved_count' => $count,
        ]);
    }
}
