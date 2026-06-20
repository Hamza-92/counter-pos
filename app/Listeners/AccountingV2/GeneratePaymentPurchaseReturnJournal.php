<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentPurchaseReturnCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentPurchaseReturnJournal
{
    use SkipsWhenManual;

    public function handle(PaymentPurchaseReturnCreated $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::fromPaymentPurchaseReturn($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
