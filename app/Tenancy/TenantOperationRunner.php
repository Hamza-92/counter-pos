<?php

namespace App\Tenancy;

use App\Models\ControlPlane\ProvisioningRun;
use App\Models\ControlPlane\Tenant;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class TenantOperationRunner
{
    public function __construct(private readonly TenantDatabaseManager $database)
    {
    }

    public function run(string $tenantId, string $action, callable $operation, bool $migrationCredentials = false): mixed
    {
        if (! Str::isUuid($tenantId)) {
            throw new \InvalidArgumentException('Tenant must be supplied as a UUID.');
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $lock = Cache::store('database')->lock('tenant-operation:'.$tenantId, 1800);

        try {
            return $lock->block(5, function () use ($tenant, $action, $operation, $migrationCredentials) {
                $run = ProvisioningRun::query()->create([
                    'tenant_id' => $tenant->id,
                    'action' => $action,
                    'status' => 'running',
                    'idempotency_key' => (string) Str::uuid(),
                    'started_at' => now(),
                ]);

                try {
                    $database = $this->database->initializeForTenant($tenant->id, $migrationCredentials);
                    $result = $operation($tenant, $database);
                    $run->forceFill([
                        'status' => 'succeeded',
                        'result' => is_array($result) ? $result : ['result' => $result],
                        'finished_at' => now(),
                    ])->save();

                    return $result;
                } catch (Throwable $exception) {
                    $run->forceFill([
                        'status' => 'failed',
                        'result' => ['error' => 'Operation failed. Review the application log using the run ID.'],
                        'finished_at' => now(),
                    ])->save();
                    report($exception);
                    throw $exception;
                } finally {
                    $this->database->reset();
                }
            });
        } catch (LockTimeoutException $exception) {
            throw new \RuntimeException('Another operation is already running for this tenant.', 0, $exception);
        }
    }
}
