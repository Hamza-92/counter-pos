<?php

namespace App\Console\Commands;

use App\Tenancy\MySqlBackupService;
use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;

class TenantBackupCommand extends Command
{
    protected $signature = 'tenant:backup {--tenant= : Tenant UUID}';
    protected $description = 'Create and checksum one tenant database backup';

    public function handle(TenantOperationRunner $runner, MySqlBackupService $backups): int
    {
        try {
            $backup = $runner->run((string) $this->option('tenant'), 'backup', fn ($tenant, $database) => $backups->create($tenant, $database));
            $this->info('Verified backup created: '.$backup->id);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
