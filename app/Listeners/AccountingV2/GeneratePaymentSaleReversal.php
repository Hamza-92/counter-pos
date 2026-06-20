<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentSaleReversal
{
    use SkipsWhenManual;

    public function handle(PaymentDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::reversePaymentSale($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
