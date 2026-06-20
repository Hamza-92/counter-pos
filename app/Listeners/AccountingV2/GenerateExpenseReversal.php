<?php

namespace App\Listeners\AccountingV2;

use App\Events\ExpenseDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateExpenseReversal
{
    use SkipsWhenManual;

    public function handle(ExpenseDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->expense)) {
            return;
        }

        AccountingHelper::reverseExpense($event->expense);
    }
}
