<?php

namespace App\Http\Controllers;

use App\Models\SyncJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncJobController extends BaseController
{
    private const WOO_PRODUCTS_QUEUE_PREFIX = 'woocommerce-sync-';

    /**
     * Manual "no-cron" mode:
     * When the UI polls sync status and the job is queued, run ONE queued batch inline.
     * This makes "Sync now" work on shared hosting without a persistent queue worker.
     */
    private function tickProductsQueueOnce(SyncJob $job): void
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

            $queue = self::WOO_PRODUCTS_QUEUE_PREFIX.(int) $job->id;
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
     * GET /api/woo-sync/latest
     * Returns latest running/cancelling sync job for current user.
     */
    public function latest(Request $request)
    {
        $userId = optional($request->user('api'))->id;
        if (!$userId) {
            return response()->json(['ok' => false, 'error' => 'Unauthenticated'], 401);
        }

        $job = SyncJob::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'running', 'cancelling'])
            ->orderByDesc('id')
            ->first();

        if (!$job) {
            return response()->json(['ok' => true, 'job' => null]);
        }

        return response()->json([
            'ok' => true,
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
            ],
        ]);
    }

    /**
     * GET /api/sync/status/{id}
     */
    public function status(Request $request, int $id)
    {
        $job = SyncJob::query()->findOrFail($id);

        // If this sync is waiting on the next batch and no worker exists, run one batch inline.
        try {
            $stage = (string) ($job->stage ?? '');
            if ($job->status === 'running' && $stage !== '' && str_starts_with($stage, 'queued')) {
                $this->tickProductsQueueOnce($job);
                // Reload after tick (status/stage/heartbeat may have changed)
                $job = SyncJob::query()->findOrFail($id);
            }
        } catch (\Throwable $e) {
        }

        // Stuck detection: if worker heartbeat hasn't moved, mark as failed so UI doesn't hang forever.
        $stuck = false;
        try {
            $stuckAfterSeconds = (int) env('WOO_SYNC_STUCK_SECONDS', 600);
            $stuckAfterSeconds = max(60, min(3600, $stuckAfterSeconds));

            $stage = (string) ($job->stage ?? '');
            $effectiveStuckSeconds = $stuckAfterSeconds;

            // Between batches, the job may be queued waiting for the next worker tick.
            if ($stage !== '' && str_starts_with($stage, 'queued')) {
                $queueWait = (int) env('WOO_SYNC_QUEUE_WAIT_SECONDS', 1800);
                $queueWait = max(120, min(21600, $queueWait));
                $effectiveStuckSeconds = max($effectiveStuckSeconds, $queueWait);
            }

            // Media uploads can legitimately take longer on shared hosting.
            if ($stage === 'media') {
                $uploadTimeout = (int) env('WOO_WP_MEDIA_UPLOAD_TIMEOUT', 60);
                $uploadTimeout = max(1, min(300, $uploadTimeout));
                $effectiveStuckSeconds = max($effectiveStuckSeconds, $uploadTimeout + 60);
            }

            if (in_array((string) $job->status, ['pending', 'running', 'cancelling'], true)) {
                $lastHeartbeat = optional($job->worker_heartbeat_at)->timestamp;
                if ($lastHeartbeat && (time() - (int) $lastHeartbeat) > $effectiveStuckSeconds) {
                    $stuck = true;
                    $job->status = 'failed';
                    $job->stage = 'failed';
                    $job->last_error = 'stuck: no worker heartbeat for '.$effectiveStuckSeconds.'s';
                    $job->cancel_requested = true; // stop worker ASAP if it's still alive
                    $job->finished_at = now();
                    $job->worker_heartbeat_at = now();
                    $job->save();
                }
            }
        } catch (\Throwable $e) {
        }

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'total_items' => (int) $job->total_items,
            'processed_items' => (int) $job->processed_items,
            'success_items' => (int) $job->success_items,
            'failed_items' => (int) $job->failed_items,
            'percentage' => (int) $job->percentage,
            'stage' => $job->stage,
            'current_product_id' => $job->current_product_id,
            'current_sku' => $job->current_sku,
            'last_error' => $job->last_error,
            'cancel_requested' => (bool) $job->cancel_requested,
            'stuck' => $stuck,
            'worker_heartbeat_at' => optional($job->worker_heartbeat_at)->toDateTimeString(),
            'started_at' => optional($job->started_at)->toDateTimeString(),
            'finished_at' => optional($job->finished_at)->toDateTimeString(),
            'updated_at' => optional($job->updated_at)->toDateTimeString(),
        ]);
    }

    /**
     * POST /api/sync/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $job = SyncJob::query()->findOrFail($id);

        // Signal cancel; worker will stop at next product boundary.
        // UX requirement: mark as cancelled immediately (not "cancelling").
        $job->cancel_requested = true;
        $job->status = 'cancelled';
        $job->stage = 'cancelled';
        $job->finished_at = now();
        $job->worker_heartbeat_at = now();
        $job->save();

        return response()->json(['ok' => true]);
    }
}

