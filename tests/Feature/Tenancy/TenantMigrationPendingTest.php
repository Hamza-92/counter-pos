<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\TenantMigrationService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TenantMigrationPendingTest extends TestCase
{
    public function test_it_uses_each_database_history_and_excludes_control_migrations(): void
    {
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $migrator = app(Migrator::class);
        $original = DB::getDefaultConnection();

        $migrator->usingConnection('tenant', function () use ($migrator) {
            $repository = $migrator->getRepository();
            $repository->createRepository();
            $service = app(TenantMigrationService::class);
            $pending = $service->pendingMigrations();
            $this->assertContains('2026_09_15_000001_add_online_store_price_to_products_and_variants', $pending);
            $this->assertNotContains('2026_08_03_000000_create_control_plane_tables', $pending);

            $repository->log($pending[0], 1);
            $this->assertNotContains($pending[0], $service->pendingMigrations());
            $this->assertCount(count($pending) - 1, $service->pendingMigrations());
            foreach (array_slice($pending, 1) as $migration) {
                $repository->log($migration, 1);
            }
            $this->assertSame([], $service->pendingMigrations());
        });

        $this->assertSame($original, DB::getDefaultConnection());
        // A separate database must not inherit the first tenant's migration history.
        DB::purge('tenant');
        $migrator->usingConnection('tenant', function () use ($migrator) {
            $migrator->getRepository()->createRepository();
            $this->assertNotEmpty(app(TenantMigrationService::class)->pendingMigrations());
        });
    }

    public function test_it_refuses_an_unprovisioned_database_without_creating_a_repository(): void
    {
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $migrator = app(Migrator::class);
        $original = DB::getDefaultConnection();
        try {
            $migrator->usingConnection('tenant', fn () => app(TenantMigrationService::class)->pendingMigrations());
            $this->fail('Expected unprovisioned database to be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No migrations table found', $exception->getMessage());
        }
        $this->assertFalse(DB::connection('tenant')->getSchemaBuilder()->hasTable('migrations'));
        $this->assertSame($original, DB::getDefaultConnection());
    }
}
