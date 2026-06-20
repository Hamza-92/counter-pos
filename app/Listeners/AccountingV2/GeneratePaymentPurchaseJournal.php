<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentPurchaseCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentPurchaseJournal
{
    use SkipsWhenManual;

    public function handle(PaymentPurchaseCreated $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::fromPaymentPurchase($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
