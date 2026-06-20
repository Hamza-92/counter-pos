<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\WooCommerceLog;
use App\Models\WooCommerceSetting;
use App\Services\WooCommerce\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WooCommerceStockSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $progressKey;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(string $progressKey)
    {
        $this->progressKey = $progressKey;
    }

    public function handle(): void
    {
        $cache = Cache::store('file');
        $finalize = true;

        $settings = WooCommerceSetting::first();
        if (! $settings) {
            $this->failJob('WooCommerce settings missing');

            return;
        }

        $client = new Client((string) $settings->store_url, (string) $settings->consumer_key, (string) $settings->consumer_secret);

        $queryBase = Product::whereNull('deleted_at')->whereNotNull('woocommerce_id');
        $total = (int) $queryBase->count();

        // Keep state between batches; do NOT reset counters each run.
        $state = $cache->get($this->progressKey, null);
        if (!is_array($state) || !empty($state['finished'])) {
            $state = [
                'total_products' => $total,
                'synced_products' => 0,
                'failed_products' => 0,
                'processed' => 0,
                'percentage' => 0,
                'stage' => 'queued',
                'last_product_id' => null,
                'last_ok' => null,
                'worker_heartbeat_at' => now()->toDateTimeString(),
                'started_at' => now()->toDateTimeString(),
                'finished' => false,
                'error' => null,
            ];
            $cache->put($this->progressKey, $state, 3600);
        } else {
            if (!isset($state['total_products'])) {
                $state['total_products'] = $total;
                $cache->put($this->progressKey, $state, 3600);
            }
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

        $batchSize = (int) env('WOO_STOCK_PRODUCTS_PER_JOB', 1);
        $batchSize = max(1, min(100, $batchSize));
        $startAfterId = (int) ($state['last_product_id'] ?? 0);
        $state['processed_this_run'] = 0;
        $state['max_this_run'] = $batchSize;

        $state['stage'] = 'running';
        $state['worker_heartbeat_at'] = now()->toDateTimeString();
        $cache->put($this->progressKey, $state, 3600);

        Product::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->when($startAfterId > 0, fn ($q) => $q->where('id', '>', $startAfterId))
            ->orderBy('id')
            ->chunk(200, function ($products) use (&$state, $client) {
                foreach ($products as $product) {
                    $cache = Cache::store('file');
                    $cancelKey = $this->progressKey.':cancel';
                    if ((bool) $cache->get($cancelKey, false)) {
                        $state['cancelled'] = true;
                        $state['cancelled_at'] = now()->toDateTimeString();
                        $state['finished'] = true;
                        $state['finished_at'] = now()->toDateTimeString();
                        $state['error'] = 'cancelled';
                        $cache->put($this->progressKey, $state, 3600);
                        $cache->forget($cancelKey);
                        return false;
                    }

                    $processedThisRun = (int) ($state['processed_this_run'] ?? 0);
                    $maxThisRun = (int) ($state['max_this_run'] ?? 0);
                    if ($maxThisRun > 0 && $processedThisRun >= $maxThisRun) {
                        return false;
                    }

                    $ok = true;
                    $message = 'OK';
                    $statusCode = null;
                    $body = null;

                    try {
                        // Skip services
                        if (($product->type ?? '') === 'is_service') {
                            // mark as processed without remote call
                        } elseif ((int) ($product->is_variant ?? 0) === 1 || ($product->type ?? '') === 'is_variant') {
                            // Variable product: update all variations by SKU/option
                            $existingBySku = [];
                            $existingByOpt = [];
                            $page = 1;
                            $per = 100;
                            while (true) {
                                $vres = $client->get('products/'.(int) $product->woocommerce_id.'/variations', ['page' => $page, 'per_page' => $per]);
                                if (! $vres->successful()) {
                                    break;
                                }
                                $list = $vres->json();
                                if (empty($list)) {
                                    break;
                                }
                                foreach ($list as $v) {
                                    $vid = (int) ($v['id'] ?? 0);
                                    if ($vid <= 0) {
                                        continue;
                                    }
                                    $sku = (string) ($v['sku'] ?? '');
                                    if ($sku !== '') {
                                        $existingBySku[$sku] = $vid;
                                    }
                                    $attrs = $v['attributes'] ?? [];
                                    if (is_array($attrs) && isset($attrs[0]['option'])) {
                                        $opt = (string) $attrs[0]['option'];
                                        if ($opt !== '') {
                                            $existingByOpt[$opt] = $vid;
                                        }
                                    }
                                }
                                if (count($list) < $per) {
                                    break;
                                }
                                $page++;
                            }

                            $anyInStock = false;
                            $variants = \App\Models\ProductVariant::where('product_id', $product->id)
                                ->whereNull('deleted_at')
                                ->get();
                            foreach ($variants as $var) {
                                $name = trim((string) ($var->name ?? ''));
                                $sku = trim((string) ($var->code ?? ''));
                                if ($sku === '') {
                                    $sku = $product->code ? ($product->code.'-'.($name !== '' ? $name : $var->id)) : ('VAR-'.$var->id);
                                }
                                $qty = $this->computeVariantStockQuantity((int) $product->id, (int) $var->id);
                                $status = $qty > 0 ? 'instock' : 'outofstock';
                                if ($qty > 0) {
                                    $anyInStock = true;
                                }
                                $payloadVar = ['manage_stock' => true, 'stock_quantity' => $qty, 'stock_status' => $status];

                                // Attach main product image to variation (prefer WP media id)
                                $media = $this->resolveOrUploadWpMedia($product);
                                if ($media && isset($media['id'])) {
                                    $payloadVar['image'] = ['id' => (int) $media['id']];
                                } else {
                                    try {
                                        $imgName = (string) ($product->image ?? '');
                                        if ($imgName !== '' && strtolower($imgName) !== 'no-image.png') {
                                            $public = public_path('images/products/'.$imgName);
                                            if (is_file($public)) {
                                                $payloadVar['image'] = ['src' => asset('images/products/'.$imgName)];
                                            }
                                        }
                                    } catch (\Throwable $e) {
                                    }
                                }

                                $target = $existingBySku[$sku] ?? ($existingByOpt[$name] ?? null);
                                if ($target) {
                                    $res = $client->put('products/'.(int) $product->woocommerce_id.'/variations/'.$target, $payloadVar);
                                    $ok = $ok && $res->successful();
                                    if (! $ok) {
                                        $statusCode = $res->status();
                                        $body = $res->body();
                                    }
                                } else {
                                    $createPayload = $payloadVar + ['sku' => $sku, 'attributes' => [['name' => 'Variant', 'option' => $name !== '' ? $name : ('Variant '.$var->id)]]];
                                    $res = $client->post('products/'.(int) $product->woocommerce_id.'/variations', $createPayload);
                                    $ok = $ok && $res->successful();
                                    if (! $ok) {
                                        $statusCode = $res->status();
                                        $body = $res->body();
                                    }
                                }
                            }

                            // Update parent stock status only
                            try {
                                $client->put('products/'.(int) $product->woocommerce_id, ['manage_stock' => false, 'stock_status' => $anyInStock ? 'instock' : 'outofstock']);
                            } catch (\Throwable $e) {
                            }

                        } elseif (($product->type ?? '') === 'is_combo') {
                            $qty = $this->computeComboStockQuantity((int) $product->id);
                            $status = $qty > 0 ? 'instock' : 'outofstock';
                            $payload = ['manage_stock' => true, 'stock_quantity' => $qty, 'stock_status' => $status];
                            // Attach main product image (prefer WP media id)
                            $media = $this->resolveOrUploadWpMedia($product);
                            if ($media && isset($media['id'])) {
                                $payload['images'] = [['id' => (int) $media['id']]];
                            } else {
                                try {
                                    $imgName = (string) ($product->image ?? '');
                                    if ($imgName !== '' && strtolower($imgName) !== 'no-image.png') {
                                        $public = public_path('images/products/'.$imgName);
                                        if (is_file($public)) {
                                            $payload['images'] = [['src' => asset('images/products/'.$imgName)]];
                                        }
                                    }
                                } catch (\Throwable $e) {
                                }
                            }
                            $res = $client->put('products/'.(int) $product->woocommerce_id, $payload);
                            $ok = $res->successful();
                            if (! $ok) {
                                $statusCode = $res->status();
                                $body = $res->body();
                            }
                        } else {
                            // Simple
                            $qty = $this->computeStockQuantity((int) $product->id);
                            $status = $qty > 0 ? 'instock' : 'outofstock';
                            $payload = ['manage_stock' => true, 'stock_quantity' => $qty, 'stock_status' => $status];
                            // Attach main product image (prefer WP media id)
                            $media = $this->resolveOrUploadWpMedia($product);
                            if ($media && isset($media['id'])) {
                                $payload['images'] = [['id' => (int) $media['id']]];
                            } else {
                                try {
                                    $imgName = (string) ($product->image ?? '');
                                    if ($imgName !== '' && strtolower($imgName) !== 'no-image.png') {
                                        $public = public_path('images/products/'.$imgName);
                                        if (is_file($public)) {
                                            $payload['images'] = [['src' => asset('images/products/'.$imgName)]];
                                        }
                                    }
                                } catch (\Throwable $e) {
                                }
                            }
                            $res = $client->put('products/'.(int) $product->woocommerce_id, $payload);
                            $ok = $res->successful();
                            if (! $ok) {
                                $statusCode = $res->status();
                                $body = $res->body();
                            }
                        }
                    } catch (\Throwable $e) {
                        $message = $e->getMessage();
                        $ok = false;
                    }

                    if ($ok) {
                        $state['synced_products']++;
                    } else {
                        $state['failed_products']++;
                        WooCommerceLog::create([
                            'action' => 'stock.sync',
                            'level' => 'error',
                            'message' => 'Stock sync failed',
                            'context' => [
                                'product_id' => $product->id,
                                'woocommerce_id' => (int) $product->woocommerce_id,
                                'status' => $statusCode,
                                'body' => $body,
                                'error' => $message,
                            ],
                        ]);
                    }

                    $processed = $state['synced_products'] + $state['failed_products'];
                    $state['processed'] = $processed;
                    $state['percentage'] = $this->computePercentage($processed, $state['total_products']);
                    $state['last_product_id'] = (int) $product->id;
                    $state['last_ok'] = $ok;
                    $state['worker_heartbeat_at'] = now()->toDateTimeString();
                    // local per-run counters (for early exit)
                    $state['processed_this_run'] = (int) ($state['processed_this_run'] ?? 0) + 1;
                    Cache::store('file')->put($this->progressKey, $state, 3600);
                }
            });

        // Determine remaining products after last id; if remaining, queue next batch.
        $lastId = (int) ($state['last_product_id'] ?? 0);
        $remaining = Product::whereNull('deleted_at')
            ->whereNotNull('woocommerce_id')
            ->when($lastId > 0, fn ($q) => $q->where('id', '>', $lastId))
            ->count();

        if (empty($state['finished']) && $remaining > 0) {
            $finalize = false;
            $state['stage'] = 'queued_next_batch';
            $state['worker_heartbeat_at'] = now()->toDateTimeString();
            unset($state['processed_this_run'], $state['max_this_run']);
            $cache->put($this->progressKey, $state, 3600);

            $queue = (string) ($state['queue'] ?? ('woocommerce-stock-'.$this->progressKey));
            self::dispatch($this->progressKey)->onConnection('database')->onQueue($queue);
            return;
        }

        $state['finished'] = true;
        $state['finished_at'] = now()->toDateTimeString();
        $cache->put($this->progressKey, $state, 3600);
        $cache->forget($cancelKey);

        $settings->last_sync_at = now();
        $settings->save();

        WooCommerceLog::create([
            'action' => 'stock.sync',
            'level' => 'info',
            'message' => 'Stock sync completed',
            'context' => [
                'processed' => $state['synced_products'] + $state['failed_products'],
                'success' => $state['synced_products'],
                'failed' => $state['failed_products'],
            ],
        ]);
    }

    private function computeStockQuantity(int $productId): int
    {
        $sum = (float) product_warehouse::where('product_id', $productId)
            ->whereNull('deleted_at')
            ->sum('qte');
        $qty = (int) round($sum);

        return $qty < 0 ? 0 : $qty;
    }

    private function computeVariantStockQuantity(int $productId, int $variantId): int
    {
        $sum = (float) product_warehouse::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereNull('deleted_at')
            ->sum('qte');
        $qty = (int) round($sum);

        return $qty < 0 ? 0 : $qty;
    }

    private function computeComboStockQuantity(int $productId): int
    {
        $components = \DB::table('combined_products')
            ->where('product_id', $productId)
            ->get(['combined_product_id', 'quantity']);
        if ($components->isEmpty()) {
            return $this->computeStockQuantity($productId);
        }
        $min = null;
        foreach ($components as $c) {
            $componentStock = $this->computeStockQuantity((int) $c->combined_product_id);
            $required = max(1.0, (float) $c->quantity);
            $possible = (int) floor($componentStock / $required);
            $min = is_null($min) ? $possible : min($min, $possible);
        }

        return max(0, (int) ($min ?? 0));
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
        $state = Cache::store('file')->get($this->progressKey, []);
        $state['finished'] = true;
        $state['error'] = $message;
        $state['percentage'] = 100;
        Cache::store('file')->put($this->progressKey, $state, 3600);

        WooCommerceLog::create([
            'action' => 'stock.sync',
            'level' => 'error',
            'message' => $message,
            'context' => [],
        ]);
    }

    private function resolveOrUploadWpMedia($product): ?array
    {
        try {
            $settings = WooCommerceSetting::first();
            if (! $settings) {
                return null;
            }
            $username = (string) ($settings->wp_username ?? '');
            $appPass = (string) ($settings->wp_app_password ?? '');
            $baseUrl = rtrim((string) ($settings->store_url ?? ''), '/');
            if ($username === '' || $appPass === '' || $baseUrl === '') {
                return null;
            }

            $imgName = (string) ($product->image ?? '');
            if ($imgName === '' || strtolower($imgName) === 'no-image.png') {
                return null;
            }
            $abs = public_path('images/products/'.$imgName);
            if (! is_file($abs)) {
                return null;
            }

            $filenameBase = pathinfo($imgName, PATHINFO_FILENAME);

            try {
                $matches = function ($m) use ($imgName, $filenameBase): bool {
                    if (!is_array($m)) {
                        return false;
                    }
                    $src = (string) ($m['source_url'] ?? '');
                    $file = '';
                    if (isset($m['media_details']) && is_array($m['media_details'])) {
                        $file = (string) ($m['media_details']['file'] ?? '');
                    }

                    $srcPath = $src !== '' ? (string) parse_url($src, PHP_URL_PATH) : '';
                    $srcBase = $srcPath !== '' ? basename($srcPath) : '';
                    $fileBase = $file !== '' ? basename($file) : '';

                    $want = strtolower($imgName);
                    $wantDecoded = strtolower(urldecode($imgName));

                    foreach ([$srcBase, urldecode($srcBase), $fileBase, urldecode($fileBase)] as $candidate) {
                        $c = strtolower((string) $candidate);
                        if ($c !== '' && ($c === $want || $c === $wantDecoded)) {
                            return true;
                        }
                    }

                    if ($src !== '' && (stripos($src, $imgName) !== false || stripos($src, $filenameBase) !== false)) {
                        return true;
                    }
                    if ($file !== '' && (stripos($file, $imgName) !== false || stripos($file, $filenameBase) !== false)) {
                        return true;
                    }
                    return false;
                };

                $trySearch = function (string $term) use ($baseUrl, $username, $appPass, $matches): ?array {
                    $searchTimeout = (int) env('WOO_WP_MEDIA_SEARCH_TIMEOUT', 15);
                    $retries = (int) env('WOO_WP_MEDIA_RETRIES', 2);
                    $sleepMs = (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 500);
                    $searchTimeout = max(1, min(60, $searchTimeout));
                    $retries = max(0, min(5, $retries));
                    $sleepMs = max(0, min(5000, $sleepMs));

                    $mediaList = Http::timeout($searchTimeout)
                        ->retry($retries, $sleepMs)
                        ->withBasicAuth($username, $appPass)
                        ->get($baseUrl.'/wp-json/wp/v2/media', [
                            'search' => $term,
                            'per_page' => 100,
                            '_fields' => 'id,source_url,media_details',
                        ]);

                    if (!$mediaList->successful()) {
                        return null;
                    }
                    $items = $mediaList->json();
                    if (!is_array($items)) {
                        return null;
                    }
                    foreach ($items as $m) {
                        if (!$matches($m)) {
                            continue;
                        }
                        $id = (int) ($m['id'] ?? 0);
                        $src = (string) ($m['source_url'] ?? '');
                        if ($id > 0) {
                            return ['id' => $id, 'src' => $src];
                        }
                    }
                    return null;
                };

                $trySlug = function (string $slug) use ($baseUrl, $username, $appPass, $matches): ?array {
                    $searchTimeout = (int) env('WOO_WP_MEDIA_SEARCH_TIMEOUT', 15);
                    $retries = (int) env('WOO_WP_MEDIA_RETRIES', 2);
                    $sleepMs = (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 500);
                    $searchTimeout = max(1, min(60, $searchTimeout));
                    $retries = max(0, min(5, $retries));
                    $sleepMs = max(0, min(5000, $sleepMs));

                    $mediaList = Http::timeout($searchTimeout)
                        ->retry($retries, $sleepMs)
                        ->withBasicAuth($username, $appPass)
                        ->get($baseUrl.'/wp-json/wp/v2/media', [
                            'slug' => $slug,
                            'per_page' => 100,
                            '_fields' => 'id,source_url,media_details',
                        ]);

                    if (!$mediaList->successful()) {
                        return null;
                    }
                    $items = $mediaList->json();
                    if (!is_array($items)) {
                        return null;
                    }
                    foreach ($items as $m) {
                        if (!$matches($m)) {
                            continue;
                        }
                        $id = (int) ($m['id'] ?? 0);
                        $src = (string) ($m['source_url'] ?? '');
                        if ($id > 0) {
                            return ['id' => $id, 'src' => $src];
                        }
                    }
                    return null;
                };

                $found = $trySearch($imgName);
                if (!$found && $filenameBase !== '' && $filenameBase !== $imgName) {
                    $found = $trySearch($filenameBase);
                }
                if (!$found && $filenameBase !== '') {
                    $found = $trySlug($filenameBase);
                }
                if ($found) {
                    return $found;
                }
            } catch (\Throwable $e) {
                try {
                    WooCommerceLog::create(['action' => 'media.resolve', 'level' => 'error', 'message' => 'WP media search failed (job)', 'context' => ['error' => $e->getMessage()]]);
                } catch (\Throwable $e2) {
                }
            }

            $mime = 'image/jpeg';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $det = finfo_file($finfo, $abs);
                    if ($det) {
                        $mime = $det;
                    }
                    finfo_close($finfo);
                }
            }

            $uploadTimeout = (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 120);
            $retries = (int) env('WOO_WP_MEDIA_RETRIES', 2);
            $sleepMs = (int) env('WOO_WP_MEDIA_RETRY_SLEEP_MS', 800);
            $uploadTimeout = max(1, min(300, $uploadTimeout));
            $retries = max(0, min(5, $retries));
            $sleepMs = max(0, min(5000, $sleepMs));

            $upload = Http::timeout($uploadTimeout)
                ->retry($retries, $sleepMs)
                ->withBasicAuth($username, $appPass)
                ->attach('file', fopen($abs, 'r'), $imgName)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="'.$imgName.'"',
                ])
                ->post($baseUrl.'/wp-json/wp/v2/media');

            if ($upload->successful() || $upload->status() === 201) {
                $body = $upload->json();
                $id = (int) ($body['id'] ?? 0);
                $src = (string) ($body['source_url'] ?? '');
                if ($id > 0) {
                    return ['id' => $id, 'src' => $src];
                }
            } else {
                try {
                    WooCommerceLog::create(['action' => 'media.upload', 'level' => 'error', 'message' => 'WP media upload failed (job)', 'context' => ['status' => $upload->status(), 'body' => $upload->body()]]);
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }
}
