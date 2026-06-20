<?php

namespace App\Http\Controllers;

use App\Models\User;
use ArPHP\I18N\Arabic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    /**
     * Return system health metrics as JSON (secured by system_health_view permission).
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'system_health_view', User::class);

        $metrics = $this->collectMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Download system health report as PDF.
     */
    public function pdf(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'system_health_view', User::class);

        $metrics = $this->collectMetrics();
        $setting = \App\Models\Setting::first();
        $companyName = $setting ? ($setting->CompanyName ?? config('app.name')) : config('app.name');

        $html = view('pdf.system_health_pdf', [
            'metrics' => $metrics,
            'companyName' => $companyName,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $html = substr_replace($html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = \PDF::loadHTML($html, 'UTF-8');

        return $pdf->download('system-health-report.pdf');
    }

    /**
     * Collect all system health metrics.
     *
     * @return array<string, mixed>
     */
    protected function collectMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => \Illuminate\Foundation\Application::VERSION,
            'environment' => config('app.env'),
            'database' => $this->getDatabaseMetrics(),
            'storage' => $this->getStorageMetrics(),
            'queue' => $this->getQueueMetrics(),
            'last_backup' => $this->getLastBackupDate(),
        ];
    }

    protected function getDatabaseMetrics(): array
    {
        try {
            $database = config('database.connections.'.config('database.default').'.database');
            $result = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?',
                [$database]
            );
            $bytes = $result && isset($result->size) ? (int) $result->size : 0;

            return [
                'size_bytes' => $bytes,
                'size_human' => $this->formatBytes($bytes),
                'connection' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'size_bytes' => null,
                'size_human' => null,
                'connection' => config('database.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getStorageMetrics(): array
    {
        $storagePath = storage_path();
        $bytes = $this->getDirectorySize($storagePath, 50000);

        return [
            'storage_path' => $storagePath,
            'size_bytes' => $bytes,
            'size_human' => $this->formatBytes($bytes),
        ];
    }

    protected function getQueueMetrics(): array
    {
        $driver = config('queue.default');
        $status = [
            'driver' => $driver,
            'running' => null,
            'pending' => null,
            'failed' => null,
        ];

        if ($driver === 'database') {
            try {
                $status['pending'] = (int) DB::table('jobs')->count();
                $status['failed'] = \Schema::hasTable('failed_jobs')
                    ? (int) DB::table('failed_jobs')->count()
                    : 0;
                $status['running'] = 0;
            } catch (\Throwable $e) {
                $status['error'] = $e->getMessage();
            }
        } elseif ($driver === 'redis') {
            try {
                $redis = app('redis')->connection(config('queue.connections.redis.connection', 'default'));
                $queue = config('queue.connections.redis.queue', 'default');
                $status['pending'] = $redis->llen('queues:'.$queue);
                $status['failed'] = 0;
                $status['running'] = 0;
            } catch (\Throwable $e) {
                $status['error'] = $e->getMessage();
            }
        }

        return $status;
    }

    protected function getLastBackupDate(): ?array
    {
        $dir = storage_path('app/public/backup');
        if (! is_dir($dir)) {
            return ['date' => null, 'human' => null, 'message' => 'Backup directory not found'];
        }

        $latestMtime = 0;
        $latestName = null;
        foreach (glob($dir.'/*.sql') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            $mtime = @filemtime($file) ?: 0;
            if ($mtime >= $latestMtime) {
                $latestMtime = $mtime;
                $latestName = basename($file);
            }
        }

        if ($latestMtime === 0) {
            return ['date' => null, 'human' => null, 'message' => 'No backups found'];
        }

        $dt = \Carbon\Carbon::createFromTimestamp($latestMtime);

        return [
            'date' => $dt->toIso8601String(),
            'human' => $dt->format('Y-m-d H:i:s'),
            'filename' => $latestName,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Get approximate directory size (cap file count to avoid timeout).
     */
    protected function getDirectorySize(string $path, int $maxFiles = 50000): int
    {
        $size = 0;
        $count = 0;
        if (! is_dir($path)) {
            return 0;
        }
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    if (++$count >= $maxFiles) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }
}
