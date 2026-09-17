<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\TenantMigrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class TenantMigrateAllTest extends ControlPlaneTestCase
{
    public function test_it_reports_no_tenants_without_attempting_migration(): void
    {
        $service = Mockery::mock(TenantMigrationService::class);
        $service->shouldNotReceive('run');
        $this->app->instance(TenantMigrationService::class, $service);

        $this->assertSame(0, Artisan::call('tenant:migrate-all'));
        $this->assertStringContainsString('Total tenants: 0', Artisan::output());
    }

    public function test_it_continues_after_failure_and_reports_all_statuses_in_order(): void
    {
        // A full disk or unwritable log must not abort the remaining tenants.
        Log::shouldReceive('error')->once()->andThrow(new RuntimeException('Log unavailable'));
        foreach (['active', 'suspended', 'archived', 'provisioning'] as $index => $status) {
            Tenant::query()->create(['name' => 'Tenant '.$index, 'slug' => 'bulk-'.$index, 'status' => $status]);
        }
        $tenants = Tenant::query()->orderBy('id')->get();
        $service = Mockery::mock(TenantMigrationService::class);
        $service->shouldReceive('run')->once()->ordered()
            ->with($tenants[0]->id, Mockery::type(OutputInterface::class))
            ->andThrow(new RuntimeException('Sensitive connection detail'));
        $service->shouldReceive('run')->once()->ordered()
            ->with($tenants[1]->id, Mockery::type(OutputInterface::class))
            ->andReturn(['status' => 'skipped', 'applied' => 0, 'backup_id' => null]);
        foreach ([2, 3] as $index) {
            $service->shouldReceive('run')->once()->ordered()
                ->with($tenants[$index]->id, Mockery::type(OutputInterface::class))
                ->andReturn(['status' => 'migrated', 'applied' => 1, 'backup_id' => 'backup-'.$index]);
        }
        $this->app->instance(TenantMigrationService::class, $service);

        $this->assertSame(1, Artisan::call('tenant:migrate-all'));
        $output = Artisan::output();
        $this->assertStringContainsString('Total tenants: 4', $output);
        $this->assertStringContainsString('Progress: 4/4 (100%)', $output);
        $this->assertStringContainsString('Finished: total 4 | migrated 2 | skipped 1 | failed 1', $output);
        $this->assertStringNotContainsString('Sensitive connection detail', $output);
        $this->assertStringContainsString('Unable to write the application log', $output);
        foreach ($tenants as $tenant) {
            $this->assertStringContainsString($tenant->id, $output);
        }
    }

    public function test_up_to_date_tenants_return_success(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Up to date', 'slug' => 'current', 'status' => 'active']);
        $service = Mockery::mock(TenantMigrationService::class);
        $service->shouldReceive('run')->once()->with($tenant->id, Mockery::type(OutputInterface::class))
            ->andReturn(['status' => 'skipped', 'applied' => 0, 'backup_id' => null]);
        $this->app->instance(TenantMigrationService::class, $service);

        $this->assertSame(0, Artisan::call('tenant:migrate-all'));
        $this->assertStringContainsString('Finished: total 1 | migrated 0 | skipped 1 | failed 0', Artisan::output());
    }
}
