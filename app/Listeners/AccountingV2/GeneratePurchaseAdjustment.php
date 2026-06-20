<?php

namespace App\Listeners\AccountingV2;

use App\Events\PurchaseUpdated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePurchaseAdjustment
{
    use SkipsWhenManual;

    public function handle(PurchaseUpdated $event): void
    {
        if ($this->shouldSkipForModel($event->purchase)) {
            return;
        }

        AccountingHelper::fromPurchaseAdjustment($event->purchase);
    }
}
