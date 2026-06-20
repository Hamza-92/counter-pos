<?php

namespace App\Events;

use App\Models\PaymentPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPurchaseCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentPurchase $payment;

    public function __construct(PaymentPurchase $payment)
    {
        $this->payment = $payment;
    }
}





