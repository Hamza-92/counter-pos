<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class TenantMigrationService
{
    public function __construct(
        private readonly TenantOperationRunner $runner,
        private readonly MySqlBackupService $backups,
        private readonly Migrator $migrator,
    ) {
    }

    public function run(string $tenantId, OutputInterface $output): array
    {
        return $this->runner->run($tenantId, 'migrate', function (Tenant $tenant, TenantDatabase $database) use ($output) {
            return $this->migrator->usingConnection('tenant', function () use ($tenant, $database, $output) {
                $pending = $this->pendingMigrations();
                if ($pending === []) {
                    return ['status' => 'skipped', 'applied' => 0, 'backup_id' => null];
                }

                $output->writeln('  Pending migrations: '.count($pending));
                foreach ($pending as $name) {
                    $output->writeln('    '.$name);
                }
                $output->writeln('  Creating backup before applying migrations...');
                $backup = $this->backups->create($tenant, $database);
                $output->writeln('  Verified backup: '.$backup->id);

                $exit = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations',
                    '--force' => true,
                ], $output);

                if ($exit !== 0 || $this->pendingMigrations() !== []) {
                    throw new RuntimeException('Migration did not complete. Backup: '.$backup->id.'. Review the migration output before retrying.');
                }

                $version = count($this->migrator->getRepository()->getRan());
                $tenant->forceFill(['schema_version' => $version])->save();

                return ['status' => 'migrated', 'applied' => count($pending), 'backup_id' => $backup->id, 'schema_version' => $version];
            });
        }, true);
    }

    public function pendingMigrations(): array
    {
        if (! $this->migrator->repositoryExists()) {
            throw new RuntimeException('No migrations table found. Provision or inspect this tenant separately before running bulk migrations.');
        }

        // Only root tenant migrations; never include database/migrations/control.
        $files = $this->migrator->getMigrationFiles(database_path('migrations'));

        return array_values(array_diff(array_keys($files), $this->migrator->getRepository()->getRan()));
    }
}
