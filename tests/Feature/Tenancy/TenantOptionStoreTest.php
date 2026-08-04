<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\TenantAccessDecision;
use App\Tenancy\TenantContext;
use App\Tenancy\TenancyManager;
use App\Tenancy\TenantOptionStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantOptionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.enabled' => true,
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        Schema::create('tenant_options', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        app(TenancyManager::class)->enterTenant(new TenantContext(
            '019fcaca-c2a4-7155-a67a-43574f25c228',
            'Test Tenant',
            'tenant.example.test',
            'tenant.example.test',
            'active',
            TenantAccessDecision::allow(),
        ));
    }

    protected function tearDown(): void
    {
        app(TenancyManager::class)->reset();
        DB::purge('tenant');

        parent::tearDown();
    }

    public function test_it_encrypts_and_reads_customer_specific_options(): void
    {
        $store = app(TenantOptionStore::class);
        $store->putMany(['STRIPE_SECRET' => 'tenant-secret']);

        $this->assertSame('tenant-secret', $store->get('STRIPE_SECRET'));
        $this->assertNotSame(
            'tenant-secret',
            DB::table('tenant_options')->where('key', 'STRIPE_SECRET')->value('value'),
        );
    }
}
