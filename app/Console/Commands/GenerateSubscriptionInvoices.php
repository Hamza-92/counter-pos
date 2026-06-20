<?php

namespace App\Console\Commands;

use App\Models\ErrorLog;
use App\Models\PaymentSale;
use App\Models\product_warehouse;
use App\Models\Subscription;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateSubscriptionInvoices extends Command
{
    protected $signature = 'subscriptions:generate-invoices';

    protected $description = 'Generate invoices and auto-charge clients via Flutterwave';

    public function handle()
    {
        $today = Carbon::today();

        $subscriptions = Subscription::whereDate('next_billing_date', '<=', $today)
            ->where('remaining_cycles', '>', 0)
            ->where('status', 'active')
            ->get();

        foreach ($subscriptions as $subscription) {
            // 1. Generate invoice
            $invoice = $subscription->generateInvoice();

            PaymentSale::create([
                'sale_id' => $invoice->id,
                'date' => now(),
                'montant' => $invoice->GrandTotal,
                'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                'change' => 0,
                'Reglement' => 'flutterwave',
                'user_id' => $invoice->user_id ?? 1,
                'notes' => 'Auto payment for subscription #'.$subscription->id,
            ]);

            // 2. Decrease product stock
            $productWarehouse = product_warehouse::where('warehouse_id', $subscription->warehouse_id)
                ->where('product_id', $subscription->product_id)
                ->whereNull('deleted_at')
                ->first();

            if ($productWarehouse) {
                $unit = Unit::find($subscription->unit_id);
                $operatorValue = $unit ? $unit->operator_value : 1;
                $productWarehouse->qte -= ($subscription->quantity / $operatorValue);
                $productWarehouse->save();
            }

            // 4. Send SMS on successful charge

            try {
                app('App\Http\Controllers\SalesController')->Send_Subscription_Payment_Success_SMS($subscription->id, $invoice->id);
                Log::info("SMS sent after successful payment for subscription #{$subscription->id}");
            } catch (\Exception $e) {
                Log::error("Failed sending SMS for subscription #{$subscription->id}: ".$e->getMessage());

                ErrorLog::create([
                    'context' => 'SMS after auto-charge success',
                    'message' => "Failed sending SMS for subscription #{$subscription->id}",
                    'details' => json_encode([
                        'subscription_id' => $subscription->id,
                        'client_id' => $subscription->client->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]),
                ]);
            }

            // 4. Update subscription
            $subscription->remaining_cycles -= 1;

            if ($subscription->remaining_cycles <= 0) {
                $subscription->status = 'completed';
            }

            $subscription->next_billing_date = match ($subscription->billing_cycle) {
                'weekly' => Carbon::parse($subscription->next_billing_date)->addWeek(),
                'monthly' => Carbon::parse($subscription->next_billing_date)->addMonth(),
                'yearly' => Carbon::parse($subscription->next_billing_date)->addYear(),
            };

            $subscription->save();
        }

        $this->info('✅ Subscription invoices generated and processed.');
    }
}
