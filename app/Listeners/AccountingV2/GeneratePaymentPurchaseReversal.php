<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentPurchaseDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentPurchaseReversal
{
    use SkipsWhenManual;

    public function handle(PaymentPurchaseDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::reversePaymentPurchase($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
