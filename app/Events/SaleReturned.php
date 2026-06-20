<?php

namespace App\Events;

use App\Models\SaleReturn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleReturned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SaleReturn $saleReturn;

    public function __construct(SaleReturn $saleReturn)
    {
        $this->saleReturn = $saleReturn;
    }
}





