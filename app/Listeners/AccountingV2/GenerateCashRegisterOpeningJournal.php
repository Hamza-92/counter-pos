<?php

namespace App\Listeners\AccountingV2;

use App\Events\CashRegisterOpened;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateCashRegisterOpeningJournal
{
    use SkipsWhenManual;

    public function handle(CashRegisterOpened $event): void
    {
        if ($this->shouldSkipForModel($event->cashRegister)) {
            return;
        }

        AccountingHelper::fromCashRegisterOpening($event->cashRegister);
    }
}
