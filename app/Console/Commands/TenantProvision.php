<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Tenancy\MySqlBackupService;
use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TenantProvision extends Command
{
    protected $signature = 'tenant:provision {--tenant= : Tenant UUID} {--admin-email=} {--admin-name=Owner}';
    protected $description = 'Provision one empty registered tenant database without destructive resets';

    public function handle(TenantOperationRunner $runner, MySqlBackupService $backups): int
    {
        $email = strtolower(trim((string) ($this->option('admin-email') ?: $this->ask('Tenant owner email'))));
        $password = (string) $this->secret('Tenant owner password (minimum 12 characters)');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $this->error('A valid email and a password of at least 12 characters are required.');

            return self::FAILURE;
        }

        try {
            $result = $runner->run((string) $this->option('tenant'), 'provision', function ($tenant, $database) use ($email, $password, $backups) {
                $tablesBefore = DB::connection('tenant')->select('SHOW TABLES');
                $backupId = null;
                if ($tablesBefore !== []) {
                    $backupId = $backups->create($tenant, $database)->id;
                }

                $exit = Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);
                if ($exit !== 0) {
                    throw new \RuntimeException('Tenant schema migration failed.');
                }

                if (! Schema::connection('tenant')->hasTable('users') || ! Schema::connection('tenant')->hasTable('roles')) {
                    throw new \RuntimeException('Required tenant identity tables were not created.');
                }

                if (DB::connection('tenant')->table('users')->count() === 0) {
                    $hasReferenceData = DB::connection('tenant')->table('settings')->exists()
                        || DB::connection('tenant')->table('roles')->exists();
                    if (! $hasReferenceData) {
                        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => 'Database\\Seeders\\TenantReferenceSeeder', '--force' => true]);
                    }
                    if (! DB::connection('tenant')->table('roles')->where('id', 1)->exists()) {
                        throw new \RuntimeException('The database has partial reference data and no owner role; repair it before provisioning.');
                    }
                    $name = trim((string) $this->option('admin-name')) ?: 'Owner';
                    $user = User::query()->create([
                        'firstname' => $name,
                        'lastname' => '',
                        'username' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'phone' => '',
                        'avatar' => 'no_avatar.png',
                        'role_id' => 1,
                        'statut' => 1,
                        'is_all_warehouses' => 1,
                        'record_view' => 1,
                    ]);
                    DB::connection('tenant')->table('role_user')->insert(['user_id' => $user->id, 'role_id' => 1]);
                }

                $version = (int) DB::connection('tenant')->table('migrations')->count();
                $tenant->forceFill(['schema_version' => $version])->save();

                return ['schema_version' => $version, 'preexisting_backup_id' => $backupId];
            }, true);
            $this->info('Tenant provisioned at schema '.$result['schema_version'].'. It remains inactive until explicitly activated.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
