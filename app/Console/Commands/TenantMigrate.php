<?php

namespace App\Console\Commands;

use App\Tenancy\MySqlBackupService;
use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate {--tenant= : Tenant UUID}';
    protected $description = 'Back up and forward-migrate exactly one registered tenant';

    public function handle(TenantOperationRunner $runner, MySqlBackupService $backups): int
    {
        try {
            $result = $runner->run((string) $this->option('tenant'), 'migrate', function ($tenant, $database) use ($backups) {
                $backup = $backups->create($tenant, $database);
                $exit = Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);
                if ($exit !== 0) {
                    throw new \RuntimeException('Tenant migration failed after backup '.$backup->id.'.');
                }
                $version = (int) DB::connection('tenant')->table('migrations')->count();
                $tenant->forceFill(['schema_version' => $version])->save();

                return ['backup_id' => $backup->id, 'schema_version' => $version];
            }, true);
            $this->info('Tenant migrated. Backup '.$result['backup_id'].'; schema '.$result['schema_version'].'.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
