<?php

namespace App\Listeners\AccountingV2;

use App\Events\SaleCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateSaleJournal
{
    use SkipsWhenManual;

    public function handle(SaleCreated $event): void
    {
        if ($this->shouldSkipForModel($event->sale)) {
            return;
        }

        AccountingHelper::fromSale($event->sale);
    }
}
