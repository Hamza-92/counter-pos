<?php

namespace App\Listeners\AccountingV2;

use App\Events\SaleReturned;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateSaleReturnJournal
{
    use SkipsWhenManual;

    public function handle(SaleReturned $event): void
    {
        if ($this->shouldSkipForModel($event->saleReturn)) {
            return;
        }

        AccountingHelper::fromSaleReturn($event->saleReturn);
    }
}
