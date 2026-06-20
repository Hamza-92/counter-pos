<?php

namespace App\Events;

use App\Models\CashRegister;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashRegisterOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CashRegister $cashRegister;

    public function __construct(CashRegister $cashRegister)
    {
        $this->cashRegister = $cashRegister;
    }
}
