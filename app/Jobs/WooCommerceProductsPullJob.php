<?php

namespace App\Jobs;

use App\Models\SyncJob;
use App\Models\WooCommerceSetting;
use App\Services\WooCommerce\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WooCommerceProductsPullJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 1;

    private string $progressKey;
    private bool $onlyUnsynced;
    private ?int $syncJobId;

    private const CANCELLED_EXCEPTION = '__WOO_PRODUCTS_PULL_CANCELLED__';

    public function __construct(string $progressKey, bool $onlyUnsynced = false, ?int $syncJobId = null)
    {
        $this->progressKey = $progressKey;
        $this->onlyUnsynced = $onlyUnsynced;
        $this->syncJobId = $syncJobId;
    }

    public function handle(): void
    {
        $cache = Cache::store('file');

        ini_set('max_execution_time', '2000');
        ini_set('memory_limit', '512M');

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            $this->failJob($cache, 'WooCommerce settings missing');
            return;
        }

        $state = $cache->get($this->progressKey, null);
        if (! is_array($state) || ! empty($state['finished'])) {
            $state = [
                'total_products' => 0,
                'synced_products' => 0,
                'failed_products' => 0,
                'percentage' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'stage' => 'queued',
                'cursor_page' => 1,
                'cursor_index' => 0,
                'started_at' => now()->toDateTimeString(),
                'finished' => false,
                'error' => null,
            ];
            $cache->put($this->progressKey, $state, 3600);
        }

        $cancelKey = $this->progressKey.':cancel';
        if ((bool) $cache->get($cancelKey, false)) {
            $state['cancelled'] = true;
            $state['cancelled_at'] = now()->toDateTimeString();
            $state['finished'] = true;
            $state['finished_at'] = now()->toDateTimeString();
            $state['error'] = 'cancelled';
            $cache->put($this->progressKey, $state, 3600);
            $cache->forget($cancelKey);
            return;
        }

        $dbJob = null;
        if ($this->syncJobId) {
            $dbJob = SyncJob::query()->find($this->syncJobId);
            if ($dbJob) {
                $dbJob->status = 'running';
                $dbJob->stage = 'running';
                $dbJob->started_at = $dbJob->started_at ?: now();
                $dbJob->worker_heartbeat_at = now();
                $dbJob->save();
            }
        }

        $shouldCancel = function () use (&$state, $cache, $cancelKey) {
            if ((bool) $cache->get($cancelKey, false)) {
                $state['cancelled'] = true;
                $state['cancelled_at'] = now()->toDateTimeString();
                $cache->put($this->progressKey, $state, 3600);
                throw new \RuntimeException(self::CANCELLED_EXCEPTION);
            }
            if ($this->syncJobId) {
                $sj = SyncJob::query()->find($this->syncJobId);
                if ($sj && ((bool) $sj->cancel_requested || in_array((string) $sj->status, ['cancelled', 'failed'], true))) {
                    $state['cancelled'] = true;
                    $state['cancelled_at'] = now()->toDateTimeString();
                    $cache->put($this->progressKey, $state, 3600);
                    throw new \RuntimeException(self::CANCELLED_EXCEPTION);
                }
            }
        };

        try {
            $sync = SyncService::fromSettings($settings);

            $batchSize = (int) env('WOO_PRODUCTS_PER_JOB', 5);
            $batchSize = max(1, min(200, $batchSize));

            $cursorPage = (int) ($state['cursor_page'] ?? 1);
            $cursorIndex = (int) ($state['cursor_index'] ?? 0);

            $result = $sync->pullProducts(
                $this->onlyUnsynced,
                function (array $p) use (&$state, $cache) {
                    $state['stage'] = (string) ($p['stage'] ?? 'pulling');
                    $state['worker_heartbeat_at'] = (string) ($p['worker_heartbeat_at'] ?? now()->toDateTimeString());
                    if (isset($p['current_sku'])) {
                        $state['current_sku'] = (string) $p['current_sku'];
                    }
                    if (isset($p['current_woocommerce_id'])) {
                        $state['current_woocommerce_id'] = (int) $p['current_woocommerce_id'];
                    }
                    $cache->put($this->progressKey, $state, 3600);
                },
                $shouldCancel,
                $batchSize,
                $cursorPage,
                $cursorIndex
            );

            $state['created'] = (int) ($state['created'] ?? 0) + (int) ($result['created'] ?? 0);
            $state['updated'] = (int) ($state['updated'] ?? 0) + (int) ($result['updated'] ?? 0);
            $state['failed_products'] = (int) ($state['failed_products'] ?? 0) + (int) ($result['errors'] ?? 0);
            $state['processed'] = (int) ($state['processed'] ?? 0) + (int) ($result['processed'] ?? 0);
            $state['synced_products'] = (int) $state['created'] + (int) $state['updated'];

            if (!empty($result['remote_total'])) {
                $state['total_products'] = (int) $result['remote_total'];
            }

            $state['cursor_page'] = (int) ($result['cursor_page'] ?? $cursorPage);
            $state['cursor_index'] = (int) ($result['cursor_index'] ?? $cursorIndex);

            $total = (int) ($state['total_products'] ?? 0);
            $proc = (int) ($state['processed'] ?? 0);
            $state['percentage'] = $total > 0 ? (int) min(100, round(($proc / $total) * 100)) : 0;
            $state['heartbeat_at'] = now()->toDateTimeString();
            $state['worker_heartbeat_at'] = now()->toDateTimeString();

            $done = (bool) ($result['done'] ?? false);
            if ($done) {
                $state['finished'] = true;
                $state['finished_at'] = now()->toDateTimeString();
                $state['stage'] = 'finished';
            } else {
                $state['stage'] = 'queued_next_batch';
            }

            $cache->put($this->progressKey, $state, 3600);

            if ($dbJob) {
                $dbJob->total_items = $total > 0 ? $total : $dbJob->total_items;
                $dbJob->processed_items = $proc;
                $dbJob->success_items = (int) $state['synced_products'];
                $dbJob->failed_items = (int) $state['failed_products'];
                $dbJob->percentage = (int) $state['percentage'];
                $dbJob->stage = (string) $state['stage'];
                $dbJob->worker_heartbeat_at = now();
                if ($done) {
                    $dbJob->status = 'completed';
                    $dbJob->finished_at = now();
                }
                $dbJob->save();
            }

            // If not done, queue the next batch on the same dedicated queue.
            // Without this, the job can get stuck in stage=queued_next_batch with no queued work.
            if (!$done) {
                $queue = $this->syncJobId ? ('woocommerce-sync-'.(int) $this->syncJobId) : 'default';
                self::dispatch($this->progressKey, $this->onlyUnsynced, $this->syncJobId)
                    ->onConnection('database')
                    ->onQueue($queue);
            }
        } catch (\Throwable $e) {
            if ($e->getMessage() === self::CANCELLED_EXCEPTION) {
                return;
            }
            $this->failJob($cache, $e->getMessage());
        }
    }

    private function failJob($cache, string $error): void
    {
        $state = $cache->get($this->progressKey, []);
        if (!is_array($state)) {
            $state = [];
        }
        $state['finished'] = true;
        $state['finished_at'] = now()->toDateTimeString();
        $state['error'] = $error;
        $state['stage'] = 'failed';
        $cache->put($this->progressKey, $state, 3600);

        if ($this->syncJobId) {
            $dbJob = SyncJob::query()->find($this->syncJobId);
            if ($dbJob) {
                $dbJob->status = 'failed';
                $dbJob->last_error = $error;
                $dbJob->finished_at = now();
                $dbJob->save();
            }
        }
    }
}

