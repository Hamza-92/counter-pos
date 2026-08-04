<?php

namespace App\Tenancy;

use App\Models\ControlPlane\TenantDatabase;
use App\Tenancy\Exceptions\TenantDatabaseException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class TenantDatabaseManager
{
    private ?string $previousDefaultConnection = null;

    public function initialize(TenantContext $context): void
    {
        $database = TenantDatabase::query()->where('tenant_id', $context->tenantId)->first();

        if ($database === null) {
            throw new TenantDatabaseException('No tenant database is configured.');
        }

        $this->assertCredentialPolicy($database);
        $this->previousDefaultConnection = DB::getDefaultConnection();

        $template = (array) config('database.connections.tenant', []);
        Config::set('database.connections.tenant', array_merge($template, [
            'driver' => $database->driver,
            'host' => $database->host,
            'port' => (string) $database->port,
            'database' => $database->database_name,
            'username' => $database->username,
            'password' => $database->password,
        ]));

        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        try {
            $selectedDatabase = DB::connection('tenant')->selectOne('SELECT DATABASE() AS database_name');
            if (($selectedDatabase->database_name ?? null) !== $database->database_name) {
                throw new TenantDatabaseException('The selected database does not match the tenant registry.');
            }
        } catch (TenantDatabaseException $exception) {
            $this->reset();
            throw $exception;
        } catch (\Throwable $exception) {
            $this->reset();
            throw new TenantDatabaseException('The tenant database connection could not be established.', 0, $exception);
        }
    }

    public function useControlConnection(): void
    {
        $this->previousDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('control');
    }

    public function reset(): void
    {
        DB::purge('tenant');
        if ($this->previousDefaultConnection !== null) {
            DB::setDefaultConnection($this->previousDefaultConnection);
        }
        $this->previousDefaultConnection = null;
    }

    public function assertCredentialPolicy(TenantDatabase $database): void
    {
        $allowedDrivers = (array) config('tenancy.database.allowed_drivers', ['mysql']);
        $allowedHosts = array_map('strtolower', (array) config('tenancy.database.allowed_hosts', []));
        $prefix = (string) config('tenancy.database.name_prefix', '');

        if (! in_array($database->driver, $allowedDrivers, true)) {
            throw new TenantDatabaseException('The tenant database driver is not allowed.');
        }

        if (! in_array(strtolower($database->host), $allowedHosts, true)) {
            throw new TenantDatabaseException('The tenant database host is not allowed.');
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database->database_name)) {
            throw new TenantDatabaseException('The tenant database name is invalid.');
        }

        if ($prefix !== '' && ! str_starts_with($database->database_name, $prefix)) {
            throw new TenantDatabaseException('The tenant database name is outside the allowed namespace.');
        }

        $controlDatabase = (string) config('database.connections.control.database');
        if ($controlDatabase !== '' && hash_equals($controlDatabase, $database->database_name)) {
            throw new TenantDatabaseException('The control database cannot be used as a tenant database.');
        }
    }
}
