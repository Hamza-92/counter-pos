<?php

namespace App\Listeners\AccountingV2;

use App\Events\PurchaseReturnDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePurchaseReturnReversal
{
    use SkipsWhenManual;

    public function handle(PurchaseReturnDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->purchaseReturn)) {
            return;
        }

        AccountingHelper::reversePurchaseReturn($event->purchaseReturn);
    }
}
