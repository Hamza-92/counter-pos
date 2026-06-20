<?php

namespace App\Listeners\Webhooks;

use App\Services\Webhooks\Payload;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class DispatchWebhooks implements ShouldQueue
{
    /** Queue the listener so webhook dispatching never blocks the request. */
    public $queue = 'webhooks';

    /**
     * Map internal event FQCNs to the canonical webhook event names
     * exposed to subscribers.
     */
    public const EVENT_MAP = [
        \App\Events\SaleCreated::class                    => 'sale.created',
        \App\Events\SaleUpdated::class                    => 'sale.updated',
        \App\Events\SaleDeleted::class                    => 'sale.deleted',
        \App\Events\SaleReturned::class                   => 'sale_return.created',
        \App\Events\SaleReturnUpdated::class              => 'sale_return.updated',
        \App\Events\SaleReturnDeleted::class              => 'sale_return.deleted',
        \App\Events\PurchaseCreated::class                => 'purchase.created',
        \App\Events\PurchaseUpdated::class                => 'purchase.updated',
        \App\Events\PurchaseDeleted::class                => 'purchase.deleted',
        \App\Events\PurchaseReturnCreated::class          => 'purchase_return.created',
        \App\Events\PurchaseReturnUpdated::class          => 'purchase_return.updated',
        \App\Events\PurchaseReturnDeleted::class          => 'purchase_return.deleted',
        \App\Events\PaymentCreated::class                 => 'payment.created',
        \App\Events\PaymentDeleted::class                 => 'payment.deleted',
        \App\Events\PaymentPurchaseCreated::class         => 'payment_purchase.created',
        \App\Events\PaymentPurchaseDeleted::class         => 'payment_purchase.deleted',
        \App\Events\PaymentSaleReturnCreated::class       => 'payment_sale_return.created',
        \App\Events\PaymentSaleReturnDeleted::class       => 'payment_sale_return.deleted',
        \App\Events\PaymentPurchaseReturnCreated::class   => 'payment_purchase_return.created',
        \App\Events\PaymentPurchaseReturnDeleted::class   => 'payment_purchase_return.deleted',
        \App\Events\ExpenseCreated::class                 => 'expense.created',
        \App\Events\ExpenseUpdated::class                 => 'expense.updated',
        \App\Events\ExpenseDeleted::class                 => 'expense.deleted',
        \App\Events\CashRegisterOpened::class             => 'cash_register.opened',
    ];

    public function __construct(protected WebhookDispatcher $dispatcher)
    {
    }

    public function handle(object $event): void
    {
        try {
            $eventName = self::EVENT_MAP[get_class($event)] ?? null;
            if (!$eventName) {
                return;
            }

            $model = $this->extractModel($event);
            $payload = $model instanceof Model
                ? Payload::fromModel($model)
                : ['attributes' => (array) $event];

            $this->dispatcher->dispatch($eventName, $payload);
        } catch (Throwable $e) {
            // Never break the originating flow.
            report($e);
        }
    }

    protected function extractModel(object $event): ?Model
    {
        foreach (get_object_vars($event) as $value) {
            if ($value instanceof Model) {
                return $value;
            }
        }
        return null;
    }
}
