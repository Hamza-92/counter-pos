<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentPurchaseReturnDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentPurchaseReturnReversal
{
    use SkipsWhenManual;

    public function handle(PaymentPurchaseReturnDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::reversePaymentPurchaseReturn($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
