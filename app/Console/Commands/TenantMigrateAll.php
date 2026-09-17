<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\TenantMigrationService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

class TenantMigrateAll extends Command
{
    protected $signature = 'tenant:migrate-all';

    protected $description = 'Sequentially back up and migrate all registered tenants, skipping up-to-date databases';

    public function handle(TenantMigrationService $migrations): int
    {
        $started = microtime(true);
        try {
            // Snapshot the registry so new registrations cannot change this run's total.
            $tenants = Tenant::query()->orderBy('id')->get(['id', 'name', 'status']);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);
            $this->error('Cannot read the tenant registry. No tenants processed. Check the application log.');

            return self::FAILURE;
        }

        $total = $tenants->count();
        $this->info('Total tenants: '.$total.' (all statuses included)');
        if ($total === 0) {
            $this->info('No registered tenants to migrate.');

            return self::SUCCESS;
        }

        $counts = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];
        $rows = [];
        foreach ($tenants as $index => $tenant) {
            $tenantStarted = microtime(true);
            $name = OutputFormatter::escape($tenant->name);
            $this->newLine();
            $this->info(sprintf('[%d/%d] %s | %s | %s', $index + 1, $total, $name, $tenant->id, $tenant->status));
            $this->line('  Connecting and checking pending migrations...');

            try {
                $result = $migrations->run($tenant->id, $this->output);
                $status = $result['status'];
                $counts[$status]++;
                $detail = $status === 'skipped'
                    ? 'Already up to date; no backup or schema changes needed.'
                    : $result['applied'].' migration(s) applied; backup '.$result['backup_id'];
                $this->info('  '.strtoupper($status).': '.$detail);
            } catch (Throwable $exception) {
                $this->reportFailure($exception);
                $status = 'failed';
                $counts['failed']++;
                // Connection exceptions can contain credentials or SQL data.
                $detail = 'Check application log and superadmin provisioning runs for this tenant.';
                $this->error('  FAILED: '.$detail.' Continuing with next tenant.');
            }

            $seconds = number_format(microtime(true) - $tenantStarted, 2);
            $rows[] = [$tenant->id, $name, strtoupper($status), $seconds.'s', $detail];
            $this->line(sprintf('  Progress: %d/%d (%d%%) | migrated %d | skipped %d | failed %d | %ss',
                $index + 1, $total, (int) floor(($index + 1) * 100 / $total),
                $counts['migrated'], $counts['skipped'], $counts['failed'], $seconds));
        }

        $this->newLine();
        $this->table(['Tenant UUID', 'Name', 'Result', 'Time', 'Details'], $rows);
        $this->info(sprintf('Finished: total %d | migrated %d | skipped %d | failed %d | elapsed %.2fs',
            $total, $counts['migrated'], $counts['skipped'], $counts['failed'], microtime(true) - $started));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reportFailure(Throwable $exception): void
    {
        try {
            report($exception);
        } catch (Throwable) {
            $this->warn('  Unable to write the application log. Check logging permissions and disk space.');
        }
    }
}
