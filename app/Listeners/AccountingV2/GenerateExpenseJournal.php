<?php

namespace App\Listeners\AccountingV2;

use App\Events\ExpenseCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateExpenseJournal
{
    use SkipsWhenManual;

    public function handle(ExpenseCreated $event): void
    {
        if ($this->shouldSkipForModel($event->expense)) {
            return;
        }

        AccountingHelper::fromExpense($event->expense);
    }
}
