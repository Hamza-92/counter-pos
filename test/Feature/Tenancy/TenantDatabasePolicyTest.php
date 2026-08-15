<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\TenantDatabase;
use App\Tenancy\Exceptions\TenantDatabaseException;
use App\Tenancy\TenantDatabaseManager;

class TenantDatabasePolicyTest extends ControlPlaneTestCase
{
    public function test_it_accepts_an_allowlisted_one_tenant_database(): void
    {
        config([
            'tenancy.database.allowed_drivers' => ['mysql'],
            'tenancy.database.allowed_hosts' => ['mysql.host.test'],
            'tenancy.database.name_prefix' => 'account_counter_',
            'database.connections.control.database' => 'account_control',
        ]);

        $database = new TenantDatabase([
            'driver' => 'mysql',
            'host' => 'mysql.host.test',
            'database_name' => 'account_counter_acme',
        ]);

        app(TenantDatabaseManager::class)->assertCredentialPolicy($database);
        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_an_unapproved_database_host(): void
    {
        config([
            'tenancy.database.allowed_drivers' => ['mysql'],
            'tenancy.database.allowed_hosts' => ['mysql.host.test'],
            'tenancy.database.name_prefix' => 'account_counter_',
        ]);

        $this->expectException(TenantDatabaseException::class);

        app(TenantDatabaseManager::class)->assertCredentialPolicy(new TenantDatabase([
            'driver' => 'mysql',
            'host' => 'attacker.internal',
            'database_name' => 'account_counter_acme',
        ]));
    }

    public function test_it_rejects_the_control_database_and_wrong_namespace(): void
    {
        config([
            'tenancy.database.allowed_drivers' => ['mysql'],
            'tenancy.database.allowed_hosts' => ['mysql.host.test'],
            'tenancy.database.name_prefix' => 'account_',
            'database.connections.control.database' => 'account_control',
        ]);

        $this->expectException(TenantDatabaseException::class);

        app(TenantDatabaseManager::class)->assertCredentialPolicy(new TenantDatabase([
            'driver' => 'mysql',
            'host' => 'mysql.host.test',
            'database_name' => 'account_control',
        ]));
    }
}
