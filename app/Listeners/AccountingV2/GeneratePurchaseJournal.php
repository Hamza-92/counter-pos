<?php

namespace App\Listeners\AccountingV2;

use App\Events\PurchaseCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePurchaseJournal
{
    use SkipsWhenManual;

    public function handle(PurchaseCreated $event): void
    {
        if ($this->shouldSkipForModel($event->purchase)) {
            return;
        }

        AccountingHelper::fromPurchase($event->purchase);
    }
}
