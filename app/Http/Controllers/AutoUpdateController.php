<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\sms_gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class AutoUpdateController extends Controller
{
    public function oneClickUpdate(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');
        ignore_user_abort(true);

        $this->logUpdate('=== One-Click Update started at '.now().' ===');
        $this->updateProgress('starting', 0);

        // Prevent concurrent updates
        if (! $this->createUpdateLock()) {
            return response()->json(['message' => 'Another update is in progress'], 409);
        }

        try {
            $codeDeployed = false;
            $migrationsDone = false;
            $appBackupPath = null;
            $dbBackupPath = null;
            $latest = $this->getLastVersion();
            if (! isset($latest['version'])) {
                $this->logUpdate('Latest version info missing version field');

                return response()->json(['message' => 'Invalid update feed'], 400);
            }

            $current = trim($this->getCurrentVersion());
            $newVersion = $latest['version'];
            if (! version_compare($newVersion, $current, '>')) {
                $this->logUpdate("Already up-to-date (current: {$current})");

                return response()->json(['message' => 'Already up-to-date', 'version' => $current]);
            }

            $this->logUpdate('Putting application into maintenance mode');
            Artisan::call('down');

            $this->logUpdate('Creating database backup');
            Artisan::call('database:backup');
            $this->logUpdate('Database backup created');
            $dbBackupPath = $this->findLatestDatabaseBackup();
            $this->updateProgress('database_backup_created', 10);

            $this->logUpdate('Creating application backup ZIP');
            $appBackupPath = $this->createApplicationBackupZip();
            $this->logUpdate('Application backup created at: '.$appBackupPath);
            $this->updateProgress('app_backup_created', 20);

            $downloadUrl = $latest['download_url'] ?? ($latest['url'] ?? null);
            if (! $downloadUrl) {
                $this->logUpdate('Update URL not found');
                Artisan::call('up');

                return response()->json(['message' => 'Update URL not found'], 400);
            }

            if (! $this->isAllowedHost($downloadUrl, ['update-stocky.ui-lib.com'])) {
                $this->logUpdate('Blocked update from untrusted host: '.$downloadUrl);
                Artisan::call('up');

                return response()->json(['message' => 'Untrusted update host'], 400);
            }

            $tmpDir = storage_path('app/updates');
            if (! File::exists($tmpDir)) {
                File::makeDirectory($tmpDir, 0755, true);
            }
            $zipPath = $tmpDir.'/update-'.$newVersion.'.zip';
            $this->logUpdate('Downloading update package from: '.$downloadUrl);
            $this->downloadFile($downloadUrl, $zipPath);
            $this->logUpdate('Download complete: '.$zipPath);
            $this->updateProgress('download_complete', 35);

            if (! empty($latest['checksum'])) {
                $this->logUpdate('Verifying checksum');
                if (! $this->verifyChecksum($zipPath, $latest['checksum'])) {
                    $this->logUpdate('Checksum verification failed');
                    Artisan::call('up');

                    return response()->json(['message' => 'Checksum verification failed'], 400);
                }
                $this->logUpdate('Checksum verification passed');
            }

            // Validate zip structure before any delete/extract
            $zipIsValid = $this->zipIsValid($zipPath);
            if (! $zipIsValid) {
                $this->logUpdate('ZIP validation failed');
                Artisan::call('up');

                return response()->json(['message' => 'Invalid update package'], 400);
            }
            $this->updateProgress('zip_validated', 40);

            // Enforce HTTPS and check forbidden paths within ZIP
            if (! $this->isHttpsUrl($downloadUrl)) {
                $this->logUpdate('Download URL is not HTTPS');
                Artisan::call('up');

                return response()->json(['message' => 'Insecure download URL'], 400);
            }
            if (! $this->zipDoesNotContainForbidden($zipPath)) {
                $this->logUpdate('ZIP contains forbidden paths (.env or storage)');
                Artisan::call('up');

                return response()->json(['message' => 'Forbidden content in update package'], 400);
            }

            // Disk space check (zip size * 3 + 100MB headroom)
            $zipSize = File::size($zipPath) ?: 0;
            $required = ($zipSize * 3) + (100 * 1024 * 1024);
            if (! $this->hasEnoughDiskSpace([base_path(), storage_path('app')], $required)) {
                $this->logUpdate('Insufficient disk space for update');
                Artisan::call('up');

                return response()->json(['message' => 'Insufficient disk space'], 507);
            }

            $this->logUpdate('Extracting package');
            $extractPath = $tmpDir.'/extracted-'.$newVersion;
            if (File::exists($extractPath)) {
                if ($zipIsValid) {
                    File::deleteDirectory($extractPath);
                    $this->logUpdate('Removed previous extract directory: '.$extractPath);
                } else {
                    $this->logUpdate('Skipped removing previous extract dir due to invalid ZIP');
                }
            }
            $this->unzip($zipPath, $extractPath);
            $this->logUpdate('Extraction done: '.$extractPath);
            $this->updateProgress('extracted', 50);

            $mergeSource = $this->detectRootContent($extractPath);

            // Verify extracted version consistency
            $extractedVersion = $this->readVersionFromPath($mergeSource);
            if ($extractedVersion && trim($extractedVersion) !== trim($newVersion)) {
                $this->logUpdate('Version mismatch. Remote: '.$newVersion.' Extracted: '.$extractedVersion);
                Artisan::call('up');

                return response()->json(['message' => 'Version mismatch between metadata and package'], 400);
            }
            $this->logUpdate('Cleaning existing application (excluding protected paths)');
            $this->cleanBaseExcept(base_path(), [
                '.env',
                'storage',
                'public/images',
                'Modules',
                'modules_statuses.json',
            ]);
            $this->logUpdate('Deploying new files to application directory');
            $this->copyIntoApplication($mergeSource, base_path(), [
                '.env',
                'storage',
                'public/images',
                'Modules',
                'modules_statuses.json',
            ]);
            $this->logUpdate('Deployment completed');
            $codeDeployed = true;
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $this->updateProgress('deployment_completed', 70);

            $this->logUpdate('Running database migrations');
            $this->runMigrations();
            $migrationsDone = true;
            $this->logUpdate('Migrations completed');
            $this->updateProgress('migrations_completed', 85);

            $this->logUpdate('Running finalization');
            $this->runFinalizationTasks();
            $this->logUpdate('Finalization completed');
            $this->updateProgress('finalization_completed', 95);

            $this->logUpdate('Clearing caches');
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            Artisan::call('up');
            $this->logUpdate("=== One-Click Update finished successfully to {$newVersion} at ".now().' ===');
            $this->appendHistory('success', $newVersion);
            $this->updateProgress('completed', 100);

            // Cleanup only if ZIP exists and is valid
            if (File::exists($extractPath) && $zipIsValid) {
                File::deleteDirectory($extractPath);
                $this->logUpdate('Cleaned extract directory');
            }
            if (File::exists($zipPath) && $zipIsValid) {
                File::delete($zipPath);
                $this->logUpdate('Deleted update ZIP');
            }

            $this->releaseUpdateLock();

            return response()->json([
                'message' => 'Successfully Updated',
                'version' => $newVersion,
            ]);
        } catch (\Throwable $e) {
            $this->logUpdate('Update failed: '.$e->getMessage());
            $this->updateProgress('failed', 0);
            // Rollback process
            try {
                if (! empty($appBackupPath) && File::exists($appBackupPath)) {
                    $this->logUpdate('Starting code rollback from backup');
                    $this->restoreCodeFromBackup($appBackupPath, base_path());
                    $this->logUpdate('Code rollback completed');
                    if (function_exists('opcache_reset')) {
                        @opcache_reset();
                    }
                } else {
                    $this->logUpdate('No code backup ZIP found for rollback');
                }

                if ($migrationsDone) {
                    $this->logUpdate('Attempting database rollback');
                    $sql = $dbBackupPath ?: $this->findLatestDatabaseBackup();
                    if ($sql && File::exists($sql)) {
                        $restored = $this->restoreDatabaseFromSql($sql);
                        $this->logUpdate($restored ? 'Database restored from SQL backup' : 'Database restore from SQL backup failed, attempting migrate:rollback');
                        if (! $restored) {
                            try {
                                Artisan::call('migrate:rollback', ['--force' => true]);
                            } catch (\Throwable $mr) {
                            }
                        }
                    } else {
                        try {
                            Artisan::call('migrate:rollback', ['--force' => true]);
                        } catch (\Throwable $mr) {
                        }
                    }
                }

                // Rebuild caches after rollback
                try {
                    Artisan::call('optimize:clear');
                    Artisan::call('cache:clear');
                    Artisan::call('config:clear');
                    Artisan::call('view:clear');
                    Artisan::call('route:clear');
                } catch (\Throwable $cc) {
                }
                $this->logUpdate('Rollback executed successfully');
                $this->appendHistory('rollback', $current ?? 'unknown');
            } catch (\Throwable $rb) {
                $this->logUpdate('Rollback error: '.$rb->getMessage());
            }

            try {
                Artisan::call('up');
            } catch (\Throwable $ignored) {
            }
            $this->releaseUpdateLock();

            return response()->json(['message' => 'Update failed and rollback executed', 'error' => $e->getMessage()], 500);
        }
    }

    private function getCurrentVersion()
    {
        $version = File::get(base_path().'/version.txt');

        return trim($version);
    }

    private function getLastVersion()
    {
        $content = file_get_contents('https://update-stocky.ui-lib.com/stocky_version.json');

        return json_decode($content, true);
    }

    private function isAllowedHost(string $url, array $allowedHosts): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        return in_array($host, $allowedHosts, true);
    }

    private function logUpdate(string $message): void
    {
        $path = storage_path('logs/updater.log');
        // simple rotation if > 10MB
        try {
            if (File::exists($path) && File::size($path) > 10 * 1024 * 1024) {
                $rotated = storage_path('logs/updater-'.now()->format('Ymd-His').'.log');
                @File::move($path, $rotated);
            }
        } catch (\Throwable $e) {
        }
        @File::append($path, '['.now().'] '.$message.PHP_EOL);
    }

    private function downloadFile(string $url, string $dest): void
    {
        // Use stream context with timeout and follow_location
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1,
                'header' => "User-Agent: Stocky-Updater\r\n",
            ],
            'https' => [
                'timeout' => 60,
                'follow_location' => 1,
                'header' => "User-Agent: Stocky-Updater\r\n",
            ],
        ]);

        $in = @fopen($url, 'rb', false, $context);
        if (! $in) {
            $err = error_get_last();
            throw new \RuntimeException('Unable to open URL: '.$url.' '.($err['message'] ?? ''));
        }
        $out = @fopen($dest, 'wb');
        if (! $out) {
            fclose($in);
            throw new \RuntimeException('Unable to write file: '.$dest);
        }
        if (@stream_copy_to_stream($in, $out) === false) {
            fclose($in);
            fclose($out);
            throw new \RuntimeException('Failed to download file stream');
        }
        fclose($in);
        fclose($out);
    }

    private function verifyChecksum(string $file, string $expectedSha256): bool
    {
        $hash = hash_file('sha256', $file);
        $ok = hash_equals(strtolower($expectedSha256), strtolower($hash));
        if (! $ok) {
            return false;
        }
        // Optional signature verification if provided
        $pubKey = env('UPDATE_PUBLIC_KEY');
        if ($pubKey && function_exists('openssl_verify')) {
            try {
                $latest = $this->getLastVersion();
                if (! empty($latest['signature'])) {
                    $signature = base64_decode($latest['signature'], true);
                    if ($signature !== false) {
                        $pkey = @openssl_pkey_get_public($pubKey);
                        if ($pkey) {
                            $data = $latest['checksum'] ?? ($latest['version'] ?? '');
                            $vr = @openssl_verify($data, $signature, $pkey, OPENSSL_ALGO_SHA256);
                            if ($vr !== 1) {
                                $this->logUpdate('Signature verification failed');

                                return false;
                            }
                        } else {
                            $this->logUpdate('Invalid UPDATE_PUBLIC_KEY');
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->logUpdate('Signature verify error: '.$e->getMessage());
            }
        }

        return true;
    }

    private function unzip(string $zipPath, string $extractTo): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open update zip file');
        }
        if (! File::exists($extractTo)) {
            File::makeDirectory($extractTo, 0755, true);
        }
        if (! $zip->extractTo($extractTo)) {
            $zip->close();
            throw new \RuntimeException('Failed extracting update zip file');
        }
        $zip->close();
    }

    private function detectRootContent(string $path): string
    {
        $items = array_values(array_filter(scandir($path), function ($i) {
            return ! in_array($i, ['.', '..']);
        }));
        if (count($items) === 1 && is_dir($path.DIRECTORY_SEPARATOR.$items[0])) {
            return $path.DIRECTORY_SEPARATOR.$items[0];
        }

        return $path;
    }

    private function mergeIntoApplication(string $source, string $destination, array $excludeRelativePaths): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relPath = ltrim(str_replace($source, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            if ($this->containsPathTraversal($relPath)) {
                continue;
            }
            if ($this->isExcluded($relPath, $excludeRelativePaths)) {
                continue;
            }

            $targetPath = $destination.DIRECTORY_SEPARATOR.$relPath;
            if ($item->isDir()) {
                if (! File::exists($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true);
                }
            } else {
                $dir = dirname($targetPath);
                if (! File::exists($dir)) {
                    // Try to create, skip if permission denied
                    if (! @File::makeDirectory($dir, 0755, true)) {
                        $this->logUpdate('Skip copy (cannot create dir): '.$dir);

                        continue;
                    }
                } elseif (! is_writable($dir)) {
                    $this->logUpdate('Skip copy (dir not writable): '.$dir);

                    continue;
                }
                if (File::exists($targetPath) && ! is_writable($targetPath)) {
                    $this->logUpdate('Skip copy (file not writable): '.$targetPath);

                    continue;
                }
                if (! @File::copy($item->getPathname(), $targetPath)) {
                    $this->logUpdate('Failed to copy file: '.$item->getPathname().' -> '.$targetPath);
                }
            }
        }
    }

    private function isExcluded(string $relativePath, array $exclusions): bool
    {
        foreach ($exclusions as $exclude) {
            $exclude = trim($exclude, '/\\');
            if ($exclude === '') {
                continue;
            }
            if ($relativePath === $exclude) {
                return true;
            }
            if (strpos($relativePath, $exclude.DIRECTORY_SEPARATOR) === 0) {
                return true;
            }
        }

        return false;
    }

    private function rrmdir(string $dir): void
    {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function copyIntoApplication(string $source, string $destination, array $excludeRelativePaths): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relPath = ltrim(str_replace($source, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            if ($this->containsPathTraversal($relPath)) {
                continue;
            }
            if ($this->isExcluded($relPath, $excludeRelativePaths)) {
                continue;
            }

            $targetPath = $destination.DIRECTORY_SEPARATOR.$relPath;
            if ($item->isDir()) {
                if (! File::exists($targetPath)) {
                    if (! @File::makeDirectory($targetPath, 0755, true)) {
                        $this->logUpdate('Skip dir create (permission denied): '.$targetPath);
                    }
                }
            } else {
                $dir = dirname($targetPath);
                if (! File::exists($dir)) {
                    if (! @File::makeDirectory($dir, 0755, true)) {
                        $this->logUpdate('Skip copy (cannot create dir): '.$dir);

                        continue;
                    }
                } elseif (! is_writable($dir)) {
                    $this->logUpdate('Skip copy (dir not writable): '.$dir);

                    continue;
                }
                if (File::exists($targetPath) && ! is_writable($targetPath)) {
                    $this->logUpdate('Skip copy (file not writable): '.$targetPath);

                    continue;
                }
                if (! @File::copy($item->getPathname(), $targetPath)) {
                    $this->logUpdate('Failed to copy file: '.$item->getPathname().' -> '.$targetPath);
                }
            }
        }
    }

    private function cleanBaseExcept(string $base, array $excludeRelativePaths): void
    {
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $items = array_values(array_filter(scandir($base), function ($i) {
            return ! in_array($i, ['.', '..']);
        }));

        foreach ($items as $name) {
            $fullPath = $base.DIRECTORY_SEPARATOR.$name;
            $relPath = $name;

            if ($this->isExcluded($relPath, $excludeRelativePaths)) {
                continue;
            }

            if (is_dir($fullPath)) {
                if (! is_writable($fullPath)) {
                    $this->logUpdate('Skip delete (dir not writable): '.$fullPath);

                    continue;
                }
                $this->deletePathContentsExcept($fullPath, $excludeRelativePaths, $base);
                if (File::isDirectory($fullPath) && count(array_diff(scandir($fullPath), ['.', '..'])) === 0) {
                    File::deleteDirectory($fullPath);
                }
            } else {
                if (! is_writable($fullPath)) {
                    $this->logUpdate('Skip delete (file not writable): '.$fullPath);

                    continue;
                }
                File::delete($fullPath);
            }
        }
    }

    private function deletePathContentsExcept(string $path, array $excludeRelativePaths, string $base): void
    {
        $items = @scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$item;
            $rel = ltrim(str_replace($base.DIRECTORY_SEPARATOR, '', $full), DIRECTORY_SEPARATOR);

            if ($this->isExcluded($rel, $excludeRelativePaths)) {
                continue;
            }

            if (is_dir($full)) {
                if (! is_writable($full)) {
                    $this->logUpdate('Skip delete (dir not writable): '.$full);

                    continue;
                }
                File::deleteDirectory($full);
            } else {
                if (! is_writable($full)) {
                    $this->logUpdate('Skip delete (file not writable): '.$full);

                    continue;
                }
                File::delete($full);
            }
        }
    }

    private function hasEnoughDiskSpace(array $paths, int $requiredBytes): bool
    {
        foreach ($paths as $p) {
            try {
                $free = @disk_free_space($p);
                if ($free !== false && $free < $requiredBytes) {
                    return false;
                }
            } catch (\Throwable $e) {
                // If we cannot determine, continue to next path
            }
        }

        return true;
    }

    private function containsPathTraversal(string $rel): bool
    {
        if (strpos($rel, '..') !== false) {
            return true;
        }
        $relNorm = str_replace('\\', '/', $rel);
        if (isset($relNorm[0]) && ($relNorm[0] === '/' || $relNorm[0] === '\\')) {
            return true;
        }
        if (preg_match('/^[a-zA-Z]:\//', $relNorm)) {
            return true;
        }

        return false;
    }

    private function isHttpsUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return strtolower((string) $scheme) === 'https';
    }

    private function zipDoesNotContainForbidden(string $zipPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return false;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat || ! isset($stat['name'])) {
                continue;
            }
            $name = str_replace('\\', '/', $stat['name']);
            $lower = strtolower($name);
            if ($lower === '.env' || strpos($lower, '/.env') !== false) {
                $zip->close();

                return false;
            }
            if (strpos($lower, 'storage/') === 0) {
                $zip->close();

                return false;
            }
            if (strpos($lower, 'public/images') === 0) {
                $zip->close();

                return false;
            }
            if (strpos($lower, 'modules/') === 0 || $lower === 'modules_statuses.json') {
                $zip->close();

                return false;
            }
        }
        $zip->close();

        return true;
    }

    private function readVersionFromPath(string $path): ?string
    {
        $file = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'version.txt';
        try {
            if (File::exists($file)) {
                return trim(File::get($file));
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function createUpdateLock(): bool
    {
        $lock = storage_path('app/updater.lock');
        if (File::exists($lock)) {
            // If lock is older than 2 hours, remove it (stale)
            $age = time() - @filemtime($lock);
            if ($age !== false && $age > 7200) {
                File::delete($lock);
            } else {
                return false;
            }
        }
        try {
            File::put($lock, 'locked at '.now());

            return true;
        } catch (\Throwable $e) {
            $this->logUpdate('Cannot create updater lock: '.$e->getMessage());

            return false;
        }
    }

    private function releaseUpdateLock(): void
    {
        $lock = storage_path('app/updater.lock');
        try {
            if (File::exists($lock)) {
                File::delete($lock);
            }
        } catch (\Throwable $e) {
        }
    }

    private function createApplicationBackupZip(): string
    {
        $backupDir = storage_path('app/public/backup');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd-His');
        $zipPath = $backupDir.'/app-backup-'.$timestamp.'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create backup zip at '.$zipPath);
        }

        $base = base_path();

        // Exclude only updater temp/backup directories to avoid recursion; include everything else
        $excludePaths = [
            'storage/app/updates',
            'storage/app/public/backup',
        ];

        $normalize = function (string $path): string {
            return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        };
        $isExcluded = function (string $relative) use ($excludePaths, $normalize): bool {
            $relative = trim($normalize($relative), DIRECTORY_SEPARATOR);
            foreach ($excludePaths as $ex) {
                $ex = trim($normalize($ex), DIRECTORY_SEPARATOR);
                if ($ex === '') {
                    continue;
                }
                if ($relative === $ex) {
                    return true;
                }
                if (strpos($relative, $ex.DIRECTORY_SEPARATOR) === 0) {
                    return true;
                }
            }

            return false;
        };

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $full = $item->getPathname();
            $rel = ltrim(str_replace($base, '', $full), DIRECTORY_SEPARATOR);
            if ($isExcluded($rel)) {
                continue;
            }
            // Skip updater.lock and progress files to avoid restoring stale state
            $relLower = strtolower($rel);
            if ($relLower === 'storage/app/updater.lock' || $relLower === 'storage/app/update_progress.json') {
                continue;
            }
            if ($item->isDir()) {
                // Ensure directory entry exists in zip for empty directories
                if ($rel !== '') {
                    $zip->addEmptyDir($rel);
                }
            } else {
                $zip->addFile($full, $rel);
            }
        }

        $zip->close();

        return $zipPath;
    }

    private function zipIsValid(string $zipPath): bool
    {
        if (! File::exists($zipPath) || File::size($zipPath) <= 0) {
            return false;
        }
        $zip = new ZipArchive;
        $result = $zip->open($zipPath);
        if ($result === true) {
            $zip->close();

            return true;
        }

        return false;
    }

    private function runFinalizationTasks(): void
    {
        Artisan::call('config:cache');
        Artisan::call('config:clear');

        $role = Role::findOrFail(1);
        $role->permissions()->detach();

        $permissions = [
            0 => 'view_employee',
            1 => 'add_employee',
            2 => 'edit_employee',
            3 => 'delete_employee',
            4 => 'company',
            5 => 'department',
            6 => 'designation',
            7 => 'office_shift',
            8 => 'attendance',
            9 => 'leave',
            10 => 'holiday',
            11 => 'Top_products',
            12 => 'Top_customers',
            13 => 'shipment',
            14 => 'users_report',
            15 => 'stock_report',
            16 => 'sms_settings',
            17 => 'pos_settings',
            18 => 'payment_gateway',
            19 => 'mail_settings',
            20 => 'dashboard',
            21 => 'pay_due',
            22 => 'pay_sale_return_due',
            23 => 'pay_supplier_due',
            24 => 'pay_purchase_return_due',
            25 => 'product_report',
            26 => 'product_sales_report',
            27 => 'product_purchases_report',
            28 => 'notification_template',
            29 => 'edit_product_sale',
            30 => 'edit_product_purchase',
            31 => 'edit_product_quotation',
            32 => 'edit_tax_discount_shipping_sale',
            33 => 'edit_tax_discount_shipping_purchase',
            34 => 'edit_tax_discount_shipping_quotation',
            35 => 'module_settings',
            36 => 'count_stock',
            37 => 'deposit_add',
            38 => 'deposit_delete',
            39 => 'deposit_edit',
            40 => 'deposit_view',
            41 => 'account',
            42 => 'inventory_valuation',
            43 => 'expenses_report',
            44 => 'deposits_report',
            45 => 'transfer_money',
            46 => 'payroll',
            47 => 'projects',
            48 => 'tasks',
            49 => 'appearance_settings',
            50 => 'translations_settings',
            51 => 'subscription_product',
            52 => 'report_error_logs',
            53 => 'payment_methods',
            54 => 'report_transactions',
            55 => 'report_sales_by_category',
            56 => 'report_sales_by_brand',
            57 => 'opening_stock_import',
            58 => 'seller_report',
            59 => 'Store_settings_view',
            60 => 'Orders_view',
            61 => 'Collections_view',
            62 => 'Banners_view',
            63 => 'inactive_customers_report',
            64 => 'zeroSalesProducts',
            65 => 'Dead_Stock_Report',
            66 => 'draft_invoices_report',
            67 => 'discount_summary_report',
            68 => 'tax_summary_report',
            69 => 'Stock_Aging_Report',
            70 => 'Stock_Transfer_Report',
            71 => 'Stock_Adjustment_Report',
            72 => 'Top_Suppliers_Report',
            73 => 'Subscribers_view',
            74 => 'Messages_view',
            75 => 'cash_register_report',
            76 => 'woocommerce_settings',
            77 => 'customer_display_screen_setup',
            78 => 'quickbooks_settings',
            79 => 'service_jobs',
        ];

        foreach ($permissions as $permission_slug) {
            Permission::firstOrCreate(['name' => $permission_slug]);
        }
        $permissions_data = Permission::pluck('id')->toArray();
        $role->permissions()->attach($permissions_data);

        sms_gateway::firstOrCreate(['title' => 'infobip']);
        sms_gateway::firstOrCreate(['title' => 'termii']);
        sms_gateway::firstOrCreate(['title' => 'custom']);
        $nexmoGateway = sms_gateway::where('title', 'nexmo')->first();
        if ($nexmoGateway) {
            $nexmoGateway->delete();
        }

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\TranslationSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\StoreSettingSeeder', '--force' => true]);

        \App\Models\Product::where('name', 'REGEXP', '<|>')
            ->chunkById(200, function ($products) {
                foreach ($products as $p) {
                    $old = $p->name;
                    $clean = str_replace(['<', '>'], ['‹', '›'], strip_tags($old));
                    if ($clean !== $old) {
                        $p->name = $clean;
                        $p->save();
                    }
                }
            });
    }

    private function runMigrations(): void
    {
        try {
            DB::beginTransaction();
            Artisan::call('migrate', ['--force' => true]);
            DB::commit();
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $ignored) {
            }
            throw $e;
        }
    }

    private function updateProgress(string $step, int $percent): void
    {
        $payload = [
            'step' => $step,
            'percent' => max(0, min(100, $percent)),
            'time' => now()->toDateTimeString(),
        ];
        try {
            File::put(storage_path('app/update_progress.json'), json_encode($payload));
        } catch (\Throwable $e) {
        }
    }

    public function progress(): \Illuminate\Http\JsonResponse
    {
        $file = storage_path('app/update_progress.json');
        if (File::exists($file)) {
            $json = json_decode(File::get($file), true) ?: [];

            return response()->json($json);
        }

        return response()->json(['step' => 'idle', 'percent' => 0]);
    }

    private function appendHistory(string $status, string $version): void
    {
        $file = storage_path('app/update_history.json');
        $hist = [];
        try {
            if (File::exists($file)) {
                $hist = json_decode(File::get($file), true) ?: [];
            }
        } catch (\Throwable $e) {
        }
        $hist[] = ['status' => $status, 'version' => $version, 'time' => now()->toDateTimeString()];
        try {
            File::put($file, json_encode($hist));
        } catch (\Throwable $e) {
        }
    }

    public function preflight(): \Illuminate\Http\JsonResponse
    {
        $checks = [];
        // Tool paths
        $checks['DUMP_PATH'] = ['value' => env('DUMP_PATH'), 'exists' => $this->pathExists(env('DUMP_PATH'))];
        $checks['MYSQL_PATH'] = ['value' => env('MYSQL_PATH'), 'exists' => $this->pathExists(env('MYSQL_PATH'))];
        // Writable paths
        $paths = [
            'base' => base_path(),
            'storage' => storage_path(),
            'storage_app' => storage_path('app'),
            'storage_logs' => storage_path('logs'),
            'bootstrap_cache' => base_path('bootstrap/cache'),
            'public' => public_path(),
        ];
        $perms = [];
        foreach ($paths as $k => $p) {
            $perms[$k] = ['path' => $p, 'writable' => is_writable($p)];
        }
        // Network/version JSON
        $net = ['ok' => false, 'error' => null];
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 10], 'https' => ['timeout' => 10]]);
            $json = @file_get_contents('https://update-stocky.ui-lib.com/stocky_version.json', false, $ctx);
            if ($json !== false) {
                $net['ok'] = true;
                $net['size'] = strlen($json);
            }
        } catch (\Throwable $e) {
            $net['error'] = $e->getMessage();
        }
        // Disk space
        $freeBase = @disk_free_space(base_path());
        $freeStorage = @disk_free_space(storage_path('app'));
        $ok = ($checks['DUMP_PATH']['exists'] || true) && $perms['storage']['writable'] && $perms['bootstrap_cache']['writable'] && $net['ok'];

        return response()->json([
            'ok' => (bool) $ok,
            'tools' => $checks,
            'permissions' => $perms,
            'network' => $net,
            'disk' => ['base_free' => $freeBase, 'storage_free' => $freeStorage],
        ]);
    }

    private function pathExists(?string $p): bool
    {
        if (! $p) {
            return false;
        }
        try {
            return file_exists($p);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function restoreCodeFromBackup(string $backupZipPath, string $destination): void
    {
        // Extract backup ZIP to temp and deploy (preserving protected paths)
        $tmp = storage_path('app/updates/restore-'.uniqid());
        File::makeDirectory($tmp, 0755, true);
        $this->unzip($backupZipPath, $tmp);
        $root = $this->detectRootContent($tmp);
        $this->cleanBaseExcept($destination, ['.env', 'storage', 'public/images', 'Modules', 'modules_statuses.json']);
        $this->copyIntoApplication($root, $destination, ['.env', 'storage', 'public/images', 'Modules', 'modules_statuses.json']);
        File::deleteDirectory($tmp);
    }

    private function findLatestDatabaseBackup(): ?string
    {
        $dir = storage_path('app/public/backup');
        if (! File::exists($dir)) {
            return null;
        }
        $files = array_values(array_filter(scandir($dir), function ($f) use ($dir) {
            return ! in_array($f, ['.', '..']) && is_file($dir.DIRECTORY_SEPARATOR.$f) && substr($f, -4) === '.sql';
        }));
        if (empty($files)) {
            return null;
        }
        usort($files, function ($a, $b) use ($dir) {
            return filemtime($dir.DIRECTORY_SEPARATOR.$b) <=> filemtime($dir.DIRECTORY_SEPARATOR.$a);
        });

        return $dir.DIRECTORY_SEPARATOR.$files[0];
    }

    private function restoreDatabaseFromSql(string $sqlPath): bool
    {
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbName = env('DB_DATABASE');
        $mysqlBin = env('MYSQL_PATH') ?: env('DB_RESTORE_PATH') ?: 'mysql';

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $quote = function ($s) use ($isWindows) {
            if ($isWindows) {
                // Wrap in double quotes for Windows
                $s = str_replace('"', '"', $s);

                return '"'.$s.'"';
            }

            return escapeshellarg($s);
        };

        $userArg = '--user='.$quote($dbUser);
        $passArg = $dbPass !== '' ? '--password='.$quote($dbPass) : '--password=';
        $hostArg = '--host='.$quote($dbHost);
        $dbArg = $quote($dbName);
        $fileArg = $quote($sqlPath);

        $cmd = $quote($mysqlBin)." $userArg $passArg $hostArg $dbArg < $fileArg";

        $shell = $isWindows ? 'cmd /C ' : '/bin/sh -c ';
        $output = [];
        $ret = 1;
        @exec($shell.$cmd, $output, $ret);

        return $ret === 0;
    }
}
