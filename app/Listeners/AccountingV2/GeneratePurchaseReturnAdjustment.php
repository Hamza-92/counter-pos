<?php

namespace App\Listeners\AccountingV2;

use App\Events\PurchaseReturnUpdated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePurchaseReturnAdjustment
{
    use SkipsWhenManual;

    public function handle(PurchaseReturnUpdated $event): void
    {
        if ($this->shouldSkipForModel($event->purchaseReturn)) {
            return;
        }

        AccountingHelper::fromPurchaseReturnAdjustment($event->purchaseReturn);
    }
}
