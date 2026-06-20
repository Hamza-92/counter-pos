<?php

namespace App\Events;

use App\Models\PaymentPurchaseReturns;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPurchaseReturnCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentPurchaseReturns $payment;

    public function __construct(PaymentPurchaseReturns $payment)
    {
        $this->payment = $payment;
    }
}
