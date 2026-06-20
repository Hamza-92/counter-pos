<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendSubscriptionSmsReminders extends Command
{
    protected $signature = 'subscriptions:send-sms-reminders';

    protected $description = 'Send SMS reminders before subscription renewal';

    public function handle()
    {

        $subscriptions = Subscription::whereIn('next_billing_date', [
            Carbon::today()->addDays(7),
            Carbon::today()->addDays(3),
        ])->where('remaining_cycles', '>', 0)->where('status', 'active')->get();

        foreach ($subscriptions as $subscription) {
            app('App\Http\Controllers\SalesController')->Send_Subscription_Reminder_SMS($subscription->id);
        }

    }
}
