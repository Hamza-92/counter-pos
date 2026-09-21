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
    ) {}

    public function run(string $tenantId, OutputInterface $output, string $source = 'control_panel', ?string $externalReference = null, bool $allowInitialProvisioning = false): array
    {
        return $this->runner->run($tenantId, 'migrate', function (Tenant $tenant, TenantDatabase $database) use ($output, $allowInitialProvisioning) {
            return $this->migrator->usingConnection('tenant', function () use ($tenant, $database, $output, $allowInitialProvisioning) {
                $freshDatabase = ! $this->migrator->repositoryExists();
                $pending = $this->pendingMigrations($allowInitialProvisioning);
                if ($pending === []) {
                    return ['status' => 'skipped', 'applied' => 0, 'backup_id' => null];
                }

                $output->writeln('  Pending migrations: '.count($pending));
                foreach ($pending as $name) {
                    $output->writeln('    '.$name);
                }

                $backup = null;
                if ($freshDatabase) {
                    $output->writeln('  Fresh database confirmed; no pre-migration backup is required.');
                } else {
                    $output->writeln('  Creating backup before applying migrations...');
                    $backup = $this->backups->create($tenant, $database);
                    $output->writeln('  Verified backup: '.$backup->id);
                }

                $exit = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations',
                    '--force' => true,
                ], $output);

                if ($exit !== 0 || $this->pendingMigrations() !== []) {
                    $backupMessage = $backup ? ' Backup: '.$backup->id.'.' : '';
                    throw new RuntimeException('Migration did not complete.'.$backupMessage.' Review the migration output before retrying.');
                }

                $version = count($this->migrator->getRepository()->getRan());
                $tenant->forceFill(['schema_version' => $version])->save();

                return ['status' => 'migrated', 'applied' => count($pending), 'backup_id' => $backup?->id, 'schema_version' => $version];
            });
        }, true, $source, $externalReference);
    }

    public function pendingMigrations(bool $allowInitialProvisioning = false): array
    {
        // Only root tenant migrations; never include database/migrations/control.
        $files = $this->migrator->getMigrationFiles(database_path('migrations'));
        if (! $this->migrator->repositoryExists()) {
            if ($allowInitialProvisioning) {
                return array_keys($files);
            }

            throw new RuntimeException('No migrations table found. Provision or inspect this tenant separately before running bulk migrations.');
        }

        return array_values(array_diff(array_keys($files), $this->migrator->getRepository()->getRan()));
    }
}
