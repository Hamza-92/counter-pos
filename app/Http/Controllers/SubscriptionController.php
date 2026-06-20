<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    // -------------- Get All Subscription ---------------\\

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Subscription::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;

        $subscription_data = Subscription::where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name', 'LIKE', "%{$request->search}%");
            });
        });

        $totalRows = $subscription_data->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $subscriptions = $subscription_data->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($subscriptions as $subscription) {

            $item['id'] = $subscription->id;
            $item['client_name'] = $subscription['client'] ? $subscription['client']->name : '---';
            $item['product_name'] = $subscription['product'] ? $subscription['product']->name : '---';
            $item['warehouse_name'] = $subscription['warehouse'] ? $subscription['warehouse']->name : '---';
            $item['billing_cycle'] = $subscription->billing_cycle;
            $item['total_cycles'] = $subscription->total_cycles.' '.$subscription->cycle_type;
            $item['price'] = $subscription->price;
            $item['remaining_cycles'] = $subscription->remaining_cycles;
            $item['next_billing_date'] = $subscription->next_billing_date;
            $item['status'] = $subscription->status;

            $data[] = $item;
        }

        return response()->json([
            'subscriptions' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    // ------------ function create -----------\\

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Subscription::class);

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $products = Product::where('deleted_at', '=', null)->get(['id', 'name']);
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'clients' => $clients,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);

    }

    // -------------- Store New  ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Subscription::class);

        $validatedData = $request->validate([
            'subscription.client_id' => 'required|exists:clients,id',
            'subscription.product_id' => 'required|exists:products,id',
            'subscription.warehouse_id' => 'required|exists:warehouses,id',
            'subscription.total_cycles' => 'required|integer|min:1',
            'subscription.cycle_type' => 'required|in:weekly,monthly,yearly',
            'subscription.billing_cycle' => 'required|in:weekly,monthly,yearly',
            'subscription.price_per_cycle' => 'required|numeric|min:0',
            'subscription.price_per_unit' => 'required|numeric|min:0',
            'subscription.quantity' => 'required|integer|min:1',
            'subscription.next_billing_date' => 'required|date',
            'subscription.status' => 'required|in:active,canceled,completed',

        ]);

        $data = $validatedData['subscription'];

        // Calculate remaining cycles
        $remaining_cycles = $this->calculateRemainingCycles(
            $data['total_cycles'],
            $data['cycle_type'],
            $data['billing_cycle']
        );

        // Create new subscription
        $subscription = Subscription::create([
            'date' => now(),
            'user_id' => auth()->id(),
            'client_id' => $data['client_id'],
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'total_cycles' => $data['total_cycles'],
            'cycle_type' => $data['cycle_type'],
            'billing_cycle' => $data['billing_cycle'],
            'remaining_cycles' => $remaining_cycles,
            'price_per_cycle' => $data['price_per_cycle'],
            'price_per_unit' => $data['price_per_unit'],
            'quantity' => $data['quantity'],
            'next_billing_date' => $data['next_billing_date'],
            'status' => $data['status'],
        ]);

        // Check if next billing date is today, generate invoice immediately
        if (Carbon::parse($subscription->next_billing_date)->isToday()) {
            $subscription->generateInvoice();
        }

        return response()->json([
            'message' => 'Subscription created successfully!',
            'subscription' => $subscription,
        ], 201);
    }

    private function calculateRemainingCycles($total_cycles, $cycle_type, $billing_cycle)
    {
        // Define durations in days
        $durations = [
            'yearly' => 365,
            'monthly' => 30.4375, // Average month duration
            'weekly' => 7,
        ];

        // Get the total duration in days based on the cycle type (how long the subscription lasts)
        $total_duration = $total_cycles * ($durations[$cycle_type] ?? 1);

        // Get the billing cycle duration in days (how often the customer pays)
        $billing_duration = $durations[$billing_cycle] ?? 1;

        // Calculate how many payments the user needs to make
        $remaining_cycles = intval($total_duration / $billing_duration);

        return max($remaining_cycles, 1); // Ensure at least 1 cycle
    }

    // ------------ function show -----------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Subscription::class);

        $subscription = Subscription::with(['client', 'product', 'warehouse', 'invoices'])->findOrFail($id);

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'client' => $subscription->client->name,
                'product' => $subscription->product->name,
                'warehouse' => $subscription->warehouse->name,
                'billing_cycle' => $subscription->billing_cycle,
                'price_per_cycle' => number_format($subscription->price_per_cycle, 2, '.', ','),
                'next_billing_date' => $subscription->next_billing_date,
                'status' => $subscription->status,
            ],
            'invoices' => $subscription->invoices->map(function ($invoice) {
                return [
                    'sale_id' => $invoice->id,
                    'ref' => $invoice->Ref,
                    'date' => $invoice->date,
                    'total' => number_format($invoice->GrandTotal, 2, '.', ','),
                    'status' => $invoice->payment_statut,
                ];
            }),
            'invoiceFields' => [
                ['key' => 'ref', 'label' => 'Invoice Ref'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'total', 'label' => 'Total Amount'],
                ['key' => 'status', 'label' => 'Status'],
            ],
        ]);
    }

    // -------------- Update ---------------\\

    public function update(Request $request, $id)
    {
        //

    }

    // -------------- Delete Subscription ---------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Subscription::class);

        Subscription::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,canceled,completed',
        ]);

        $subscription = Subscription::findOrFail($id);
        $subscription->update([
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Subscription status updated successfully!']);
    }
}
