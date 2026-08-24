<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\Plan;
use App\Models\ControlPlane\Subscription;
use App\Models\ControlPlane\SuperAdmin;
use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use App\Models\User;
use App\Services\ControlPlane\TotpService;
use App\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenancyLocalBootstrap extends Command
{
    protected $signature = 'tenancy:local-bootstrap {--confirm-local : Create the fixed local test databases and records}';
    protected $description = 'Create a non-destructive local control + two-tenant test installation';

    public function handle(TotpService $totp, TenantDatabaseManager $manager): int
    {
        if (! $this->option('confirm-local')) {
            $this->error('Refusing to continue without --confirm-local.');

            return self::FAILURE;
        }

        $host = strtolower((string) config('database.connections.mysql.host'));
        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->error('This command only runs against a loopback MySQL server.');

            return self::FAILURE;
        }

        $controlName = 'counterpos_control_local';
        $tenantAName = (string) config('database.connections.mysql.database');
        $tenantBName = 'counterpos_tenant_local_b';
        if ($tenantAName === '' || in_array($tenantAName, [$controlName, $tenantBName], true)) {
            $this->error('The current standalone database is not a safe Tenant A source.');

            return self::FAILURE;
        }

        foreach ([$controlName, $tenantBName] as $databaseName) {
            DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        $mysql = config('database.connections.mysql');
        Config::set('database.connections.control', array_merge(config('database.connections.control'), $mysql, [
            'driver' => 'mysql', 'database' => $controlName, 'strict' => true,
        ]));
        Config::set('tenancy.database.allowed_hosts', ['127.0.0.1', 'localhost']);
        Config::set('tenancy.database.name_prefix', 'counter');
        DB::purge('control');

        $exit = Artisan::call('migrate', [
            '--database' => 'control', '--path' => 'database/migrations/control', '--force' => true,
        ]);
        if ($exit !== 0) {
            $this->error('Control migration failed.');

            return self::FAILURE;
        }

        $plan = Plan::query()->firstOrCreate(['name' => 'Local Test'], [
            'billing_interval' => 'monthly', 'price' => 1000, 'currency' => 'PKR', 'is_active' => true,
            'features' => ['Local isolation testing'],
        ]);

        $tenantA = $this->tenant('Local Existing Business', 'local-a', 'shop-a.127.0.0.1.nip.io', $tenantAName, $mysql, $plan);
        $tenantB = $this->tenant('Local New Business', 'local-b', 'shop-b.127.0.0.1.nip.io', $tenantBName, $mysql, $plan);

        $manager->initializeForTenant($tenantA->id, true);
        try {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/2026_08_03_100000_create_tenant_runtime_infrastructure_tables.php',
                '--force' => true,
            ]);
            $tenantA->forceFill(['schema_version' => DB::connection('tenant')->table('migrations')->count()])->save();
        } finally {
            $manager->reset();
        }

        $tenantPassword = $this->randomPassword();
        $manager->initializeForTenant($tenantB->id, true);
        try {
            Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);
            $owner = User::query()->where('email', 'owner@local.test')->first();
            if (! $owner) {
                if (! DB::connection('tenant')->table('settings')->exists()) {
                    Artisan::call('db:seed', ['--database' => 'tenant', '--class' => 'Database\\Seeders\\TenantReferenceSeeder', '--force' => true]);
                }
                $owner = User::query()->create([
                    'firstname' => 'Local', 'lastname' => 'Owner', 'username' => 'Local Owner',
                    'email' => 'owner@local.test', 'password' => Hash::make($tenantPassword),
                    'phone' => '', 'avatar' => 'no_avatar.png', 'role_id' => 1, 'statut' => 1,
                    'is_all_warehouses' => 1, 'record_view' => 1,
                ]);
                DB::connection('tenant')->table('role_user')->insert(['user_id' => $owner->id, 'role_id' => 1]);
            } else {
                $owner->forceFill(['password' => Hash::make($tenantPassword), 'statut' => 1])->save();
            }
            $tenantB->forceFill(['schema_version' => DB::connection('tenant')->table('migrations')->count()])->save();
        } finally {
            $manager->reset();
        }

        $adminPassword = $this->randomPassword();
        $secret = $totp->generateSecret();
        $recovery = $totp->generateRecoveryCodes();
        $admin = SuperAdmin::query()->firstOrNew(['email' => 'admin@counterpos.local']);
        $admin->fill(['name' => 'Local Superadmin', 'password' => Hash::make($adminPassword), 'is_active' => true]);
        $admin->totp_secret = $secret;
        $admin->recovery_code_hashes = array_map(static fn ($code) => Hash::make($code), $recovery);
        $admin->save();

        $this->newLine();
        $this->warn('Local credentials (save now; rerunning rotates them):');
        $this->line('Control URL: http://admin.127.0.0.1.nip.io:8000');
        $this->line('Control email: admin@counterpos.local');
        $this->line('Control password: '.$adminPassword);
        $this->line('TOTP secret: '.$secret);
        $this->line('Recovery code: '.$recovery[0]);
        $this->line('Tenant A URL: http://shop-a.127.0.0.1.nip.io:8000 (existing application data)');
        $this->line('Tenant B URL: http://shop-b.127.0.0.1.nip.io:8000');
        $this->line('Tenant B email: owner@local.test');
        $this->line('Tenant B password: '.$tenantPassword);

        return self::SUCCESS;
    }

    private function tenant(string $name, string $slug, string $host, string $databaseName, array $mysql, Plan $plan): Tenant
    {
        $tenant = Tenant::query()->firstOrCreate(['slug' => $slug], [
            'name' => $name, 'status' => 'active', 'activated_at' => now(),
        ]);
        $tenant->forceFill(['status' => 'active', 'activated_at' => $tenant->activated_at ?: now()])->save();

        Domain::query()->updateOrCreate(['normalized_host' => $host], [
            'tenant_id' => $tenant->id, 'host' => $host, 'is_primary' => true, 'verified_at' => now(),
        ]);
        TenantDatabase::query()->updateOrCreate(['tenant_id' => $tenant->id], [
            'driver' => 'mysql', 'host' => $mysql['host'], 'port' => $mysql['port'],
            'database_name' => $databaseName, 'username' => $mysql['username'], 'password' => $mysql['password'],
            'migration_username' => $mysql['username'], 'migration_password' => $mysql['password'],
        ]);
        Subscription::query()->firstOrCreate([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
        ], [
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
            'agreed_amount' => $plan->price, 'currency' => $plan->currency,
        ]);

        return $tenant;
    }

    private function randomPassword(): string
    {
        return 'L!'.Str::random(20).'9z';
    }
}
