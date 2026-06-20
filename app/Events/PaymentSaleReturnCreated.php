<?php

namespace App\Events;

use App\Models\PaymentSaleReturns;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSaleReturnCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentSaleReturns $payment;

    public function __construct(PaymentSaleReturns $payment)
    {
        $this->payment = $payment;
    }
}
