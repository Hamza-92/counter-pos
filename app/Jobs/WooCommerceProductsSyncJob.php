<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncJob;
use App\Models\WooCommerceLog;
use App\Models\WooCommerceSetting;
use App\Services\WooCommerce\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WooCommerceProductsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Shared hosting safety:
     * keep each job run short; if your worker uses --timeout this should override it.
     */
    public int $timeout = 1200;
    public int $tries = 1;

    private const ACTIVE_PRODUCTS_SYNC_TOKENS_KEY = 'woo_products_sync_active_tokens';

    private string $progressKey;

    private bool $onlyUnsynced;

    private ?int $syncJobId;

    private const CANCELLED_EXCEPTION = '__WOO_PRODUCTS_SYNC_CANCELLED__';

    public function __construct(string $progressKey, bool $onlyUnsynced = false, ?int $syncJobId = null)
    {
        $this->progressKey = $progressKey;
        $this->onlyUnsynced = $onlyUnsynced;
        $this->syncJobId = $syncJobId;
    }

    public function handle(): void
    {
        $cache = Cache::store('file');
        $finalize = true;

        // When running via dispatchAfterResponse() (sync driver), this may still execute
        // under the web SAPI. Increase limits defensively.
        ini_set('max_execution_time', '2000');
        ini_set('memory_limit', '512M');

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            $this->failJob('WooCommerce settings missing');

            return;
        }

        $query = Product::whereNull('deleted_at');
        if ($this->onlyUnsynced) {
            $query->whereNull('woocommerce_id');
        }
        $total = (int) $query->count();

        // Keep state between batches; do NOT reset counters on every run.
        $state = $cache->get($this->progressKey, null);
        if (!is_array($state) || !empty($state['finished'])) {
            $state = [
                'total_products' => $total,
                'synced_products' => 0,
                'failed_products' => 0,
                'percentage' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'started_at' => now()->toDateTimeString(),
                'finished' => false,
                'error' => null,
            ];
            $cache->put($this->progressKey, $state, 3600);
        } else {
            // Ensure total is present (first run might have been older format)
            if (!isset($state['total_products'])) {
                $state['total_products'] = $total;
                $cache->put($this->progressKey, $state, 3600);
            }
        }

        $cancelKey = $this->progressKey.':cancel';
        $cancelled = (bool) $cache->get($cancelKey, false);
        if ($cancelled) {
            $state['cancelled'] = true;
            $state['cancelled_at'] = now()->toDateTimeString();
            $state['finished'] = true;
            $state['finished_at'] = now()->toDateTimeString();
            $state['error'] = 'cancelled';
            $cache->put($this->progressKey, $state, 3600);
            $cache->forget($cancelKey);
            return;
        }

        try {
            $sync = SyncService::fromSettings($settings);

            $dbJob = null;
            if ($this->syncJobId) {
                $dbJob = SyncJob::query()->find($this->syncJobId);
                if ($dbJob) {
                    $dbJob->status = 'running';
                    // Always set stage to running at job start (prevents UI staying on "queued_next_batch").
                    $dbJob->stage = 'running';
                    $dbJob->started_at = $dbJob->started_at ?: now();
                    $dbJob->worker_heartbeat_at = now();
                    $dbJob->save();
                }
            }

            $shouldCancel = function () use (&$state, &$cancelled, $cancelKey, $cache) {
                // DB cancel request
                if ($this->syncJobId) {
                    $sj = SyncJob::query()->find($this->syncJobId);
                    if ($sj && (in_array((string) $sj->status, ['cancelled', 'failed'], true) || (bool) $sj->cancel_requested)) {
                        $cancelled = true;
                        $state['cancelled'] = true;
                        $state['cancelled_at'] = now()->toDateTimeString();
                        $cache->put($this->progressKey, $state, 3600);
                        throw new \RuntimeException(self::CANCELLED_EXCEPTION);
                    }
                }
                if ((bool) $cache->get($cancelKey, false)) {
                    $cancelled = true;
                    $state['cancelled'] = true;
                    $state['cancelled_at'] = now()->toDateTimeString();
                    $cache->put($this->progressKey, $state, 3600);
                    throw new \RuntimeException(self::CANCELLED_EXCEPTION);
                }
            };

            $lastDoneProductId = null;
            $prevCreated = 0;
            $prevUpdated = 0;
            $prevErrors  = 0;
            $lastStageForProduct = null;

            $batchSize = (int) env('WOO_PRODUCTS_PER_JOB', 5);
            $batchSize = max(1, min(100, $batchSize));
            $startAfterId = (int) ($state['last_product_id'] ?? 0);

            $result = $sync->pushProducts($this->onlyUnsynced, function (array $p) use (
                &$state, &$cancelled, $cancelKey, $cache,
                &$lastDoneProductId, &$prevCreated, &$prevUpdated, &$prevErrors, &$lastStageForProduct
            ) {
                if ((bool) $cache->get($cancelKey, false)) {
                    $cancelled = true;
                    $state['cancelled'] = true;
                    $state['cancelled_at'] = now()->toDateTimeString();
                    $cache->put($this->progressKey, $state, 3600);
                    throw new \RuntimeException(self::CANCELLED_EXCEPTION);
                }

                $created = (int) ($p['created'] ?? 0);
                $updated = (int) ($p['updated'] ?? 0);
                $errors = (int) ($p['errors'] ?? 0);
                $currentId = isset($p['current_product_id']) ? (int) $p['current_product_id'] : null;
                $currentSku = isset($p['current_sku']) ? (string) $p['current_sku'] : null;
                $lastId = isset($p['last_product_id']) ? (int) $p['last_product_id'] : null;
                $lastSku = isset($p['last_sku']) ? (string) $p['last_sku'] : null;
                $stage = isset($p['stage']) ? (string) $p['stage'] : null;
                $variantSku = isset($p['variant_sku']) ? (string) $p['variant_sku'] : null;
                $variantIndex = isset($p['variant_index']) ? (int) $p['variant_index'] : null;
                $variantsTotal = isset($p['variants_total']) ? (int) $p['variants_total'] : null;
                $variantsPage = isset($p['variants_page']) ? (int) $p['variants_page'] : null;
                $attempt = isset($p['attempt']) ? (int) $p['attempt'] : null;
                $maxAttempts = isset($p['max_attempts']) ? (int) $p['max_attempts'] : null;
                $batchCreate = isset($p['batch_create']) ? (int) $p['batch_create'] : null;
                $batchUpdate = isset($p['batch_update']) ? (int) $p['batch_update'] : null;
                $verifiedCount = isset($p['verified_count']) ? (int) $p['verified_count'] : null;
                $missingCount = isset($p['missing_count']) ? (int) $p['missing_count'] : null;
                $status = isset($p['status']) ? $p['status'] : null;
                $stageStartedAt = $p['stage_started_at'] ?? null;
                $lastHttpStartedAt = $p['last_http_started_at'] ?? null;
                $lastHttpDurationMs = isset($p['last_http_duration_ms']) ? (int) $p['last_http_duration_ms'] : null;
                $lastHttpErrorType = $p['last_http_error_type'] ?? null;
                $lastHttpErrorMessage = $p['last_http_error_message'] ?? null;
                $workerHeartbeatAt = $p['worker_heartbeat_at'] ?? null;
                $substep = $p['substep'] ?? null;
                $substepAt = $p['substep_at'] ?? null;
                $lastEndpoint = $p['last_endpoint'] ?? null;
                $lastPayloadBytes = isset($p['last_payload_bytes']) ? (int) $p['last_payload_bytes'] : null;

                $state['created'] = $created;
                $state['updated'] = $updated;
                $state['synced_products'] = $created + $updated;
                $state['failed_products'] = $errors;
                if ($currentId) {
                    $state['current_product_id'] = $currentId;
                }
                if ($currentSku !== null) {
                    $state['current_sku'] = $currentSku;
                }
                if ($lastId) {
                    $state['last_product_id'] = $lastId;
                }
                if ($lastSku !== null) {
                    $state['last_sku'] = $lastSku;
                }
                if ($stage !== null) {
                    $state['stage'] = $stage;
                }
                if ($variantSku !== null) {
                    $state['variant_sku'] = $variantSku;
                }
                if ($variantIndex !== null) {
                    $state['variant_index'] = $variantIndex;
                }
                if ($variantsTotal !== null) {
                    $state['variants_total'] = $variantsTotal;
                }
                if ($variantsPage !== null) {
                    $state['variants_page'] = $variantsPage;
                }
                if ($attempt !== null) {
                    $state['attempt'] = $attempt;
                }
                if ($maxAttempts !== null) {
                    $state['max_attempts'] = $maxAttempts;
                }
                if ($batchCreate !== null) {
                    $state['batch_create'] = $batchCreate;
                }
                if ($batchUpdate !== null) {
                    $state['batch_update'] = $batchUpdate;
                }
                if ($verifiedCount !== null) {
                    $state['verified_count'] = $verifiedCount;
                }
                if ($missingCount !== null) {
                    $state['missing_count'] = $missingCount;
                }
                if ($status !== null) {
                    $state['status'] = $status;
                }
                if ($stageStartedAt !== null) {
                    $state['stage_started_at'] = $stageStartedAt;
                }
                if ($lastHttpStartedAt !== null) {
                    $state['last_http_started_at'] = $lastHttpStartedAt;
                }
                if ($lastHttpDurationMs !== null) {
                    $state['last_http_duration_ms'] = $lastHttpDurationMs;
                }
                if ($lastHttpErrorType !== null) {
                    $state['last_http_error_type'] = $lastHttpErrorType;
                }
                if ($lastHttpErrorMessage !== null) {
                    $state['last_http_error_message'] = $lastHttpErrorMessage;
                }
                if ($workerHeartbeatAt !== null) {
                    $state['worker_heartbeat_at'] = $workerHeartbeatAt;
                }
                if ($substep !== null) {
                    $state['substep'] = $substep;
                }
                if ($substepAt !== null) {
                    $state['substep_at'] = $substepAt;
                }
                if ($lastEndpoint !== null) {
                    $state['last_endpoint'] = $lastEndpoint;
                }
                if ($lastPayloadBytes !== null) {
                    $state['last_payload_bytes'] = $lastPayloadBytes;
                }
                // Clear stale variant fields when caller explicitly nulls them (moving to next product).
                if (array_key_exists('variant_sku', $p) && $p['variant_sku'] === null) {
                    unset($state['variant_sku']);
                }
                if (array_key_exists('variant_index', $p) && $p['variant_index'] === null) {
                    unset($state['variant_index']);
                }
                if (array_key_exists('variants_total', $p) && $p['variants_total'] === null) {
                    unset($state['variants_total']);
                }
                if (array_key_exists('variants_page', $p) && $p['variants_page'] === null) {
                    unset($state['variants_page']);
                }
                if (array_key_exists('attempt', $p) && $p['attempt'] === null) {
                    unset($state['attempt']);
                }
                if (array_key_exists('max_attempts', $p) && $p['max_attempts'] === null) {
                    unset($state['max_attempts']);
                }
                if (array_key_exists('batch_create', $p) && $p['batch_create'] === null) {
                    unset($state['batch_create']);
                }
                if (array_key_exists('batch_update', $p) && $p['batch_update'] === null) {
                    unset($state['batch_update']);
                }
                if (array_key_exists('verified_count', $p) && $p['verified_count'] === null) {
                    unset($state['verified_count']);
                }
                if (array_key_exists('missing_count', $p) && $p['missing_count'] === null) {
                    unset($state['missing_count']);
                }
                if (array_key_exists('status', $p) && $p['status'] === null) {
                    unset($state['status']);
                }
                if (array_key_exists('stage_started_at', $p) && $p['stage_started_at'] === null) {
                    unset($state['stage_started_at']);
                }
                if (array_key_exists('last_http_started_at', $p) && $p['last_http_started_at'] === null) {
                    unset($state['last_http_started_at']);
                }
                if (array_key_exists('last_http_duration_ms', $p) && $p['last_http_duration_ms'] === null) {
                    unset($state['last_http_duration_ms']);
                }
                if (array_key_exists('last_http_error_type', $p) && $p['last_http_error_type'] === null) {
                    unset($state['last_http_error_type']);
                }
                if (array_key_exists('last_http_error_message', $p) && $p['last_http_error_message'] === null) {
                    unset($state['last_http_error_message']);
                }
                if (array_key_exists('worker_heartbeat_at', $p) && $p['worker_heartbeat_at'] === null) {
                    unset($state['worker_heartbeat_at']);
                }
                if (array_key_exists('substep', $p) && $p['substep'] === null) {
                    unset($state['substep']);
                }
                if (array_key_exists('substep_at', $p) && $p['substep_at'] === null) {
                    unset($state['substep_at']);
                }
                if (array_key_exists('last_endpoint', $p) && $p['last_endpoint'] === null) {
                    unset($state['last_endpoint']);
                }
                if (array_key_exists('last_payload_bytes', $p) && $p['last_payload_bytes'] === null) {
                    unset($state['last_payload_bytes']);
                }
                // Worker heartbeat only (polling endpoint does not update this).
                $state['worker_heartbeat_at'] = now()->toDateTimeString();

                // IMPORTANT: use the real processed count emitted by SyncService.
                // (created+updated+failed can stay 0 when items are skipped, which makes UI stuck at 0%)
                $processed = isset($p['processed']) ? (int) $p['processed'] : (int) ($state['synced_products'] + $state['failed_products']);
                $state['processed'] = $processed;
                $state['percentage'] = $this->computePercentage($processed, (int) ($state['total_products'] ?? 0));

                $cache->put($this->progressKey, $state, 3600);

                // --- DB progress update (after each product attempt) ---
                if ($this->syncJobId) {
                    $job = SyncJob::query()->find($this->syncJobId);
                    if ($job) {
                        // If API marked it cancelled/failed (stop/stuck), abort immediately.
                        if (in_array((string) $job->status, ['cancelled', 'failed'], true)) {
                            throw new \RuntimeException(self::CANCELLED_EXCEPTION);
                        }

                        $stageNow = isset($p['stage']) ? (string) $p['stage'] : null;
                        $currentIdNow = isset($p['current_product_id']) ? (int) $p['current_product_id'] : null;
                        $currentSkuNow = isset($p['current_sku']) ? (string) $p['current_sku'] : null;

                        if ($stageNow !== null) {
                            $job->stage = $stageNow;
                            $lastStageForProduct = $stageNow;
                        }
                        if ($currentIdNow) {
                            $job->current_product_id = $currentIdNow;
                        }
                        if ($currentSkuNow !== null) {
                            $job->current_sku = $currentSkuNow;
                        }
                        $job->worker_heartbeat_at = now();

                        // Increment processed AFTER EACH PRODUCT ATTEMPT (we key off stage=done)
                        $isDone = ($stageNow === 'done');
                        $doneId = isset($p['last_product_id']) ? (int) $p['last_product_id'] : null;
                        if ($isDone && $doneId && $doneId !== $lastDoneProductId) {
                            $lastDoneProductId = $doneId;

                            $job->processed_items = (int) $job->processed_items + 1;

                            $createdNow = (int) ($p['created'] ?? 0);
                            $updatedNow = (int) ($p['updated'] ?? 0);
                            $errorsNow  = (int) ($p['errors'] ?? 0);

                            $successDelta = ($createdNow + $updatedNow) - ($prevCreated + $prevUpdated);
                            $errorDelta = $errorsNow - $prevErrors;

                            if ($errorDelta > 0 || $lastStageForProduct === 'missing_sku' || $lastStageForProduct === 'ambiguous_match') {
                                $job->failed_items = (int) $job->failed_items + 1;
                                $job->last_error = (string) ($p['last_http_error_message'] ?? ($p['last_http_error_type'] ?? ($p['missing_sku_reason'] ?? 'failed')));
                            } elseif ($successDelta > 0) {
                                $job->success_items = (int) $job->success_items + 1;
                            } else {
                                // Default: treat as success attempt (no error, no create/update)
                                $job->success_items = (int) $job->success_items + 1;
                            }

                            $prevCreated = $createdNow;
                            $prevUpdated = $updatedNow;
                            $prevErrors = $errorsNow;

                            $total = max(1, (int) $job->total_items);
                            $job->percentage = (int) floor(((int) $job->processed_items / $total) * 100);
                        }

                        $job->save();
                    }
                }
            }, $shouldCancel, $startAfterId > 0 ? $startAfterId : null, $batchSize, [
                'created' => (int) ($state['created'] ?? 0),
                'updated' => (int) ($state['updated'] ?? 0),
                'errors' => (int) ($state['failed_products'] ?? 0),
                'processed' => (int) ($state['processed'] ?? 0),
            ]);

            // Decide whether to continue (batching) or finish
            $lastId = (int) ($state['last_product_id'] ?? 0);
            $remaining = Product::whereNull('deleted_at')
                ->when($this->onlyUnsynced, fn ($q) => $q->whereNull('woocommerce_id'))
                ->when($lastId > 0, fn ($q) => $q->where('id', '>', $lastId))
                ->count();

            if (!$cancelled && $remaining > 0) {
                // Queue next batch; keep job running status, do not finalize.
                $finalize = false;
                $state['stage'] = 'queued_next_batch';
                $state['worker_heartbeat_at'] = now()->toDateTimeString();
                $cache->put($this->progressKey, $state, 3600);

                if ($this->syncJobId) {
                    $sj = SyncJob::query()->find($this->syncJobId);
                    if ($sj && !in_array((string) $sj->status, ['cancelled', 'failed'], true)) {
                        $sj->status = 'running';
                        $sj->stage = 'queued_next_batch';
                        $sj->worker_heartbeat_at = now();
                        $sj->save();
                    }
                }

                try {
                    WooCommerceLog::create([
                        'action' => 'products.push',
                        'level' => 'info',
                        'message' => 'Queued next products batch',
                        'context' => [
                            'only_unsynced' => $this->onlyUnsynced,
                            'batch_size' => $batchSize,
                            'last_product_id' => $lastId,
                            'remaining_after_last_id' => (int) $remaining,
                            'processed' => (int) ($state['processed'] ?? 0),
                            'created' => (int) ($state['created'] ?? 0),
                            'updated' => (int) ($state['updated'] ?? 0),
                            'errors' => (int) ($state['failed_products'] ?? 0),
                        ],
                    ]);
                } catch (\Throwable $e) {
                }

                // Re-dispatch same job for next slice.
                $queue = $this->syncJobId ? ('woocommerce-sync-'.(int) $this->syncJobId) : 'default';
                self::dispatch($this->progressKey, $this->onlyUnsynced, $this->syncJobId)
                    ->onConnection('database')
                    ->onQueue($queue);
                return;
            }

            // Mark DB job complete (only when no remaining items)
            if ($this->syncJobId) {
                $sj = SyncJob::query()->find($this->syncJobId);
                if ($sj) {
                    // Do not overwrite terminal state set by Stop/stuck detection.
                    if (!in_array((string) $sj->status, ['cancelled', 'failed'], true)) {
                        $sj->status = 'completed';
                        $sj->percentage = 100;
                        $sj->stage = 'finished';
                        $sj->finished_at = now();
                        $sj->worker_heartbeat_at = now();
                        $sj->save();
                    }
                }
            }

            $state['created'] = (int) ($result['created'] ?? $state['created']);
            $state['updated'] = (int) ($result['updated'] ?? $state['updated']);
            $state['failed_products'] = (int) ($result['errors'] ?? $state['failed_products']);
            $state['synced_products'] = $state['created'] + $state['updated'];

            // Keep final progress consistent with callback logic
            $processed = (int) ($state['processed'] ?? ($state['synced_products'] + $state['failed_products']));
            $state['processed'] = $processed;
            $state['percentage'] = $this->computePercentage($processed, (int) ($state['total_products'] ?? 0));

            $settings->last_sync_at = now();
            $settings->save();

            WooCommerceLog::create([
                'action' => 'products.push',
                'level' => 'info',
                'message' => 'Products push completed (job)',
                'context' => [
                    'only_unsynced' => $this->onlyUnsynced,
                    'created' => $state['created'],
                    'updated' => $state['updated'],
                    'errors' => $state['failed_products'],
                ],
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && $e->getMessage() === self::CANCELLED_EXCEPTION) {
                $cancelled = true;
                $state['cancelled'] = true;
                $state['cancelled_at'] = now()->toDateTimeString();
                $state['error'] = 'cancelled';
                if ($this->syncJobId) {
                    $sj = SyncJob::query()->find($this->syncJobId);
                    if ($sj) {
                        $sj->status = 'cancelled';
                        $sj->stage = 'cancelled';
                        $sj->finished_at = now();
                        $sj->worker_heartbeat_at = now();
                        $sj->save();
                    }
                }
                try {
                    WooCommerceLog::create([
                        'action' => 'products.push',
                        'level' => 'warning',
                        'message' => 'Products push cancelled (job)',
                        'context' => ['only_unsynced' => $this->onlyUnsynced],
                    ]);
                } catch (\Throwable $e2) {
                }
            } else {
                $state['error'] = $e->getMessage();
                if ($this->syncJobId) {
                    $sj = SyncJob::query()->find($this->syncJobId);
                    if ($sj) {
                        $sj->status = 'failed';
                        $sj->stage = 'failed';
                        $sj->last_error = $e->getMessage();
                        $sj->finished_at = now();
                        $sj->worker_heartbeat_at = now();
                        $sj->save();
                    }
                }
                WooCommerceLog::create([
                    'action' => 'products.push',
                    'level' => 'error',
                    'message' => 'Products push failed (job)',
                    'context' => ['error' => $e->getMessage()],
                ]);
            }
        } finally {
            // When batching, do not mark as finished and do not remove from active list.
            if (!$finalize) {
                $cache->put($this->progressKey, $state, 3600);
            } else {
                $state['finished'] = true;
                $state['finished_at'] = now()->toDateTimeString();
                if (!$cancelled) {
                    $state['percentage'] = 100;
                } else {
                    $processed = (int) (($state['synced_products'] ?? 0) + ($state['failed_products'] ?? 0));
                    $state['processed'] = $processed;
                    $state['percentage'] = $this->computePercentage($processed, (int) ($state['total_products'] ?? 0));
                }
                $cache->put($this->progressKey, $state, 3600);
                $cache->forget($cancelKey);
                $this->removeFromActiveList();
            }
        }
    }

    private function computePercentage(int $processed, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }
        $p = (int) floor(($processed / $total) * 100);
        if ($p > 100) {
            $p = 100;
        }
        if ($p < 0) {
            $p = 0;
        }

        return $p;
    }

    private function failJob(string $message): void
    {
        $state = Cache::get($this->progressKey, []);
        $state['finished'] = true;
        $state['error'] = $message;
        $state['percentage'] = 100;
        Cache::put($this->progressKey, $state, 3600);

        WooCommerceLog::create([
            'action' => 'products.push',
            'level' => 'error',
            'message' => $message,
            'context' => [],
        ]);
    }

    private function removeFromActiveList(): void
    {
        try {
            $tokens = Cache::store('file')->get(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, []);
            if (!is_array($tokens)) {
                return;
            }
            unset($tokens[$this->progressKey]);
            Cache::store('file')->put(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, $tokens, 3600);
        } catch (\Throwable $e) {
        }
    }
}

