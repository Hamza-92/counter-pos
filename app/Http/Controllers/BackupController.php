<?php

namespace App\Http\Controllers;

use App\Models\ControlPlane\TenantBackup;
use App\Models\Setting;
use App\Services\CloudBackupUploader;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Tenancy\MySqlBackupService;
use App\Tenancy\TenantOperationRunner;
use App\Tenancy\TenancyManager;

class BackupController extends Controller
{
    // -------------------- Backup Databse -------------\\

    public function Get_Backup(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'backup', User::class);

        if (config('tenancy.enabled', false)) {
            $tenantId = app(TenancyManager::class)->tenant()->tenantId;
            $backups = TenantBackup::query()
                ->where('tenant_id', $tenantId)
                ->latest()
                ->get();
            $data = $backups->values()->map(fn (TenantBackup $backup, int $index) => [
                'id' => $index + 1,
                'date' => $backup->created_at?->format('Y-m-d H:i:s').' ('.$backup->id.')',
                'size' => $this->formatSizeUnits($backup->size_bytes),
                'status' => $backup->status,
                // Shared-mode backup history is intentionally append-only.
                // Hiding the legacy delete action prevents a misleading 404
                // and, more importantly, prevents accidental loss of the
                // only verified restore point for a customer.
                'deletable' => false,
            ])->all();

            return response()->json(['backups' => $data, 'totalRows' => count($data)]);
        }

        $data = [];
        $id = 0;
        foreach (glob(storage_path().'/app/public/backup/*') as $filename) {
            $item['id'] = $id += 1;
            $item['date'] = basename($filename);
            $size = $this->formatSizeUnits(filesize($filename));
            $item['size'] = $size;

            $data[] = $item;
        }
        $totalRows = count($data);

        return response()->json([
            'backups' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    // -------------------- Generate Databse -------------\\

    public function Generate_Backup(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'backup', User::class);

        if (config('tenancy.enabled', false)) {
            $tenantId = app(TenancyManager::class)->tenant()->tenantId;
            try {
                $backup = app(TenantOperationRunner::class)->run(
                    $tenantId,
                    'backup',
                    fn ($tenant, $database) => app(MySqlBackupService::class)->create($tenant, $database),
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Verified tenant backup generated successfully',
                    'backup_id' => $backup->id,
                    'cloud' => null,
                ]);
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => 'Backup generation failed',
                    'error' => 'Review the application log for the recorded provisioning run.',
                    'cloud' => null,
                ], 500);
            }
        }

        // Run backup command
        $exitCode = Artisan::call('database:backup');
        $output = Artisan::output();
        
        // Check if backup command failed
        if ($exitCode !== 0) {
            // Extract error details from output
            $errorMsg = trim($output);
            
            // Try to extract ERROR_DETAILS if present
            if (preg_match('/ERROR_DETAILS:\s*(.+)/s', $output, $matches)) {
                $errorMsg = trim($matches[1]);
            }
            
            // If no specific error, provide helpful message
            if (empty($errorMsg) || strlen($errorMsg) < 10) {
                $errorMsg = 'Database backup command failed. Common issues:'."\n";
                $errorMsg .= '1. DUMP_PATH in .env is incorrect or mysqldump not found'."\n";
                $errorMsg .= '2. Database credentials (DB_USERNAME, DB_PASSWORD, DB_HOST) are incorrect'."\n";
                $errorMsg .= '3. MySQL server is not running'."\n";
                $errorMsg .= '4. Database user does not have backup permissions'."\n";
                $errorMsg .= '5. Database name (DB_DATABASE) is incorrect';
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Backup generation failed',
                'error' => $errorMsg,
                'cloud' => null,
            ], 500);
        }

        // Wait a moment for file to be fully written (especially on Windows)
        usleep(500000); // 0.5 seconds

        // Local backup remains the primary destination; cloud upload is optional and additive.
        $cloud = null;
        try {
            $dir = storage_path().'/app/public/backup';
            
            // Ensure directory exists
            if (!is_dir($dir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup directory does not exist',
                    'error' => 'Backup directory not found: '.$dir,
                    'cloud' => null,
                ], 500);
            }

            $latest = null;
            $latestMtime = 0;
            $files = glob($dir.'/*.sql');
            
            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No backup file found after generation',
                    'error' => 'Backup command completed but no .sql file was created. Check database credentials and mysqldump path.',
                    'cloud' => null,
                ], 500);
            }

            foreach ($files as $filename) {
                if (!is_file($filename)) {
                    continue;
                }
                $mt = @filemtime($filename) ?: 0;
                if ($mt >= $latestMtime) {
                    $latestMtime = $mt;
                    $latest = $filename;
                }
            }

            if ($latest && file_exists($latest)) {
                // Verify file has content
                $fileSize = filesize($latest);
                if ($fileSize === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Backup file is empty',
                        'error' => 'Backup file was created but is empty (0 bytes). Check database credentials and mysqldump path.',
                        'cloud' => null,
                    ], 500);
                }

                $setting = Setting::whereNull('deleted_at')->first();
                $uploader = new CloudBackupUploader();
                $cloud = $uploader->uploadIfConfigured($latest, basename($latest), $setting);

                // Cloud upload is additive; we always keep the local backup file.
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No backup file found after generation',
                    'error' => 'Backup command completed but no valid backup file was found.',
                    'cloud' => null,
                ], 500);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup generation error',
                'error' => $e->getMessage(),
                'cloud' => null,
            ], 500);
        }

        $message = 'Backup generated successfully';
        if ($cloud && isset($cloud['success']) && $cloud['success']) {
            $message .= ' and uploaded to '.ucfirst($cloud['provider']);
        } elseif ($cloud && isset($cloud['error'])) {
            $message .= ' (cloud upload failed: '.$cloud['error'].')';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'cloud' => $cloud,
        ]);
    }

    // -------------------- Delete Databse -------------\\

    public function Delete_Backup(Request $request, $name)
    {

        $this->authorizeForUser($request->user('api'), 'backup', User::class);

        foreach (glob(storage_path().'/app/public/backup/*') as $filename) {
            $path = storage_path().'/app/public/backup/'.basename($name);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    // -------------------- Fomrmat units -------------\\

    public function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes.' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes.' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}
