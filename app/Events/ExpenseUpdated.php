<?php

namespace App\Events;

use App\Models\Expense;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Expense $expense;

    public function __construct(Expense $expense)
    {
        $this->expense = $expense;
    }
}





