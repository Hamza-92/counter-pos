<?php

namespace App\Console\Commands;

use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantHealth extends Command
{
    protected $signature = 'tenant:health {--tenant= : Tenant UUID}';
    protected $description = 'Verify one registered tenant database and required runtime tables';

    public function handle(TenantOperationRunner $runner): int
    {
        try {
            $result = $runner->run((string) $this->option('tenant'), 'health', function ($tenant, $database) {
                $required = ['users', 'settings', 'sessions', 'cache', 'jobs', 'migrations', 'tenant_options'];
                $missing = array_values(array_filter($required, static fn ($table) => ! Schema::connection('tenant')->hasTable($table)));
                $requiredColumns = [
                    'settings' => ['timezone'],
                    'products' => ['warranty_period', 'has_guarantee', 'guarantee_period'],
                ];
                foreach ($requiredColumns as $table => $columns) {
                    if (! Schema::connection('tenant')->hasTable($table)) {
                        $missing[] = $table;
                        continue;
                    }
                    foreach ($columns as $column) {
                        if (! Schema::connection('tenant')->hasColumn($table, $column)) {
                            $missing[] = $table.'.'.$column;
                        }
                    }
                }
                $missing = array_values(array_unique($missing));
                $tables = DB::connection('tenant')->select('SHOW TABLES');
                $schemaVersion = Schema::connection('tenant')->hasTable('migrations')
                    ? DB::connection('tenant')->table('migrations')->count()
                    : 0;
                $tenant->forceFill([
                    'last_health_check_at' => now(),
                    'schema_version' => $schemaVersion,
                ])->save();

                return ['database' => $database->database_name, 'table_count' => count($tables), 'missing' => $missing];
            });
            $this->table(['Database', 'Tables', 'Missing'], [[$result['database'], $result['table_count'], implode(', ', $result['missing']) ?: 'none']]);

            return $result['missing'] === [] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
