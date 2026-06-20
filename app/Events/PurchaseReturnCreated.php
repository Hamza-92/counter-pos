<?php

namespace App\Events;

use App\Models\PurchaseReturn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReturnCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PurchaseReturn $purchaseReturn;

    public function __construct(PurchaseReturn $purchaseReturn)
    {
        $this->purchaseReturn = $purchaseReturn;
    }
}
