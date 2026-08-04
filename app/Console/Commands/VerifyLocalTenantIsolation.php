<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifyLocalTenantIsolation extends Command
{
    protected $signature = 'tenancy:verify-local-isolation {--confirm-local}';
    protected $description = 'Run a write/read isolation proof against the two fixed local tenants';

    public function handle(TenantDatabaseManager $manager): int
    {
        if (! $this->option('confirm-local') || config('database.connections.control.database') !== 'counterpos_control_local') {
            $this->error('This proof only runs against the fixed local control database with --confirm-local.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()->whereIn('slug', ['local-a', 'local-b'])->get()->keyBy('slug');
        if ($tenants->count() !== 2) {
            $this->error('Both local test tenants must exist.');

            return self::FAILURE;
        }

        $key = 'isolation-proof:'.Str::uuid();
        $observed = [];
        try {
            foreach (['local-a', 'local-b'] as $slug) {
                $manager->initializeForTenant($tenants[$slug]->id);
                DB::connection('tenant')->table('cache')->insert([
                    'key' => $key,
                    'value' => $slug,
                    'expiration' => time() + 300,
                ]);
                $observed[$slug] = DB::connection('tenant')->table('cache')->where('key', $key)->value('value');
                $manager->reset();
            }

            foreach (['local-a', 'local-b'] as $slug) {
                $manager->initializeForTenant($tenants[$slug]->id);
                $value = DB::connection('tenant')->table('cache')->where('key', $key)->value('value');
                if (! hash_equals($slug, (string) $value)) {
                    throw new \RuntimeException('Cross-tenant isolation proof failed for '.$slug.'.');
                }
                DB::connection('tenant')->table('cache')->where('key', $key)->delete();
                $manager->reset();
            }
        } catch (\Throwable $exception) {
            $manager->reset();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tenant', 'Isolated value'], [['local-a', $observed['local-a']], ['local-b', $observed['local-b']]]);
        $this->info('Isolation proof passed; temporary rows were removed.');

        return self::SUCCESS;
    }
}
