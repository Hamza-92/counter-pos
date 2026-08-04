<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\TenantBackup;
use App\Tenancy\MySqlBackupService;
use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;

class TenantRestore extends Command
{
    protected $signature = 'tenant:restore {--tenant= : Tenant UUID} {--backup= : Verified backup UUID} {--confirm= : tenant:database:backup confirmation}';
    protected $description = 'Restore exactly one tenant from a verified backup after creating a safety backup';

    public function handle(TenantOperationRunner $runner, MySqlBackupService $backups): int
    {
        $tenantId = (string) $this->option('tenant');
        $backupId = (string) $this->option('backup');
        $backup = TenantBackup::query()->where('tenant_id', $tenantId)->find($backupId);
        if (! $backup || ! hash_equals($tenantId.':'.$backup->database_name.':'.$backupId, (string) $this->option('confirm'))) {
            $this->error('Backup selection or confirmation value is invalid.');

            return self::FAILURE;
        }

        try {
            $result = $runner->run($tenantId, 'restore', function ($tenant, $database) use ($backups, $backup) {
                $safety = $backups->create($tenant, $database);
                $backups->restore($tenant, $database, $backup);

                return ['restored_backup_id' => $backup->id, 'safety_backup_id' => $safety->id];
            }, true);
            $this->info('Restore completed. Pre-restore safety backup: '.$result['safety_backup_id']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
