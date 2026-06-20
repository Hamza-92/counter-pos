<?php

namespace App\Listeners\AccountingV2;

use App\Events\SaleUpdated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateSaleAdjustment
{
    use SkipsWhenManual;

    public function handle(SaleUpdated $event): void
    {
        if ($this->shouldSkipForModel($event->sale)) {
            return;
        }

        AccountingHelper::fromSaleAdjustment($event->sale);
    }
}
