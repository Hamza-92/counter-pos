<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class ControlPlaneTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.control.driver' => 'sqlite',
            'database.connections.control.database' => ':memory:',
            'database.connections.control.prefix' => '',
            'database.connections.control.foreign_key_constraints' => true,
            'tenancy.control_host' => 'admin.counterpos.pk',
            'tenancy.require_verified_domain' => true,
            'tenancy.require_subscription' => true,
            'tenancy.resolution_cache_store' => 'array',
        ]);

        Artisan::call('migrate', [
            '--database' => 'control',
            '--path' => 'database/migrations/control',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::store('array')->flush();
        parent::tearDown();
    }
}
