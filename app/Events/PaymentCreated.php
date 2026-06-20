<?php

namespace App\Events;

use App\Models\PaymentSale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentSale $payment;

    public function __construct(PaymentSale $payment)
    {
        $this->payment = $payment;
    }
}





