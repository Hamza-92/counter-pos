<?php

namespace App\Console\Commands;

use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantSeedReference extends Command
{
    protected $signature = 'tenant:seed-reference {--tenant= : Tenant UUID}';
    protected $description = 'Run the fixed reference-data seeder for exactly one tenant';

    public function handle(TenantOperationRunner $runner): int
    {
        try {
            $runner->run((string) $this->option('tenant'), 'seed-reference', function () {
                if (DB::connection('tenant')->table('settings')->exists() || DB::connection('tenant')->table('roles')->exists()) {
                    throw new \RuntimeException('Reference tables are not empty; refusing a duplicate seed.');
                }
                $exit = Artisan::call('db:seed', ['--database' => 'tenant', '--class' => 'Database\\Seeders\\TenantReferenceSeeder', '--force' => true]);
                if ($exit !== 0) {
                    throw new \RuntimeException('Reference seeding failed.');
                }

                return ['seeded' => true];
            }, true);
            $this->info('Reference data seeded.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
