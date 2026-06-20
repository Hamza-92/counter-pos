<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentSaleReturnDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentSaleReturnReversal
{
    use SkipsWhenManual;

    public function handle(PaymentSaleReturnDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::reversePaymentSaleReturn($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
