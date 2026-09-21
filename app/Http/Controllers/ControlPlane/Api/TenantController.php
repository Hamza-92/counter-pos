<?php

namespace App\Http\Controllers\ControlPlane\Api;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\ProvisioningRun;
use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use App\Services\ControlPlane\AuditService;
use App\Tenancy\Exceptions\TenantDatabaseException;
use App\Tenancy\HostNormalizer;
use App\Tenancy\TenantAdministratorService;
use App\Tenancy\TenantDatabaseManager;
use App\Tenancy\TenantMigrationService;
use App\Tenancy\TenantOperationRunner;
use App\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class TenantController extends Controller
{
    public function register(Request $request, AuditService $audit): JsonResponse
    {
        $existing = Tenant::query()->where('crm_application_instance_id', $request->integer('crm_application_instance_id'))->first();
        $data = $request->validate([
            'crm_application_instance_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('control.tenants', 'slug')->ignore($existing?->id)],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'data_template_code' => ['nullable', 'alpha_dash', 'max:64'],
            'data_template_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $before = $existing?->only(['name', 'slug', 'contact_name', 'contact_email', 'contact_phone', 'data_template_code', 'data_template_version']);
        $tenant = Tenant::query()->updateOrCreate(
            ['crm_application_instance_id' => $data['crm_application_instance_id']],
            $data + ['status' => $existing?->status ?? 'provisioning'],
        );
        $audit->record($existing ? 'crm_api.tenant.updated' : 'crm_api.tenant.created', $tenant, $before, $tenant->only(array_keys($data)));

        return $this->tenantResponse($tenant, $existing ? 200 : 201);
    }

    public function showByCrmInstance(int $crmInstanceId): JsonResponse
    {
        $tenant = Tenant::query()->where('crm_application_instance_id', $crmInstanceId)->first();

        if (! $tenant) {
            return response()->json([
                'message' => 'No CounterPOS tenant is linked to this CRM application instance.',
                'code' => 'tenant_not_linked',
            ], 404);
        }

        return $this->tenantResponse($tenant);
    }

    public function saveDomain(Request $request, Tenant $tenant, AuditService $audit, TenantResolver $resolver, HostNormalizer $normalizer): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'verified' => ['required', 'boolean'],
        ]);
        $normalizedHost = $normalizer->normalize($data['host']);
        $domain = $tenant->primaryDomain()->first()
            ?? $tenant->domains()->where('normalized_host', $normalizedHost)->first();
        $before = $domain?->only(['normalized_host', 'is_primary', 'verified_at']);
        $oldHost = $domain?->normalized_host;

        $domain = DB::connection('control')->transaction(function () use ($tenant, $domain, $data): Domain {
            Domain::query()->where('tenant_id', $tenant->id)->update(['is_primary' => false]);
            $domain ??= new Domain(['tenant_id' => $tenant->id]);
            $domain->forceFill([
                'host' => $data['host'],
                'is_primary' => true,
                'verified_at' => $data['verified'] ? now() : null,
            ])->save();

            return $domain;
        });

        if ($oldHost) {
            $resolver->forget($oldHost);
        }
        $resolver->forget($domain->normalized_host);
        $audit->record('crm_api.domain.saved', $domain, $before, $domain->only(['normalized_host', 'is_primary', 'verified_at']));

        return $this->tenantResponse($tenant->fresh());
    }

    public function saveDatabase(Request $request, Tenant $tenant, AuditService $audit, TenantDatabaseManager $manager): JsonResponse
    {
        $existing = $tenant->databaseConfiguration;
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database_name' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:191'],
            'username' => ['required', 'string', 'max:191'],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'max:1000'],
            'migration_username' => ['nullable', 'string', 'max:191'],
            'migration_password' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($existing && empty($data['password'])) {
            unset($data['password']);
        }
        if ($existing && empty($data['migration_password'])) {
            unset($data['migration_password']);
        }

        $database = $existing ?: new TenantDatabase(['tenant_id' => $tenant->id, 'driver' => 'mysql']);
        $before = $existing?->only(['host', 'port', 'database_name', 'username', 'migration_username', 'credential_version']);
        $database->fill($data);
        if ($existing) {
            $database->credential_version++;
        }

        try {
            $manager->assertCredentialPolicy($database);
        } catch (TenantDatabaseException) {
            return response()->json([
                'message' => 'Database credentials do not satisfy the configured tenant database policy.',
                'code' => 'database_policy_rejected',
            ], 422);
        }

        $database->save();
        $audit->record('crm_api.tenant_database.saved', $database, $before, $database->only(['host', 'port', 'database_name', 'username', 'migration_username', 'credential_version']));

        return $this->tenantResponse($tenant->fresh());
    }

    public function testDatabase(Tenant $tenant, TenantDatabaseManager $manager, AuditService $audit): JsonResponse
    {
        $database = $tenant->databaseConfiguration;
        if (! $database) {
            return response()->json(['message' => 'No database is configured.', 'code' => 'database_not_configured'], 409);
        }

        try {
            $manager->initializeDatabase($database);
            $database->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_succeeded' => true,
                'last_connection_error' => null,
            ])->save();
            $audit->record('crm_api.tenant_database.test_succeeded', $database);

            return response()->json(['data' => [
                'connected' => true,
                'tested_at' => $database->last_connection_test_at->toISOString(),
            ]]);
        } catch (TenantDatabaseException) {
            $database->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_succeeded' => false,
                'last_connection_error' => 'Connection or database identity verification failed.',
            ])->save();
            $audit->record('crm_api.tenant_database.test_failed', $database);

            return response()->json([
                'message' => 'Database connection failed. Check the allowlist and credentials.',
                'code' => 'database_connection_failed',
                'data' => ['connected' => false, 'tested_at' => $database->last_connection_test_at->toISOString()],
            ], 422);
        } finally {
            $manager->reset();
        }
    }

    public function migrate(Request $request, Tenant $tenant, TenantMigrationService $migrations): JsonResponse
    {
        set_time_limit(900);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($idempotencyKey)) {
            return response()->json([
                'message' => 'A UUID Idempotency-Key header is required.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        $existing = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Idempotency key belongs to another tenant.', 'code' => 'idempotency_conflict'], 409);
            }

            return $this->operationResponse($existing);
        }

        try {
            $migrations->run($tenant->id, new BufferedOutput, 'crm', $idempotencyKey, true);
        } catch (Throwable) {
            $run = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();

            return $run
                ? $this->operationResponse($run, 422)
                : response()->json(['message' => 'Migration could not be started.', 'code' => 'migration_failed'], 422);
        }

        return $this->operationResponse(ProvisioningRun::query()->where('external_reference', $idempotencyKey)->firstOrFail());
    }

    public function seedTemplate(Request $request, Tenant $tenant, TenantOperationRunner $runner): JsonResponse
    {
        set_time_limit(900);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($idempotencyKey)) {
            return response()->json([
                'message' => 'A UUID Idempotency-Key header is required.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        $data = $request->validate([
            'template_code' => ['required', 'alpha_dash', 'max:64'],
            'template_version' => ['required', 'integer', 'min:1'],
        ]);
        if ($tenant->data_template_code && $data['template_code'] !== $tenant->data_template_code) {
            return response()->json([
                'message' => 'The requested template does not match the tenant registration.',
                'code' => 'template_mismatch',
            ], 422);
        }

        $existing = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Idempotency key belongs to another tenant.', 'code' => 'idempotency_conflict'], 409);
            }

            return $this->operationResponse($existing);
        }

        try {
            $runner->run($tenant->id, 'seed-template', function () use ($data): array {
                $seeders = [
                    'clients' => 'Database\\Seeders\\ClientSeeder',
                    'currencies' => 'Database\\Seeders\\CurrencySeeder',
                    'settings' => 'Database\\Seeders\\SettingSeeder',
                    'servers' => 'Database\\Seeders\\ServerSeeder',
                    'permissions' => 'Database\\Seeders\\PermissionsSeeder',
                    'roles' => 'Database\\Seeders\\RoleSeeder',
                    'permission_role' => 'Database\\Seeders\\PermissionRoleSeeder',
                    'warehouses' => 'Database\\Seeders\\Warehouse',
                    'store_settings' => 'Database\\Seeders\\StoreSettingSeeder',
                    'payment_methods' => 'Database\\Seeders\\PaymentMethodsSeeder',
                ];
                $seededTables = [];

                foreach ($seeders as $table => $seeder) {
                    if (DB::connection('tenant')->table($table)->exists()) {
                        continue;
                    }

                    $exit = Artisan::call('db:seed', [
                        '--database' => 'tenant',
                        '--class' => $seeder,
                        '--force' => true,
                    ]);
                    if ($exit !== 0) {
                        throw new \RuntimeException("Tenant reference data seeding failed for {$table}.");
                    }

                    $seededTables[] = $table;
                }

                return [
                    'status' => 'completed',
                    'template_code' => $data['template_code'],
                    'template_version' => $data['template_version'],
                    'seeded' => $seededTables !== [],
                    'seeded_tables' => $seededTables,
                ];
            }, true, 'crm', $idempotencyKey);
        } catch (Throwable) {
            $run = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();

            return $run
                ? $this->operationResponse($run, 422)
                : response()->json(['message' => 'Template seeding could not be started.', 'code' => 'template_seed_failed'], 422);
        }

        return $this->operationResponse(ProvisioningRun::query()->where('external_reference', $idempotencyKey)->firstOrFail());
    }

    public function configureAdministrator(Request $request, Tenant $tenant, TenantAdministratorService $administrators): JsonResponse
    {
        set_time_limit(120);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($idempotencyKey)) {
            return response()->json([
                'message' => 'A UUID Idempotency-Key header is required.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:192'],
            'password' => ['required', 'string', 'min:12', 'max:128'],
        ]);

        $existing = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Idempotency key belongs to another tenant.', 'code' => 'idempotency_conflict'], 409);
            }

            return $this->operationResponse($existing);
        }

        try {
            $administrators->configure($tenant->id, $data, 'crm', $idempotencyKey);
        } catch (Throwable) {
            $run = ProvisioningRun::query()->where('external_reference', $idempotencyKey)->first();

            return $run
                ? $this->operationResponse($run, 422)
                : response()->json(['message' => 'Administrator configuration could not be started.', 'code' => 'administrator_configuration_failed'], 422);
        }

        return $this->operationResponse(ProvisioningRun::query()->where('external_reference', $idempotencyKey)->firstOrFail());
    }

    public function changeStatus(Request $request, Tenant $tenant, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'archived'])],
            'reason' => ['nullable', 'required_if:status,suspended,archived', 'string', 'max:2000'],
            'version' => ['required', 'integer'],
        ]);

        if ((int) $data['version'] !== (int) $tenant->version) {
            return response()->json(['message' => 'Tenant version is stale. Refresh and retry.', 'code' => 'tenant_version_conflict'], 409);
        }

        if ($data['status'] === 'active') {
            $ready = $tenant->databaseConfiguration()->exists()
                && $tenant->domains()->where('is_primary', true)->whereNotNull('verified_at')->exists();
            if (! $ready) {
                return response()->json([
                    'message' => 'Activation requires a database and verified primary domain.',
                    'code' => 'tenant_not_ready',
                ], 422);
            }
        }

        $before = $tenant->only(['status', 'manual_suspension_reason', 'version']);
        $tenant->forceFill([
            'status' => $data['status'],
            'manual_suspension_reason' => $data['status'] === 'active' ? null : $data['reason'],
            'activated_at' => $data['status'] === 'active' ? now() : $tenant->activated_at,
            'suspended_at' => $data['status'] === 'suspended' ? now() : null,
            'version' => $tenant->version + 1,
        ])->save();
        $audit->record('crm_api.tenant.status_changed', $tenant, $before, $tenant->only(['status', 'manual_suspension_reason', 'version']));

        return $this->tenantResponse($tenant->fresh());
    }

    public function operation(ProvisioningRun $run): JsonResponse
    {
        return $this->operationResponse($run);
    }

    private function tenantResponse(Tenant $tenant, int $status = 200): JsonResponse
    {
        $tenant->load([
            'primaryDomain:id,tenant_id,host,verified_at',
            'databaseConfiguration:id,tenant_id,driver,credential_version,last_connection_test_at,last_connection_succeeded',
            'provisioningRuns' => fn ($query) => $query
                ->select(['id', 'tenant_id', 'action', 'source', 'status', 'external_reference', 'started_at', 'finished_at', 'created_at'])
                ->limit(10),
        ]);
        $database = $tenant->databaseConfiguration;
        $domain = $tenant->primaryDomain;

        return response()->json(['data' => [
            'id' => $tenant->id,
            'crm_application_instance_id' => $tenant->crm_application_instance_id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'version' => $tenant->version,
            'schema_version' => $tenant->schema_version,
            'data_template' => ['code' => $tenant->data_template_code, 'version' => $tenant->data_template_version],
            'domain' => $domain ? [
                'host' => $domain->host,
                'verified' => $domain->verified_at !== null,
                'verified_at' => $domain->verified_at?->toISOString(),
            ] : null,
            'database' => [
                'configured' => $database !== null,
                'driver' => $database?->driver,
                'credential_version' => $database?->credential_version,
                'last_tested_at' => $database?->last_connection_test_at?->toISOString(),
                'last_test_succeeded' => $database?->last_connection_succeeded,
            ],
            'operations' => $tenant->provisioningRuns->map(fn ($run) => $this->operationData($run))->values(),
            'last_health_check_at' => $tenant->last_health_check_at?->toISOString(),
            'updated_at' => $tenant->updated_at?->toISOString(),
        ]], $status);
    }

    private function operationResponse(ProvisioningRun $run, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->operationData($run->fresh())], $status);
    }

    private function operationData(ProvisioningRun $run): array
    {
        $result = collect($run->result ?? [])->only([
            'status',
            'applied',
            'schema_version',
            'template_code',
            'template_version',
            'seeded',
            'seeded_tables',
            'admin_email',
            'created',
        ])->all();

        return [
            'id' => $run->id,
            'tenant_id' => $run->tenant_id,
            'type' => $run->action,
            'source' => $run->source,
            'status' => $run->status,
            'external_reference' => $run->external_reference,
            'result' => $result === [] ? null : $result,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
        ];
    }
}
