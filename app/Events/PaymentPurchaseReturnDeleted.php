<?php

namespace App\Events;

use App\Models\PaymentPurchaseReturns;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPurchaseReturnDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentPurchaseReturns $payment;

    public function __construct(PaymentPurchaseReturns $payment)
    {
        $this->payment = $payment;
    }
}
