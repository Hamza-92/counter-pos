<?php

namespace App\Http\Controllers;

use App\Jobs\WooCommerceProductsSyncJob;
use App\Jobs\WooCommerceProductsPullJob;
use App\Jobs\WooCommerceStockSyncJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncJob;
use App\Models\WooCommerceLog;
use App\Models\WooCommerceSetting;
use App\Services\WooCommerce\SyncService;
use App\Services\WooCommerce\Client as WooCommerceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Client as PosClient;
use App\Models\Setting;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\Sale;

class WooCommerceSyncController extends BaseController
{
    private const ACTIVE_PRODUCTS_SYNC_TOKENS_KEY = 'woo_products_sync_active_tokens';
    private const WOO_PRODUCTS_QUEUE_PREFIX = 'woocommerce-sync-';
    private const WOO_STOCK_QUEUE_PREFIX = 'woocommerce-stock-';

    private function progressCache()
    {
        // Force a shared store between web + queue worker (prevents “progress stuck at 0%”)
        return Cache::store('file');
    }

    private function addActiveProductsSyncToken(string $token): void
    {
        try {
            $tokens = $this->progressCache()->get(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, []);
            if (!is_array($tokens)) {
                $tokens = [];
            }
            $tokens[$token] = now()->toDateTimeString();
            $this->progressCache()->put(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, $tokens, 3600);
        } catch (\Throwable $e) {
        }
    }

    private function removeActiveProductsSyncToken(string $token): void
    {
        try {
            $tokens = $this->progressCache()->get(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, []);
            if (!is_array($tokens)) {
                return;
            }
            unset($tokens[$token]);
            $this->progressCache()->put(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, $tokens, 3600);
        } catch (\Throwable $e) {
        }
    }

    private function cancelProductsSyncToken(string $token, string $reason = 'cancelled'): void
    {
        // Signal job to stop ASAP
        $this->progressCache()->put($token.':cancel', true, 3600);

        // Mark state for UI immediately (job will also see the cancel flag shortly)
        $state = $this->progressCache()->get($token, null);
        if (is_array($state)) {
            $state['cancelled'] = true;
            $state['cancelled_at'] = now()->toDateTimeString();
            $state['finished'] = true;
            $state['finished_at'] = now()->toDateTimeString();
            $state['error'] = $reason;
            $this->progressCache()->put($token, $state, 3600);
        }
    }

    private function deleteQueuedProductsJobs(?string $token = null): void
    {
        try {
            if (!Schema::hasTable('jobs')) {
                return;
            }
            $q = DB::table('jobs')->where('payload', 'like', '%WooCommerceProductsSyncJob%');
            if (is_string($token) && $token !== '') {
                $q->where('payload', 'like', '%'.$token.'%');
            }
            $q->delete();
        } catch (\Throwable $e) {
        }
    }

    private function cancelAllActiveProductsSyncTokens(string $reason = 'cancelled_by_reset'): int
    {
        $count = 0;
        try {
            $tokens = $this->progressCache()->get(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY, []);
            if (!is_array($tokens) || empty($tokens)) {
                return 0;
            }
            foreach (array_keys($tokens) as $token) {
                if (!is_string($token) || $token === '') {
                    continue;
                }
                $this->cancelProductsSyncToken($token, $reason);
                $this->deleteQueuedProductsJobs($token);
                $count++;
            }
            $this->progressCache()->forget(self::ACTIVE_PRODUCTS_SYNC_TOKENS_KEY);
            // Also purge any queued Woo products sync jobs (tokens list may be stale)
            $this->deleteQueuedProductsJobs(null);
        } catch (\Throwable $e) {
        }

        return $count;
    }

    /**
     * POST /api/woocommerce/sync/products/stop
     * Body: { token: string }
     *
     * Signals the queue job to cancel ASAP and marks the progress state as cancelled.
     */
    public function stopProductsSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $data = $request->validate([
            'token' => 'required|string',
        ]);

        $token = (string) $data['token'];
        $state = $this->progressCache()->get($token, null);
        if (!is_array($state)) {
            return response()->json(['ok' => false, 'error' => 'Invalid token'], 422);
        }

        $this->cancelProductsSyncToken($token, 'cancelled');
        $this->deleteQueuedProductsJobs($token);
        $this->removeActiveProductsSyncToken($token);

        try {
            WooCommerceLog::create([
                'action' => 'products.push',
                'level' => 'warning',
                'message' => 'Products push cancelled by user',
                'context' => ['token' => $token],
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true]);
    }

    public function getSettings(Request $request)
    {
        $settings = WooCommerceSetting::first();

        return response()->json(['settings' => $settings]);
    }

    public function saveSettings(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $data = $request->validate([
            'store_url' => 'required|string',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'wp_username' => 'nullable|string',
            'wp_app_password' => 'nullable|string',
            'sync_interval' => 'nullable|string',
        ]);

        // Detect store change before saving
        $existing = WooCommerceSetting::first();
        $storeChanged = false;
        if ($existing) {
            $prevUrl = rtrim((string) $existing->store_url, '/');
            $newUrl = rtrim((string) $data['store_url'], '/');
            $prevKey = (string) $existing->consumer_key;
            $newKey = (string) $data['consumer_key'];
            $prevSecret = (string) $existing->consumer_secret;
            $newSecret = (string) $data['consumer_secret'];
            // Consider store changed if URL, key or secret changed
            $storeChanged = ($prevUrl !== $newUrl) || ($prevKey !== $newKey) || ($prevSecret !== $newSecret);
        }

        $settings = null;
        DB::transaction(function () use ($data, &$settings) {
            $settings = WooCommerceSetting::first();
            if (! $settings) {
                $settings = new WooCommerceSetting;
            }
            foreach ($data as $k => $v) {
                $settings->$k = $v;
            }
            $settings->save();
        }, 3);

        // If the Woo store has changed, clear previous product/category/customer mappings so they can be re-synced to the new store
        if ($storeChanged && $settings) {
            DB::transaction(function () use (&$settings) {
                DB::table('products')->whereNotNull('woocommerce_id')->update([
                    'woocommerce_id' => null,
                    'updated_at' => now(),
                ]);
                DB::table('categories')->whereNotNull('woocommerce_id')->update([
                    'woocommerce_id' => null,
                    'updated_at' => now(),
                ]);
                DB::table('brands')->whereNotNull('woocommerce_id')->update([
                    'woocommerce_id' => null,
                    'updated_at' => now(),
                ]);
                DB::table('clients')->whereNotNull('woocommerce_id')->update([
                    'woocommerce_id' => null,
                    'updated_at' => now(),
                ]);
                $settings->last_sync_at = null;
                $settings->save();
            }, 3);
        }

        return response()->json(['success' => true, 'settings' => $settings]);
    }

    public function connectStore(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }
        if (empty($settings->store_url) || empty($settings->consumer_key) || empty($settings->consumer_secret)) {
            return response()->json(['ok' => false, 'error' => 'Missing WooCommerce credentials'], 422);
        }

        $sync = SyncService::fromSettings($settings);
        $result = $sync->testConnection();
        if (empty($result['ok'])) {
            // persist failure details for troubleshooting
            \App\Models\WooCommerceLog::create([
                'action' => 'connect.test',
                'level' => 'error',
                'message' => 'Connection test failed',
                'context' => [
                    'status' => $result['status'] ?? null,
                    'data' => $result['data'] ?? null,
                    'error' => $result['error'] ?? null,
                ],
            ]);
        }

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function syncProducts(Request $request)
    {
        ini_set('max_execution_time', 2000); 
		ini_set('memory_limit', '512M');

        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        $mode = (string) $request->query('mode', 'push');
        if ($mode === 'pull') {
            // Woo -> Stocky
            $onlyUnsynced = (bool) $request->boolean('only_unsynced', false);

            // Try to compute remote total for better UX (X-WP-Total header)
            $remoteTotal = 0;
            try {
                $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
                $res = $client->getNoRetry('products', ['per_page' => 1, 'status' => 'any'], 20, 5);
                $hdr = $res->header('x-wp-total');
                $remoteTotal = $hdr !== null ? (int) $hdr : 0;
            } catch (\Throwable $e) {
                $remoteTotal = 0;
            }

            $syncJob = SyncJob::create([
                'user_id' => optional($request->user('api'))->id,
                'warehouse_id' => null,
                'status' => 'running',
                'total_items' => $remoteTotal,
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

            $token = 'woo_products_pull_'.uniqid();
            $this->progressCache()->put($token, [
                'total_products' => $remoteTotal,
                'synced_products' => 0,
                'failed_products' => 0,
                'percentage' => 0,
                'created' => 0,
                'updated' => 0,
                'processed' => 0,
                'stage' => 'queued',
                'cursor_page' => 1,
                'cursor_index' => 0,
                'sync_job_id' => (int) $syncJob->id,
                'worker_heartbeat_at' => now()->toDateTimeString(),
                'heartbeat_at' => now()->toDateTimeString(),
                'started_at' => now()->toDateTimeString(),
                'finished' => false,
                'error' => null,
            ], 3600);

            $this->addActiveProductsSyncToken($token);

            $queue = self::WOO_PRODUCTS_QUEUE_PREFIX.(int) $syncJob->id;
            if (method_exists(WooCommerceProductsPullJob::class, 'dispatchAfterResponse')) {
                WooCommerceProductsPullJob::dispatchAfterResponse($token, $onlyUnsynced, (int) $syncJob->id)
                    ->onConnection('database')
                    ->onQueue($queue);
            } else {
                WooCommerceProductsPullJob::dispatch($token, $onlyUnsynced, (int) $syncJob->id)
                    ->onConnection('database')
                    ->onQueue($queue);
            }

            return response()->json(['ok' => true, 'token' => $token, 'sync_job_id' => (int) $syncJob->id]);
        }

        // Run in background (prevents HTTP timeouts); UI polls progress by token.
        $onlyUnsynced = (bool) $request->boolean('only_unsynced', false);
        // Compute total immediately so UI never shows 0/0.
        $total = (int) Product::whereNull('deleted_at')
            ->when($onlyUnsynced, fn ($q) => $q->whereNull('woocommerce_id'))
            ->count();

        // DB-based progress tracking
        $syncJob = SyncJob::create([
            'user_id' => optional($request->user('api'))->id,
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
        if (method_exists(WooCommerceProductsSyncJob::class, 'dispatchAfterResponse')) {
            WooCommerceProductsSyncJob::dispatchAfterResponse($token, $onlyUnsynced, (int) $syncJob->id)
                ->onConnection('database')
                ->onQueue($queue);
        } else {
            WooCommerceProductsSyncJob::dispatch($token, $onlyUnsynced, (int) $syncJob->id)
                ->onConnection('database')
                ->onQueue($queue);
        }

        return response()->json(['ok' => true, 'token' => $token, 'sync_job_id' => (int) $syncJob->id]);
    }

    /**
     * Manual "no-cron" mode:
     * When the UI polls progress and the job is queued, run ONE queued batch inline.
     * This makes "Sync now" work on shared hosting without a persistent queue worker.
     */
    private function tickProductsQueueOnce(array $state): void
    {
        try {
            // IMPORTANT: in "no-cron" mode we run a queue job inside this HTTP request.
            // If PHP's max_execution_time is short, the request can be killed mid-batch and appear "stuck".
            $tickLimit = (int) env('WOO_POLL_TICK_MAX_SECONDS', 300);
            $tickLimit = max(30, min(1800, $tickLimit));
            @ini_set('max_execution_time', (string) $tickLimit);
            if (function_exists('set_time_limit')) {
                @set_time_limit($tickLimit);
            }

            if (!Schema::hasTable('jobs')) {
                return;
            }

            $syncJobId = (int) ($state['sync_job_id'] ?? 0);
            if ($syncJobId <= 0) {
                return;
            }

            $queue = self::WOO_PRODUCTS_QUEUE_PREFIX.$syncJobId;
            $hasQueued = DB::table('jobs')->where('queue', $queue)->exists();
            if (!$hasQueued) {
                return;
            }

            // Prevent concurrent ticks (multiple polling requests)
            $lockKey = 'woo_tick_queue:'.$queue;
            $lock = null;
            try {
                $lock = Cache::store('file')->lock($lockKey, 120);
                if (!$lock->get()) {
                    return;
                }
            } catch (\Throwable $e) {
                $lock = null;
            }

            try {
                // Run a single job from this sync's dedicated queue.
                Artisan::call('queue:work', [
                    'connection' => 'database',
                    '--once' => true,
                    '--queue' => $queue,
                    '--sleep' => 1,
                    '--tries' => 1,
                    '--timeout' => (int) env('QUEUE_WORKER_TIMEOUT', 1200),
                ]);
            } finally {
                try {
                    if ($lock) {
                        $lock->release();
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function syncStock(Request $request)
    {
        ini_set('max_execution_time', 2000); 
		ini_set('memory_limit', '512M');
        
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        // Start queued job and return a progress token
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

        return response()->json(['ok' => true, 'token' => $token]);
    }

    public function stopStockSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $data = $request->validate([
            'token' => 'required|string',
        ]);

        $token = (string) $data['token'];
        $state = $this->progressCache()->get($token, null);
        if (!is_array($state)) {
            return response()->json(['ok' => false, 'error' => 'Invalid token'], 422);
        }

        // Signal cancellation; job checks this flag between products.
        $this->progressCache()->put($token.':cancel', true, 3600);

        // Mark state for UI immediately.
        $state['cancelled'] = true;
        $state['cancelled_at'] = now()->toDateTimeString();
        $state['finished'] = true;
        $state['finished_at'] = now()->toDateTimeString();
        $state['error'] = 'cancelled';
        if (!isset($state['processed'])) {
            $state['processed'] = (int) (($state['synced_products'] ?? 0) + ($state['failed_products'] ?? 0));
        }
        $this->progressCache()->put($token, $state, 3600);

        // Best-effort: delete queued stock jobs for this token/queue
        try {
            if (Schema::hasTable('jobs')) {
                $queue = (string) ($state['queue'] ?? (self::WOO_STOCK_QUEUE_PREFIX.$token));
                DB::table('jobs')->where('queue', $queue)->delete();
            }
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true]);
    }

    private function tickStockQueueOnce(array $state, string $token): void
    {
        try {
            $tickLimit = (int) env('WOO_POLL_TICK_MAX_SECONDS', 300);
            $tickLimit = max(30, min(1800, $tickLimit));
            @ini_set('max_execution_time', (string) $tickLimit);
            if (function_exists('set_time_limit')) {
                @set_time_limit($tickLimit);
            }

            if (!Schema::hasTable('jobs')) {
                return;
            }

            $queue = (string) ($state['queue'] ?? '');
            if ($queue === '') {
                $queue = self::WOO_STOCK_QUEUE_PREFIX.$token;
            }

            $hasQueued = DB::table('jobs')->where('queue', $queue)->exists();
            if (!$hasQueued) {
                return;
            }

            $lockKey = 'woo_tick_queue:'.$queue;
            $lock = null;
            try {
                $lock = Cache::store('file')->lock($lockKey, 120);
                if (!$lock->get()) {
                    return;
                }
            } catch (\Throwable $e) {
                $lock = null;
            }

            try {
                Artisan::call('queue:work', [
                    'connection' => 'database',
                    '--once' => true,
                    '--queue' => $queue,
                    '--sleep' => 1,
                    '--tries' => 1,
                    '--timeout' => (int) env('QUEUE_WORKER_TIMEOUT', 1200),
                ]);
            } finally {
                try {
                    if ($lock) {
                        $lock->release();
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * GET /api/woocommerce/sync-stock/progress?token=...
     */
    public function syncStockProgress(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $token = (string) $request->query('token');
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'Missing token'], 422);
        }
        $state = $this->progressCache()->get($token, null);

        // Manual "no-cron" mode: if queued, run one batch inline.
        if (is_array($state) && empty($state['finished'])) {
            $stage = (string) ($state['stage'] ?? '');
            if ($stage !== '' && str_starts_with($stage, 'queued')) {
                $this->tickStockQueueOnce($state, $token);
                $state = $this->progressCache()->get($token, $state);
            }
        }

        // Stuck detection (same policy as products)
        if (is_array($state) && empty($state['finished'])) {
            try {
                $stuckAfterSeconds = (int) env('WOO_SYNC_STUCK_SECONDS', 600);
                $stuckAfterSeconds = max(60, min(3600, $stuckAfterSeconds));

                $stage = (string) ($state['stage'] ?? '');
                $effectiveStuckSeconds = $stuckAfterSeconds;

                if ($stage !== '' && str_starts_with($stage, 'queued')) {
                    $queueWait = (int) env('WOO_SYNC_QUEUE_WAIT_SECONDS', 1800);
                    $queueWait = max(120, min(21600, $queueWait));
                    $effectiveStuckSeconds = max($effectiveStuckSeconds, $queueWait);
                }
                if ($stage === 'media') {
                    $uploadTimeout = (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 60);
                    $uploadTimeout = max(1, min(300, $uploadTimeout));
                    $effectiveStuckSeconds = max($effectiveStuckSeconds, $uploadTimeout + 60);
                }

                $last = (string) ($state['worker_heartbeat_at'] ?? $state['heartbeat_at'] ?? $state['started_at'] ?? '');
                if ($last !== '') {
                    $lastTs = strtotime($last);
                    if ($lastTs !== false && (time() - $lastTs) > $effectiveStuckSeconds) {
                        $state['finished'] = true;
                        $state['finished_at'] = now()->toDateTimeString();
                        $state['error'] = 'stuck: no worker heartbeat for '.$effectiveStuckSeconds.'s';
                        $state['stuck'] = true;
                        $this->progressCache()->put($token, $state, 3600);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['ok' => $state !== null, 'state' => $state]);
    }

    /**
     * GET /api/woocommerce/sync/products/progress?token=...
     */
    public function syncProductsProgress(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $token = (string) $request->query('token');
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'Missing token'], 422);
        }
        $state = $this->progressCache()->get($token, null);

        // If the sync is queued and no worker is running, "tick" one batch inline.
        // This makes manual "Sync now" work without cron/supervisor (user must keep the page open).
        if (is_array($state) && empty($state['finished'])) {
            $stage = (string) ($state['stage'] ?? '');
            if ($stage !== '' && str_starts_with($stage, 'queued')) {
                $this->tickProductsQueueOnce($state);
                // Reload state after tick
                $state = $this->progressCache()->get($token, $state);
            }
        }

        // Stuck detection (production safety): if worker heartbeat hasn't moved, fail fast.
        if (is_array($state) && empty($state['finished'])) {
            try {
                $stuckAfterSeconds = (int) env('WOO_SYNC_STUCK_SECONDS', 600);
                $stuckAfterSeconds = max(60, min(3600, $stuckAfterSeconds));

                $stage = (string) ($state['stage'] ?? '');
                $effectiveStuckSeconds = $stuckAfterSeconds;

                if ($stage !== '' && str_starts_with($stage, 'queued')) {
                    $queueWait = (int) env('WOO_SYNC_QUEUE_WAIT_SECONDS', 1800);
                    $queueWait = max(120, min(21600, $queueWait));
                    $effectiveStuckSeconds = max($effectiveStuckSeconds, $queueWait);
                }
                if ($stage === 'media') {
                    $uploadTimeout = (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 60);
                    $uploadTimeout = max(1, min(300, $uploadTimeout));
                    $effectiveStuckSeconds = max($effectiveStuckSeconds, $uploadTimeout + 60);
                }

                $last = (string) ($state['worker_heartbeat_at'] ?? $state['heartbeat_at'] ?? $state['started_at'] ?? '');
                if ($last !== '') {
                    $lastTs = strtotime($last);
                    if ($lastTs !== false && (time() - $lastTs) > $effectiveStuckSeconds) {
                        $state['finished'] = true;
                        $state['finished_at'] = now()->toDateTimeString();
                        $state['error'] = 'stuck: no worker heartbeat for '.$effectiveStuckSeconds.'s';
                        $state['stuck'] = true;
                        $this->progressCache()->put($token, $state, 3600);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['ok' => $state !== null, 'state' => $state]);
    }

    /**
     * GET /api/woocommerce/stock-metrics
     */
    public function stockMetrics(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $in = (int) \DB::table('product_warehouse')
            ->whereNull('deleted_at')
            ->selectRaw('product_id, SUM(qte) as total')
            ->groupBy('product_id')
            ->having('total', '>', 0)
            ->count();
        $out = (int) \DB::table('product_warehouse')
            ->whereNull('deleted_at')
            ->selectRaw('product_id, SUM(qte) as total')
            ->groupBy('product_id')
            ->having('total', '<=', 0)
            ->count();
        $last = optional(\App\Models\WooCommerceSetting::first())->last_sync_at;

        return response()->json([
            'in_stock' => $in,
            'out_stock' => $out,
            'last_sync' => $last,
        ]);
    }

    public function syncCategories(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }
        $sync = SyncService::fromSettings($settings);
        $mode = (string) $request->query('mode', 'push');
        if ($mode === 'pull') {
            $result = $sync->pullCategories();
        } else {
            $onlyUnsynced = (bool) $request->boolean('only_unsynced', false);
            $result = $sync->pushCategories($onlyUnsynced);
        }
        $settings->last_sync_at = now();
        $settings->save();

        return response()->json(['ok' => true, 'result' => $result]);
    }

    public function syncBrands(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }
        $sync = SyncService::fromSettings($settings);
        $mode = (string) $request->query('mode', 'push');
        if ($mode === 'pull') {
            $result = $sync->pullBrands();
        } else {
            $onlyUnsynced = (bool) $request->boolean('only_unsynced', false);
            $result = $sync->pushBrands($onlyUnsynced);
        }
        $settings->last_sync_at = now();
        $settings->save();

        return response()->json(['ok' => true, 'result' => $result]);
    }

    public function logs(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $logs = WooCommerceLog::orderBy('id', 'desc')->limit(200)->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * DELETE /woocommerce/logs
     * Clear WooCommerce sync logs.
     */
    public function clearLogs(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        WooCommerceLog::query()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /woocommerce/reset-sync
     * Reset sync state for products, categories, logs, and last sync timestamp.
     */
    public function resetSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        // Kill/cancel any running Woo product sync jobs immediately
        $this->cancelAllActiveProductsSyncTokens('cancelled_by_reset');

        DB::transaction(function () {
            // Clear mappings
            DB::table('products')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
            // Clear variation mappings (POS variants -> Woo variations)
            DB::table('product_variants')
                ->whereNull('deleted_at')
                ->whereNotNull('woocommerce_variation_id')
                ->update([
                    'woocommerce_variation_id' => null,
                    'updated_at' => now(),
                ]);
            DB::table('categories')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
            DB::table('brands')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
            DB::table('clients')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);

            // Clear logs
            WooCommerceLog::query()->delete();

            // Reset last sync
            $settings = WooCommerceSetting::first();
            if ($settings) {
                $settings->last_sync_at = null;
                $settings->save();
            }
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * POST /woocommerce/reset-products-sync
     * Reset sync state for products and variants only.
     */
    public function resetProductsSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        // Kill/cancel any running Woo product sync jobs immediately
        $this->cancelAllActiveProductsSyncTokens('cancelled_by_reset');

        DB::transaction(function () {
            // Clear product mappings
            DB::table('products')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
            // Clear variation mappings (POS variants -> Woo variations)
            DB::table('product_variants')
                ->whereNull('deleted_at')
                ->whereNotNull('woocommerce_variation_id')
                ->update([
                    'woocommerce_variation_id' => null,
                    'updated_at' => now(),
                ]);
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/woocommerce/products/fix-categories
     * Fix Woo products that became "Uncategorized" by re-applying mapped categories.
     */
    public function fixProductCategories(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        $sync = SyncService::fromSettings($settings);
        $result = $sync->fixWooUncategorizedProducts();

        return response()->json($result);
    }

    /**
     * POST /woocommerce/reset-categories-sync
     * Reset sync state for categories only.
     */
    public function resetCategoriesSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        DB::transaction(function () {
            // Clear category mappings
            DB::table('categories')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * POST /woocommerce/reset-brands-sync
     * Reset sync state for brands only.
     */
    public function resetBrandsSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        DB::transaction(function () {
            // Clear brand mappings
            DB::table('brands')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * POST /woocommerce/reset-stock-sync
     * Reset sync state for stock (clears stock-related logs).
     */
    public function resetStockSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        DB::transaction(function () {
            // Clear stock-related logs
            WooCommerceLog::where('action', 'like', '%stock%')
                ->orWhere('action', 'like', '%Stock%')
                ->delete();
        }, 3);

        return response()->json(['success' => true]);
    }

    public function unsyncedCount(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $count = Product::whereNull('deleted_at')->whereNull('woocommerce_id')->count();

        return response()->json(['count' => $count]);
    }

    public function unsyncedCategoriesCount(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $count = Category::whereNull('deleted_at')->whereNull('woocommerce_id')->count();

        return response()->json(['count' => $count]);
    }

    public function unsyncedBrandsCount(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $count = Brand::whereNull('deleted_at')->whereNull('woocommerce_id')->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/woocommerce/customers/unsynced-count
     */
    public function unsyncedCustomersCount(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        $count = \App\Models\Client::whereNull('deleted_at')->whereNull('woocommerce_id')->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/woocommerce/customers/stats
     * Get customer statistics: total, synced (with woocommerce_id), unsynced
     */
    public function getCustomersStats(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);
        
        $total = \App\Models\Client::whereNull('deleted_at')->count();
        
        // Synced = customers with valid woocommerce_id (not null and > 0)
        // Count customers where woocommerce_id is NOT NULL and is a positive integer (> 0)
        // This excludes: null, 0, empty string, and negative numbers
        $synced = \App\Models\Client::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->whereRaw('CAST(woocommerce_id AS UNSIGNED) > 0')
            ->count();
        
        // Unsynced = total - synced (customers without valid woocommerce_id)
        // This includes: null, 0, empty string, and any invalid values
        $unsynced = max(0, $total - $synced);

        return response()->json([
            'total' => $total,
            'synced' => $synced,
            'unsynced' => $unsynced,
        ]);
    }

    /**
     * GET /api/woocommerce/products/pull-stats
     * Get stats for WooCommerce → Stocky: total in WooCommerce, imported (Stocky products with woocommerce_id), not imported
     */
    public function getProductsPullStats(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        // Build a set of distinct mapped Woo product IDs (parents only live on products table).
        $localIds = Product::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->whereRaw('CAST(woocommerce_id AS UNSIGNED) > 0')
            ->distinct()
            ->pluck('woocommerce_id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        $localSet = [];
        foreach ($localIds as $id) {
            $localSet[(string) $id] = true;
        }

        $imported = count($localSet);

        // We compute totals by comparing actual Woo IDs rather than trusting X-WP-Total
        // (some stores/plugins skew totals by including variations or ignoring type filters).
        $totalWoo = null;
        $notImported = null;
        $sampleMissing = [];

        // Debug/visibility (safe to ignore on UI)
        $totalWooAll = null;
        $totalWooVariations = null;
        $totalWooByType = null;
        $totalWooByTypeBreakdown = null;
        $settings = WooCommerceSetting::first();
        if ($settings) {
            try {
                $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
            } catch (\Throwable $e) {
                $totalWoo = null;
            }
        }

        // If we have credentials, compute accurate totals by paging Woo products and excluding variations.
        // This is more reliable than header totals in the presence of plugins/security hardening.
        if ($settings) {
            try {
                $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);

                // Best-effort debug totals from headers (not used for final)
                try {
                    $resAll = $client->getNoRetry('products', ['per_page' => 1, 'status' => 'any'], 20, 5);
                    $hdrAll = $resAll->header('x-wp-total');
                    $totalWooAll = ($hdrAll !== null && $hdrAll !== '') ? (int) $hdrAll : null;
                    $resVar = $client->getNoRetry('products', ['per_page' => 1, 'status' => 'any', 'type' => 'variation'], 20, 5);
                    $hdrVar = $resVar->header('x-wp-total');
                    $totalWooVariations = ($hdrVar !== null && $hdrVar !== '') ? (int) $hdrVar : null;
                } catch (\Throwable $e) {
                }

                $page = 1;
                $per = 100;
                $cap = (int) env('WOO_PULL_STATS_PAGE_CAP', 200);
                $cap = max(1, min(1000, $cap));

                $parents = 0;
                $missing = 0;

                while ($page <= $cap) {
                    $res = $client->getNoRetry('products', [
                        'page' => $page,
                        'per_page' => $per,
                        'status' => 'any',
                        'orderby' => 'id',
                        'order' => 'asc',
                        '_fields' => 'id,type',
                    ], 30, 5);

                    if (!$res->successful()) {
                        break;
                    }

                    $items = $res->json();
                    if (!is_array($items) || empty($items)) {
                        break;
                    }

                    foreach ($items as $it) {
                        if (!is_array($it)) continue;
                        $id = (int) ($it['id'] ?? 0);
                        if ($id <= 0) continue;
                        $type = (string) ($it['type'] ?? '');
                        if ($type === 'variation') {
                            continue;
                        }
                        $parents++;
                        if (!isset($localSet[(string) $id])) {
                            $missing++;
                            if (count($sampleMissing) < 10) {
                                $sampleMissing[] = $id;
                            }
                        }
                    }

                    if (count($items) < $per) {
                        break;
                    }
                    $page++;
                }

                $totalWoo = $parents;
                $notImported = $missing;
            } catch (\Throwable $e) {
                // Fall back to header-based subtraction if paging fails
                $notImported = ($totalWoo !== null && $totalWoo >= 0) ? max(0, $totalWoo - $imported) : null;
            }
        } else {
            $notImported = null;
        }

        return response()->json([
            'total_woo' => $totalWoo,
            // debug/visibility (safe to ignore on UI)
            'total_woo_all' => $totalWooAll,
            'total_woo_variations' => $totalWooVariations,
            'total_woo_by_type' => $totalWooByType,
            'total_woo_by_type_breakdown' => $totalWooByTypeBreakdown,
            'imported' => $imported,
            'not_imported' => $notImported,
            'sample_missing_ids' => $sampleMissing,
        ]);
    }

    /**
     * GET /api/woocommerce/categories/pull-stats
     * Get stats for WooCommerce → Stocky categories: total in WooCommerce, imported (Stocky categories with woocommerce_id), not imported
     */
    public function getCategoriesPullStats(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $imported = Category::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->whereRaw('CAST(woocommerce_id AS UNSIGNED) > 0')
            ->count();

        $totalWoo = null;
        $settings = WooCommerceSetting::first();
        if ($settings) {
            try {
                $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
                // Use headers to get total count without pulling all categories
                $res = $client->getNoRetry('products/categories', ['per_page' => 1, 'hide_empty' => false], 20, 5);
                $hdr = $res->header('x-wp-total');
                $totalWoo = $hdr !== null && $hdr !== '' ? (int) $hdr : null;
            } catch (\Throwable $e) {
                $totalWoo = null;
            }
        }

        $notImported = ($totalWoo !== null && $totalWoo >= 0) ? max(0, $totalWoo - $imported) : null;

        return response()->json([
            'total_woo' => $totalWoo,
            'imported' => $imported,
            'not_imported' => $notImported,
        ]);
    }

    /**
     * GET /api/woocommerce/brands/pull-stats
     * Get stats for WooCommerce → Stocky brands: total in WooCommerce, imported (Stocky brands with woocommerce_id), not imported
     */
    public function getBrandsPullStats(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $imported = Brand::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->whereRaw('CAST(woocommerce_id AS UNSIGNED) > 0')
            ->count();

        $totalWoo = null;
        $settings = WooCommerceSetting::first();
        if ($settings) {
            try {
                $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
                $res = $client->getNoRetry('products/brands', ['per_page' => 1, 'hide_empty' => false], 20, 5);
                $hdr = $res->header('x-wp-total');
                $totalWoo = $hdr !== null && $hdr !== '' ? (int) $hdr : null;
            } catch (\Throwable $e) {
                $totalWoo = null;
            }
        }

        $notImported = ($totalWoo !== null && $totalWoo >= 0) ? max(0, $totalWoo - $imported) : null;

        return response()->json([
            'total_woo' => $totalWoo,
            'imported' => $imported,
            'not_imported' => $notImported,
        ]);
    }

    /**
     * POST /api/woocommerce/sync/customers
     * Query params: mode=push|pull (default: push), only_unsynced=true|false (for push mode), customer_id=int (for single customer sync)
     */
    public function syncCustomers(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }
        $sync = SyncService::fromSettings($settings);
        $mode = (string) $request->query('mode', 'push');
        $customerId = $request->query('customer_id');
        
        // Single customer sync
        if ($customerId) {
            $customerId = (int) $customerId;
            if ($mode === 'pull') {
                $result = $sync->pullSingleCustomer($customerId);
            } else {
                $result = $sync->pushSingleCustomer($customerId);
            }
            
            if ($result['ok'] ?? false) {
                $settings->last_sync_at = now();
                $settings->save();
            }
            
            return response()->json($result);
        }
        
        // Bulk sync
        if ($mode === 'pull') {
            // Pull customers from WooCommerce → Stocky
            $result = $sync->pullCustomers();
        } else {
            // Push customers from Stocky → WooCommerce (default)
            $onlyUnsynced = (bool) $request->boolean('only_unsynced', false);
            $result = $sync->pushCustomers($onlyUnsynced);
        }
        
        $settings->last_sync_at = now();
        $settings->save();

        return response()->json(['ok' => true, 'result' => $result]);
    }

    /**
     * POST /api/woocommerce/sync/orders
     * Pull orders from WooCommerce -> Stocky sales.
     */
    public function syncOrders(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        // Determine a default warehouse (same logic used by POS element)
        $warehouseId = 0;
        try {
            $appSettings = Setting::whereNull('deleted_at')->first();
            $candidate = $appSettings ? (int) ($appSettings->warehouse_id ?? 0) : 0;

            $user = $request->user('api');
            if ($candidate > 0) {
                if ($user && (int) ($user->is_all_warehouses ?? 0) === 1) {
                    if (Warehouse::where('id', $candidate)->whereNull('deleted_at')->exists()) {
                        $warehouseId = $candidate;
                    }
                } else {
                    $allowed = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->toArray();
                    if (!empty($allowed) && in_array($candidate, $allowed, true)) {
                        $warehouseId = $candidate;
                    }
                }
            }

            if ($warehouseId <= 0) {
                if ($user && (int) ($user->is_all_warehouses ?? 0) === 1) {
                    $warehouseId = (int) (Warehouse::whereNull('deleted_at')->min('id') ?? 0);
                } else {
                    $allowed = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->toArray();
                    $warehouseId = !empty($allowed) ? (int) $allowed[0] : 0;
                }
            }
        } catch (\Throwable $e) {
            $warehouseId = 0;
        }

        $sync = SyncService::fromSettings($settings);
        $orderId = (int) $request->query('order_id', 0);
        if ($orderId > 0) {
            $result = $sync->pullSingleOrder($orderId, (int) $request->user('api')->id, $warehouseId);
        } else {
            $result = $sync->pullOrders((int) $request->user('api')->id, $warehouseId);
        }

        return response()->json($result);
    }

    /**
     * POST /woocommerce/reset-customers-sync
     */
    public function resetCustomersSync(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        DB::transaction(function () {
            DB::table('clients')->whereNotNull('woocommerce_id')->update([
                'woocommerce_id' => null,
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/woocommerce/customers
     * Fetch WooCommerce customers with pagination
     */
    public function getWooCommerceCustomers(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        try {
            $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 10);
            $search = trim((string) $request->query('search', ''));

            $params = [
                'page' => $page,
                'per_page' => $perPage,
                'orderby' => 'id',
                'order' => 'desc',
            ];

            if ($search !== '') {
                $params['search'] = $search;
            }

            $res = $client->getNoRetry('customers', $params, 20, 5);

            if (!$res->successful()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Failed to fetch WooCommerce customers',
                    'status' => $res->status(),
                ], $res->status());
            }

            $body = $res->json();
            $customers = is_array($body) ? ($body['customers'] ?? $body) : [];
            
            // Get total count from response
            $totalPages = (int) ($body['total_pages'] ?? 1);
            $total = (int) ($body['total'] ?? count($customers));

            // Format customers for display
            $formattedCustomers = [];
            foreach ($customers as $customer) {
                $billing = $customer['billing'] ?? $customer['billing_address'] ?? [];
                $formattedCustomers[] = [
                    'id' => $customer['id'] ?? null,
                    'email' => $customer['email'] ?? '',
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name' => $customer['last_name'] ?? '',
                    'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                    'phone' => is_array($billing) ? ($billing['phone'] ?? '') : '',
                    'city' => is_array($billing) ? ($billing['city'] ?? '') : '',
                    'state' => is_array($billing) ? ($billing['state'] ?? '') : '',
                    'zip' => is_array($billing) ? ($billing['postcode'] ?? '') : '',
                    'country' => is_array($billing) ? ($billing['country'] ?? '') : '',
                    'address' => is_array($billing) ? trim(((string) ($billing['address_1'] ?? '')).' '.((string) ($billing['address_2'] ?? ''))) : '',
                    'username' => $customer['username'] ?? '',
                    'date_created' => $customer['date_created'] ?? null,
                    'date_modified' => $customer['date_modified'] ?? null,
                ];
            }

            return response()->json([
                'ok' => true,
                'customers' => $formattedCustomers,
                'totalRows' => $total,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/woocommerce/orders
     * Fetch WooCommerce orders with pagination
     */
    public function getWooCommerceOrders(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        try {
            $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 10);
            $search = trim((string) $request->query('search', ''));

            $params = [
                'page' => $page,
                'per_page' => $perPage,
                'orderby' => 'id',
                'order' => 'desc',
            ];

            // Woo "search" works for some fields; keep optional
            if ($search !== '') {
                $params['search'] = $search;
            }

            $res = $client->getNoRetry('orders', $params, 20, 5);
            if (!$res->successful()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Failed to fetch WooCommerce orders',
                    'status' => $res->status(),
                ], $res->status());
            }

            $orders = $res->json();
            $orders = is_array($orders) ? ($orders['orders'] ?? $orders) : [];
            if (!is_array($orders)) {
                $orders = [];
            }

            $total = (int) ($res->header('X-WP-Total') ?? 0);
            if ($total <= 0) {
                $total = (int) count($orders);
            }
            $totalPages = (int) ($res->header('X-WP-TotalPages') ?? 1);

            $formatted = [];
            $customerCache = [];
            foreach ($orders as $order) {
                if (!is_array($order)) continue;
                $billing = $order['billing'] ?? [];
                $billingEmail = is_array($billing) ? (string) ($billing['email'] ?? '') : '';
                $billingName = is_array($billing) ? trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')) : '';
                $customerId = (int) ($order['customer_id'] ?? 0);

                // Prefer customer name (billing name). If missing, fallback to Woo customer username/name by customer_id.
                $customerDisplay = $billingName;
                if ($customerDisplay === '' && $customerId > 0) {
                    if (!array_key_exists($customerId, $customerCache)) {
                        try {
                            $cres = $client->getNoRetry('customers/'.$customerId, [
                                '_fields' => 'id,username,name,first_name,last_name,email',
                                'context' => 'edit',
                            ], 20, 5);
                            if ($cres->successful()) {
                                $cbody = $cres->json();
                                $cname = is_array($cbody) ? trim((string) ($cbody['name'] ?? '')) : '';
                                if ($cname === '' && is_array($cbody)) {
                                    $fn = trim((string) ($cbody['first_name'] ?? ''));
                                    $ln = trim((string) ($cbody['last_name'] ?? ''));
                                    $cname = trim($fn.' '.$ln);
                                }
                                $cuser = is_array($cbody) ? trim((string) ($cbody['username'] ?? '')) : '';
                                $customerCache[$customerId] = $cname !== '' ? $cname : ($cuser !== '' ? $cuser : '');
                            } else {
                                $customerCache[$customerId] = '';
                            }
                        } catch (\Throwable $e) {
                            $customerCache[$customerId] = '';
                        }
                    }
                    $customerDisplay = (string) ($customerCache[$customerId] ?? '');
                }
                if ($customerDisplay === '') {
                    $customerDisplay = trim($billingEmail) !== '' ? $billingEmail : 'Guest';
                }

                $formatted[] = [
                    'id' => $order['id'] ?? null,
                    'number' => $order['number'] ?? null,
                    'status' => $order['status'] ?? null,
                    'date_created' => $order['date_created'] ?? null,
                    'total' => $order['total'] ?? null,
                    'currency' => $order['currency'] ?? null,
                    'customer_id' => $customerId ?: null,
                    'billing_email' => $billingEmail,
                    'billing_name' => $billingName,
                    'customer_display' => $customerDisplay,
                    'items_count' => is_array($order['line_items'] ?? null) ? count($order['line_items']) : 0,
                ];
            }

            // Sync status: check which Woo orders are already imported into Stocky
            $ids = [];
            foreach ($formatted as $it) {
                $oid = (int) ($it['id'] ?? 0);
                if ($oid > 0) $ids[$oid] = true;
            }
            $importedMap = [];
            if (!empty($ids)) {
                $rows = \App\Models\Sale::whereNull('deleted_at')
                    ->whereIn('woocommerce_order_id', array_keys($ids))
                    ->get(['id', 'woocommerce_order_id']);
                foreach ($rows as $r) {
                    $importedMap[(int) $r->woocommerce_order_id] = (int) $r->id;
                }
            }
            foreach ($formatted as &$it) {
                $oid = (int) ($it['id'] ?? 0);
                $saleId = $oid > 0 && isset($importedMap[$oid]) ? (int) $importedMap[$oid] : 0;
                $it['stocky_sale_id'] = $saleId > 0 ? $saleId : null;
                $it['sync_status'] = $saleId > 0 ? 'synced' : 'not_synced';
            }
            unset($it);

            return response()->json([
                'ok' => true,
                'orders' => $formatted,
                'totalRows' => $total,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/woocommerce/orders/imported
     * Fetch Stocky sales imported from WooCommerce (woocommerce_order_id not null).
     */
    public function getImportedWooOrders(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $perPage = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $search = trim((string) $request->query('search', ''));
        $sortField = (string) $request->query('SortField', 'id');
        $sortType = strtolower((string) $request->query('SortType', 'desc'));
        if (!in_array($sortType, ['asc', 'desc'], true)) {
            $sortType = 'desc';
        }

        $allowedSort = ['id', 'date', 'Ref', 'GrandTotal', 'payment_statut', 'statut', 'woocommerce_order_id', 'woocommerce_order_status'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }

        $q = Sale::with(['client'])
            ->whereNull('deleted_at')
            ->whereNotNull('woocommerce_order_id')
            ->where('woocommerce_order_id', '>', 0);

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('Ref', 'like', "%{$search}%")
                    ->orWhere('woocommerce_order_id', 'like', "%{$search}%")
                    ->orWhere('woocommerce_order_number', 'like', "%{$search}%")
                    ->orWhere('woocommerce_order_status', 'like', "%{$search}%");
            })->orWhereHas('client', function ($qc) use ($search) {
                $qc->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $totalRows = (clone $q)->count();
        if ($perPage <= 0 || $perPage === -1) {
            $rows = $q->orderBy($sortField, $sortType)->get();
        } else {
            $offset = max(0, ($page * $perPage) - $perPage);
            $rows = $q->orderBy($sortField, $sortType)->offset($offset)->limit($perPage)->get();
        }

        $data = [];
        foreach ($rows as $sale) {
            $data[] = [
                'id' => $sale->id,
                'date' => $sale->date,
                'time' => $sale->time,
                'Ref' => $sale->Ref,
                'client_name' => optional($sale->client)->name,
                'client_email' => optional($sale->client)->email,
                'GrandTotal' => $sale->GrandTotal,
                'paid_amount' => $sale->paid_amount,
                'payment_statut' => $sale->payment_statut,
                'statut' => $sale->statut,
                'woocommerce_order_id' => $sale->woocommerce_order_id,
                'woocommerce_order_number' => $sale->woocommerce_order_number,
                'woocommerce_order_status' => $sale->woocommerce_order_status,
            ];
        }

        return response()->json([
            'ok' => true,
            'orders' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * GET /api/woocommerce/orders/imported/stats
     */
    public function getImportedWooOrdersStats(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $totalImported = Sale::whereNull('deleted_at')
            ->whereNotNull('woocommerce_order_id')
            ->where('woocommerce_order_id', '>', 0)
            ->count();

        $today = now()->toDateString();
        $importedToday = Sale::whereNull('deleted_at')
            ->whereNotNull('woocommerce_order_id')
            ->where('woocommerce_order_id', '>', 0)
            ->where('date', $today)
            ->count();

        return response()->json([
            'total_imported' => $totalImported,
            'imported_today' => $importedToday,
        ]);
    }

    /**
     * GET /api/woocommerce/customers/sync-issues
     * List Stocky clients that have a sync issue recorded.
     */
    public function getCustomerSyncIssues(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $perPage = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $search = trim((string) $request->query('search', ''));
        $sortField = (string) $request->query('SortField', 'sync_issue_at');
        $sortType = strtolower((string) $request->query('SortType', 'desc'));
        if (!in_array($sortType, ['asc', 'desc'], true)) {
            $sortType = 'desc';
        }

        $q = PosClient::whereNull('deleted_at')
            ->whereNotNull('sync_issue_type');

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('sync_issue_type', 'like', "%{$search}%")
                    ->orWhere('sync_issue_message', 'like', "%{$search}%");
            });
        }

        // Allow-list sort fields
        $allowedSort = ['id', 'name', 'email', 'phone', 'woocommerce_id', 'sync_issue_type', 'sync_issue_source', 'sync_issue_at'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'sync_issue_at';
        }

        $totalRows = (clone $q)->count();

        if ($perPage <= 0 || $perPage === -1) {
            $rows = $q->orderBy($sortField, $sortType)->get();
        } else {
            $offset = max(0, ($page * $perPage) - $perPage);
            $rows = $q->orderBy($sortField, $sortType)
                ->offset($offset)
                ->limit($perPage)
                ->get();
        }

        $issues = $rows->map(function (PosClient $c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'woocommerce_id' => $c->woocommerce_id,
                'sync_issue_type' => $c->sync_issue_type,
                'sync_issue_message' => $c->sync_issue_message,
                'sync_issue_source' => $c->sync_issue_source,
                'sync_issue_at' => $c->sync_issue_at,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'issues' => $issues,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * POST /api/woocommerce/customers/sync-issues/{id}/resolve
     * Clears the issue fields for a Stocky client.
     */
    public function resolveCustomerSyncIssue(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $client = PosClient::whereNull('deleted_at')->findOrFail($id);
        $client->sync_issue_type = null;
        $client->sync_issue_message = null;
        $client->sync_issue_source = null;
        $client->sync_issue_at = null;
        $client->save();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/woocommerce/customers/sync-issues/{id}/link
     * Body: { woocommerce_id: int }
     * Manually links a Stocky client to a WooCommerce customer ID and clears the issue.
     */
    public function manualLinkCustomerSyncIssue(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $data = $request->validate([
            'woocommerce_id' => 'required|integer|min:1',
        ]);

        $client = PosClient::whereNull('deleted_at')->findOrFail($id);
        $client->woocommerce_id = (int) $data['woocommerce_id'];
        $client->sync_issue_type = null;
        $client->sync_issue_message = null;
        $client->sync_issue_source = null;
        $client->sync_issue_at = null;
        $client->save();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /woocommerce/categories/map
     * Body: { mappings: [ { id: <local_category_id>, woocommerce_id: <int|null> }, ... ] }
     */
    public function mapCategories(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $data = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.id' => 'required|integer|exists:categories,id',
            'mappings.*.woocommerce_id' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['mappings'] as $map) {
                DB::table('categories')->where('id', $map['id'])->update([
                    'woocommerce_id' => $map['woocommerce_id'] ?? null,
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        return response()->json(['success' => true]);
    }

    /**
     * POST /woocommerce/products/auto-link
     *
     * Walk WooCommerce products and stamp `woocommerce_id` on Stocky products
     * (and `woocommerce_variation_id` on variants) where the SKU/code matches
     * exactly and only one match exists. Eliminates the most common cause of
     * "Order contains unmapped products" failures during order pull.
     */
    public function autoLinkProductsBySku(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'WooCommerce not configured'], 422);
        }

        try {
            $client = new WooCommerceClient($settings->store_url, $settings->consumer_key, $settings->consumer_secret);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Failed to init WooCommerce client: '.$e->getMessage()], 500);
        }

        $skuToWooId = [];        // sku => [woo_id, ...]
        $variableParents = [];   // [woo_parent_id, ...]

        $page = 1;
        $per = 100;
        $cap = (int) env('WOO_AUTOLINK_PAGE_CAP', 200);
        $cap = max(1, min(2000, $cap));

        while ($page <= $cap) {
            try {
                $res = $client->getNoRetry('products', [
                    'page' => $page,
                    'per_page' => $per,
                    'status' => 'any',
                    'orderby' => 'id',
                    'order' => 'asc',
                    '_fields' => 'id,sku,type',
                ], 30, 5);
            } catch (\Throwable $e) {
                break;
            }

            if (!$res->successful()) {
                break;
            }

            $items = $res->json();
            if (!is_array($items) || empty($items)) {
                break;
            }

            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $id = (int) ($it['id'] ?? 0);
                $sku = trim((string) ($it['sku'] ?? ''));
                $type = (string) ($it['type'] ?? '');
                if ($id <= 0 || $type === 'variation') continue;
                if ($sku !== '') {
                    // Case-insensitive match: Woo admin search is case-insensitive,
                    // and Stocky users routinely enter SKUs in mixed case.
                    $skuKey = mb_strtolower($sku);
                    $skuToWooId[$skuKey][] = $id;
                }
                if ($type === 'variable') {
                    $variableParents[] = $id;
                }
            }

            if (count($items) < $per) break;
            $page++;
        }

        $linked = 0;
        $ambiguous = 0;
        $noMatch = 0;
        $ambiguousSamples = [];
        $noMatchSamples = [];

        $candidates = Product::whereNull('deleted_at')
            ->whereNull('woocommerce_id')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->select('id', 'name', 'code')
            ->get();

        foreach ($candidates as $p) {
            $sku = trim((string) $p->code);
            if ($sku === '') {
                $noMatch++;
                continue;
            }
            $matches = $skuToWooId[mb_strtolower($sku)] ?? [];
            if (count($matches) === 1) {
                DB::table('products')->where('id', $p->id)->update([
                    'woocommerce_id' => $matches[0],
                    'updated_at' => now(),
                ]);
                $linked++;
            } elseif (count($matches) > 1) {
                $ambiguous++;
                if (count($ambiguousSamples) < 10) {
                    $ambiguousSamples[] = ['product_id' => (int) $p->id, 'sku' => $sku, 'woo_ids' => $matches];
                }
            } else {
                $noMatch++;
                if (count($noMatchSamples) < 10) {
                    $noMatchSamples[] = ['product_id' => (int) $p->id, 'sku' => $sku];
                }
            }
        }

        // Variant linking — fetch variations only for variable parents.
        $variantsLinked = 0;
        $variantsAmbiguous = 0;
        $variantsNoMatch = 0;

        $variableParents = array_values(array_unique($variableParents));
        foreach ($variableParents as $wooParentId) {
            $localParent = Product::whereNull('deleted_at')
                ->where('woocommerce_id', $wooParentId)
                ->first();
            if (!$localParent) continue;

            $localVariants = ProductVariant::where('product_id', $localParent->id)
                ->whereNull('woocommerce_variation_id')
                ->whereNull('deleted_at')
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->get();
            if ($localVariants->isEmpty()) continue;

            $varSkuMap = [];
            $vp = 1;
            $vpc = 50;
            while ($vp <= 20) {
                try {
                    $vres = $client->getNoRetry("products/{$wooParentId}/variations", [
                        'page' => $vp,
                        'per_page' => $vpc,
                        '_fields' => 'id,sku',
                    ], 30, 5);
                } catch (\Throwable $e) {
                    break;
                }
                if (!$vres->successful()) break;
                $vitems = $vres->json();
                if (!is_array($vitems) || empty($vitems)) break;
                foreach ($vitems as $vi) {
                    if (!is_array($vi)) continue;
                    $vid = (int) ($vi['id'] ?? 0);
                    $vsku = trim((string) ($vi['sku'] ?? ''));
                    if ($vid > 0 && $vsku !== '') {
                        $varSkuMap[mb_strtolower($vsku)][] = $vid;
                    }
                }
                if (count($vitems) < $vpc) break;
                $vp++;
            }

            if (empty($varSkuMap)) continue;

            foreach ($localVariants as $lv) {
                $vsku = trim((string) $lv->code);
                if ($vsku === '') {
                    $variantsNoMatch++;
                    continue;
                }
                $matches = $varSkuMap[mb_strtolower($vsku)] ?? [];
                if (count($matches) === 1) {
                    DB::table('product_variants')->where('id', $lv->id)->update([
                        'woocommerce_variation_id' => $matches[0],
                        'updated_at' => now(),
                    ]);
                    $variantsLinked++;
                } elseif (count($matches) > 1) {
                    $variantsAmbiguous++;
                } else {
                    $variantsNoMatch++;
                }
            }
        }

        try {
            WooCommerceLog::create([
                'action' => 'products.autolink',
                'level' => 'info',
                'message' => "Auto-link by SKU: products linked={$linked}, ambiguous={$ambiguous}, no_match={$noMatch}; variants linked={$variantsLinked}",
                'context' => [
                    'products_scanned' => $candidates->count(),
                    'woo_skus_indexed' => count($skuToWooId),
                    'woo_variable_parents' => count($variableParents),
                    'ambiguous_samples' => $ambiguousSamples,
                ],
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json([
            'ok' => true,
            'products' => [
                'scanned' => $candidates->count(),
                'linked' => $linked,
                'ambiguous' => $ambiguous,
                'no_match' => $noMatch,
            ],
            'variants' => [
                'linked' => $variantsLinked,
                'ambiguous' => $variantsAmbiguous,
                'no_match' => $variantsNoMatch,
            ],
            'samples' => [
                'ambiguous' => $ambiguousSamples,
                'no_match' => $noMatchSamples,
            ],
        ]);
    }

    /**
     * GET /woocommerce/products/unmapped-report
     *
     * Returns three lists to help the user fix order import failures:
     *  - unlinked Stocky products (no woocommerce_id)
     *  - unlinked Stocky variants (no woocommerce_variation_id)
     *  - aggregated WooCommerce line items from recent failed-order logs
     */
    public function getUnmappedItemsReport(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WooCommerceSetting::class);

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(500, $limit));

        $unlinkedProductsQuery = Product::whereNull('deleted_at')->whereNull('woocommerce_id');
        $unlinkedProducts = (clone $unlinkedProductsQuery)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'code']);
        $unlinkedProductsCount = $unlinkedProductsQuery->count();

        // Exclude soft-deleted variants AND variants whose parent product is soft-deleted
        // (ProductVariant model doesn't use SoftDeletes trait, so the deleted_at column
        // must be filtered explicitly).
        $unlinkedVariantsQuery = ProductVariant::query()
            ->whereNull('product_variants.woocommerce_variation_id')
            ->whereNull('product_variants.deleted_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.id', 'product_variants.product_id')
                    ->whereNull('products.deleted_at');
            });
        $unlinkedVariants = (clone $unlinkedVariantsQuery)
            ->orderBy('product_variants.id', 'desc')
            ->limit($limit)
            ->get(['product_variants.id', 'product_variants.product_id', 'product_variants.name', 'product_variants.code']);
        $unlinkedVariantsCount = $unlinkedVariantsQuery->count();

        $recentFailed = WooCommerceLog::where('action', 'orders.pull')
            ->where('level', 'warning')
            ->where('message', 'Skipped order: could not map line item product')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id', 'context', 'created_at']);

        $issueMap = [];
        foreach ($recentFailed as $row) {
            $ctx = is_array($row->context) ? $row->context : (json_decode((string) $row->context, true) ?: []);
            $wooId = (int) ($ctx['woo_product_id'] ?? 0);
            $varId = (int) ($ctx['woo_variation_id'] ?? 0);
            $sku = trim((string) ($ctx['sku'] ?? ''));
            $orderId = $ctx['woocommerce_order_id'] ?? null;

            if ($varId > 0) {
                $key = 'v:'.$varId;
            } elseif ($wooId > 0) {
                $key = 'p:'.$wooId;
            } elseif ($sku !== '') {
                $key = 's:'.$sku;
            } else {
                continue;
            }

            if (!isset($issueMap[$key])) {
                $issueMap[$key] = [
                    'woo_product_id' => $wooId ?: null,
                    'woo_variation_id' => $varId ?: null,
                    'sku' => $sku ?: null,
                    'count' => 0,
                    'last_order_id' => $orderId,
                    'last_seen_at' => optional($row->created_at)->toDateTimeString(),
                ];
            }
            $issueMap[$key]['count']++;
        }

        $orderFailures = array_values($issueMap);
        usort($orderFailures, fn($a, $b) => ($b['count'] <=> $a['count']));

        return response()->json([
            'ok' => true,
            'unlinked_products' => [
                'total' => $unlinkedProductsCount,
                'sample' => $unlinkedProducts,
            ],
            'unlinked_variants' => [
                'total' => $unlinkedVariantsCount,
                'sample' => $unlinkedVariants,
            ],
            'order_failures' => $orderFailures,
        ]);
    }
}
