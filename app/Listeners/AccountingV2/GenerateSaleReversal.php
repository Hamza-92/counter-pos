<?php

namespace App\Listeners\AccountingV2;

use App\Events\SaleDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateSaleReversal
{
    use SkipsWhenManual;

    public function handle(SaleDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->sale)) {
            return;
        }

        AccountingHelper::reverseSale($event->sale);
    }
}
