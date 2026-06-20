<?php

namespace App\Listeners\AccountingV2;

use App\Events\PaymentCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GeneratePaymentSaleJournal
{
    use SkipsWhenManual;

    public function handle(PaymentCreated $event): void
    {
        if ($this->shouldSkipForModel($event->payment)) {
            return;
        }

        try {
            AccountingHelper::fromPaymentSale($event->payment);
        } catch (\Throwable $e) {
            // swallow to avoid breaking the request flow
        }
    }
}
