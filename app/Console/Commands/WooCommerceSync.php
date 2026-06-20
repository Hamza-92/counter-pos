<?php

namespace App\Console\Commands;

use App\Jobs\WooCommerceProductsSyncJob;
use App\Jobs\WooCommerceStockSyncJob;
use App\Models\Product;
use App\Models\SyncJob;
use App\Models\WooCommerceSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WooCommerceSync extends Command
{
    protected $signature = 'woocommerce:sync {--scope=all : products|stock|all} {--only-unsynced : For products push, sync only products without woocommerce_id}';

    protected $description = 'Run WooCommerce sync using the same jobs as manual sync (products push, stock)';

    private const ACTIVE_PRODUCTS_SYNC_TOKENS_KEY = 'woo_products_sync_active_tokens';
    private const WOO_PRODUCTS_QUEUE_PREFIX = 'woocommerce-sync-';
    private const WOO_STOCK_QUEUE_PREFIX = 'woocommerce-stock-';

    private const MAX_WAIT_SECONDS = 7200; // 2 hours max per phase

    private function progressCache()
    {
        return Cache::store('file');
    }

    private function addActiveProductsSyncToken(string $token): void
    {
        try {
            $tokens = $this->progressCache()->get(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, []);
            if (! is_array($tokens)) {
                $tokens = [];
            }
            $tokens[$token] = now()->toDateTimeString();
            $this->progressCache()->put(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, $tokens, 3600);
        } catch (\Throwable $e) {
        }
    }

    public function handle(): int
    {
        $settings = WooCommerceSetting::first();
        if (! $settings) {
            $this->warn('WooCommerce is not configured.');

            return 0;
        }

        $scope = strtolower((string) $this->option('scope'));
        $onlyUnsynced = (bool) $this->option('only-unsynced');

        if (! in_array($scope, ['products', 'stock', 'all'], true)) {
            $this->error('Invalid scope. Use: products, stock, or all');

            return 1;
        }

        $runProducts = ($scope === 'products' || $scope === 'all');
        $runStock = ($scope === 'stock' || $scope === 'all');

        if ($runProducts) {
            if ($this->runProductsPushSync($onlyUnsynced) !== 0) {
                return 1;
            }
        }

        if ($runStock) {
            if ($this->runStockSync() !== 0) {
                return 1;
            }
        }

        $settings->refresh();
        $settings->last_sync_at = now();
        $settings->save();

        $this->info('WooCommerce sync completed.');

        return 0;
    }

    /**
     * Dispatch products push job (same as manual sync) and process queue until finished.
     */
    private function runProductsPushSync(bool $onlyUnsynced): int
    {
        $total = (int) Product::whereNull('deleted_at')
            ->when($onlyUnsynced, fn ($q) => $q->whereNull('woocommerce_id'))
            ->count();

        $syncJob = SyncJob::create([
            'user_id' => null,
            'warehouse_id' => null,
            'status' => 'running',
            'total_items' => $total,
            'processed_items' => 0,
            'success_items' => 0,
            'failed_items' => 0,
            'percentage' => 0,
            'stage' => 'queued',
            'current_product_id' => null,
            'current_sku' => null,
            'last_error' => null,
            'started_at' => now(),
            'finished_at' => null,
            'cancel_requested' => false,
            'worker_heartbeat_at' => now(),
        ]);

        $token = 'woo_products_sync_'.uniqid();
        $this->progressCache()->put($token, [
            'total_products' => $total,
            'synced_products' => 0,
            'failed_products' => 0,
            'percentage' => 0,
            'created' => 0,
            'updated' => 0,
            'processed' => 0,
            'stage' => 'queued',
            'current_product_id' => null,
            'current_sku' => null,
            'sync_job_id' => (int) $syncJob->id,
            'heartbeat_at' => now()->toDateTimeString(),
            'started_at' => now()->toDateTimeString(),
            'finished' => false,
            'error' => null,
        ], 3600);

        $this->addActiveProductsSyncToken($token);

        $queue = self::WOO_PRODUCTS_QUEUE_PREFIX.(int) $syncJob->id;
        WooCommerceProductsSyncJob::dispatch($token, $onlyUnsynced, (int) $syncJob->id)
            ->onConnection('database')
            ->onQueue($queue);

        $this->info('Products push: job dispatched (same as manual sync), processing queue...');

        return $this->processQueueUntilFinished($queue, $token, 'Products push', self::MAX_WAIT_SECONDS);
    }

    /**
     * Dispatch stock sync job (same as manual sync) and process queue until finished.
     */
    private function runStockSync(): int
    {
        $total = (int) Product::whereNull('deleted_at')->whereNotNull('woocommerce_id')->count();
        $token = 'woo_stock_sync_'.uniqid();
        $queue = self::WOO_STOCK_QUEUE_PREFIX.$token;

        $this->progressCache()->put($token, [
            'total_products' => $total,
            'synced_products' => 0,
            'failed_products' => 0,
            'processed' => 0,
            'percentage' => 0,
            'stage' => 'queued',
            'queue' => $queue,
            'worker_heartbeat_at' => now()->toDateTimeString(),
            'started_at' => now()->toDateTimeString(),
            'finished' => false,
            'error' => null,
        ], 3600);

        WooCommerceStockSyncJob::dispatch($token)
            ->onConnection('database')
            ->onQueue($queue);

        $this->info('Stock sync: job dispatched (same as manual sync), processing queue...');

        return $this->processQueueUntilFinished($queue, $token, 'Stock sync', self::MAX_WAIT_SECONDS);
    }

    /**
     * Run queue:work for the given queue until the progress state is finished or timeout.
     */
    private function processQueueUntilFinished(string $queue, string $token, string $label, int $maxWaitSeconds): int
    {
        $timeout = (int) env('QUEUE_WORKER_TIMEOUT', 1200);
        $timeout = max(60, min(3600, $timeout));

        $deadline = time() + $maxWaitSeconds;
        $lastOutput = 0;

        while (time() < $deadline) {
            $state = $this->progressCache()->get($token, null);
            if (is_array($state) && ! empty($state['finished'])) {
                $error = $state['error'] ?? null;
                if ($error) {
                    $this->warn("{$label} finished with error: {$error}");
                }
                return 0;
            }

            if (! Schema::hasTable('jobs')) {
                $this->warn('Jobs table missing. Run queue worker separately.');

                return 1;
            }

            $hasJob = DB::table('jobs')->where('queue', $queue)->exists();
            if (! $hasJob) {
                // No job in queue; check state again (job may have finished and cleared queue)
                $state = $this->progressCache()->get($token, null);
                if (is_array($state) && ! empty($state['finished'])) {
                    return 0;
                }
                // Job might have re-dispatched with a slight delay; wait and retry
                sleep(2);
                continue;
            }

            Artisan::call('queue:work', [
                'connection' => 'database',
                '--once' => true,
                '--queue' => $queue,
                '--sleep' => 1,
                '--tries' => 1,
                '--timeout' => $timeout,
            ]);

            if (time() - $lastOutput >= 15) {
                $state = $this->progressCache()->get($token, []);
                $pct = (int) ($state['percentage'] ?? 0);
                $processed = (int) ($state['processed'] ?? 0);
                $total = (int) ($state['total_products'] ?? 0);
                $this->line("{$label}: {$processed}/{$total} ({$pct}%)");
                $lastOutput = time();
            }
        }

        $this->error("{$label} did not finish within {$maxWaitSeconds}s.");

        return 1;
    }
}
