<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentSaleReturnCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentSaleReturnJournal
{
    use SkipsWhenManual;

    public function handle(PaymentSaleReturnCreated $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::fromPaymentSaleReturn($event->payment);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
