<?php

// app/Jobs/SyncSaleToQuickBooks.php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\QuickBooksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncSaleToQuickBooks implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $saleId, public ?string $realmId = null) {}

    public function handle(QuickBooksService $qb): void
    {
        $sale = Sale::with(['saleDetails.product', 'client'])->find($this->saleId);
        if (! $sale) {
            return;
        }

        // Let the service resolve if we don’t have a specific one
        $res = $qb->createInvoice($sale, $this->realmId ?: $sale->quickbooks_realm_id);

        if ($res['ok'] ?? false) {
            $sale->update([
                'quickbooks_invoice_id' => $res['id'],
                'quickbooks_realm_id' => $res['realm'] ?? $this->realmId ?: $sale->quickbooks_realm_id,
                'quickbooks_synced_at' => now(),
                'quickbooks_sync_error' => null,
            ]);
        } else {
            $sale->update([
                'quickbooks_sync_error' => ($res['error'] ?? 'Unknown error')
                    .(isset($res['http']) ? " (HTTP {$res['http']})" : '')
                    .(isset($res['body']) ? " :: {$res['body']}" : ''),
            ]);
        }
    }
}
