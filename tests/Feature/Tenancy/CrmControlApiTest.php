<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\ProvisioningRun;
use App\Models\ControlPlane\Tenant;
use App\Tenancy\TenantAdministratorService;
use App\Tenancy\TenantMigrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CrmControlApiTest extends TestCase
{
    private const API_KEY = 'crm-test';

    private const API_SECRET = 'test-secret-with-enough-entropy';

    public function createApplication()
    {
        foreach ([
            'TENANCY_ENABLED' => 'true',
            'CONTROL_PLANE_HOST' => 'admin.counterpos.pk',
            'CONTROL_DB_DRIVER' => 'sqlite',
            'CONTROL_DB_DATABASE' => ':memory:',
            'CRM_API_KEY' => self::API_KEY,
            'CRM_API_SECRET' => self::API_SECRET,
            'CRM_API_CLOCK_SKEW_SECONDS' => '300',
        ] as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = parent::createApplication();
        $app->make(Kernel::class)->call('migrate', [
            '--database' => 'control',
            '--path' => 'database/migrations/control',
            '--force' => true,
        ]);

        return $app;
    }

    public function test_health_requires_a_valid_signature(): void
    {
        $this->getJson('https://admin.counterpos.pk/api/control/v1/health')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'crm_auth_invalid');
    }

    public function test_signed_health_request_succeeds(): void
    {
        $url = 'https://admin.counterpos.pk/api/control/v1/health';

        $this->withHeaders($this->signedHeaders('GET', $url))
            ->get($url, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.service', 'counterpos-control')
            ->assertJsonPath('data.api_version', 'v1');
    }

    public function test_expired_and_replayed_requests_are_rejected(): void
    {
        $url = 'https://admin.counterpos.pk/api/control/v1/health';
        $expired = $this->signedHeaders('GET', $url, '', (string) (time() - 1000));
        $this->withHeaders($expired)->get($url, ['Accept' => 'application/json'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'crm_timestamp_expired');

        $headers = $this->signedHeaders('GET', $url);
        $this->withHeaders($headers)->get($url, ['Accept' => 'application/json'])->assertOk();
        $this->withHeaders($headers)->get($url, ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'crm_request_replayed');
    }

    public function test_tenant_lookup_returns_operational_status_without_credentials(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'CRM Tenant',
            'slug' => 'crm-tenant',
            'status' => 'active',
            'crm_application_instance_id' => 42,
            'schema_version' => 12,
            'data_template_code' => 'grocery',
            'data_template_version' => 1,
        ]);
        $tenant->domains()->create([
            'host' => 'crm-tenant.example.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $tenant->databaseConfiguration()->create([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database_name' => 'private_database_name',
            'username' => 'private_database_user',
            'password' => 'private-database-password',
            'last_connection_test_at' => now(),
            'last_connection_succeeded' => true,
        ]);

        $url = 'https://admin.counterpos.pk/api/control/v1/tenants/by-crm-instance/42';
        $response = $this->withHeaders($this->signedHeaders('GET', $url))->get($url, ['Accept' => 'application/json'])->assertOk();
        $database = $response->json('data.database');

        $this->assertSame($tenant->id, $response->json('data.id'));
        $this->assertSame('crm-tenant.example.com', $response->json('data.domain.host'));
        $this->assertTrue($database['configured']);
        $this->assertArrayNotHasKey('host', $database);
        $this->assertArrayNotHasKey('database_name', $database);
        $this->assertArrayNotHasKey('username', $database);
        $this->assertArrayNotHasKey('password', $database);
    }

    public function test_control_api_is_not_available_on_tenant_hosts(): void
    {
        $url = 'https://customer.example.com/api/control/v1/health';

        $this->withHeaders($this->signedHeaders('GET', $url))
            ->get($url, ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_crm_can_idempotently_register_and_configure_a_tenant_without_exposing_credentials(): void
    {
        $registerUrl = 'https://admin.counterpos.pk/api/control/v1/tenants';
        $register = [
            'crm_application_instance_id' => 91,
            'name' => 'Provisioned from CRM',
            'slug' => 'provisioned-from-crm',
            'contact_email' => 'owner@example.com',
            'data_template_code' => 'grocery',
            'data_template_version' => 1,
        ];
        $registerKey = (string) Str::uuid();
        $first = $this->json('POST', $registerUrl, $register, $this->signedJsonHeaders('POST', $registerUrl, $register, $registerKey))
            ->assertCreated()
            ->assertJsonPath('data.crm_application_instance_id', 91);
        $tenantId = $first->json('data.id');

        $this->json('POST', $registerUrl, $register, $this->signedJsonHeaders('POST', $registerUrl, $register, $registerKey))
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');
        $this->assertSame(1, Tenant::query()->where('crm_application_instance_id', 91)->count());

        $domainUrl = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenantId}/domain";
        $domain = ['host' => 'provisioned.example.com', 'verified' => true];
        $this->json('PUT', $domainUrl, $domain, $this->signedJsonHeaders('PUT', $domainUrl, $domain, (string) Str::uuid()))
            ->assertOk()
            ->assertJsonPath('data.domain.host', 'provisioned.example.com')
            ->assertJsonPath('data.domain.verified', true);

        $databaseUrl = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenantId}/database";
        $database = [
            'host' => 'localhost',
            'port' => 3306,
            'database_name' => 'customer_provisioned',
            'username' => 'tenant_user',
            'password' => 'DatabasePassword123',
        ];
        $databaseResponse = $this->json('PUT', $databaseUrl, $database, $this->signedJsonHeaders('PUT', $databaseUrl, $database, (string) Str::uuid()))
            ->assertOk()
            ->assertJsonPath('data.database.configured', true);
        $this->assertStringNotContainsString('DatabasePassword123', $databaseResponse->getContent());
        $this->assertNotSame('DatabasePassword123', DB::connection('control')->table('tenant_databases')->where('tenant_id', $tenantId)->value('password'));

        $statusUrl = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenantId}/status";
        $status = ['status' => 'active', 'version' => $databaseResponse->json('data.version'), 'reason' => null];
        $this->json('PUT', $statusUrl, $status, $this->signedJsonHeaders('PUT', $statusUrl, $status, (string) Str::uuid()))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_domain_configuration_promotes_a_matching_non_primary_domain(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Domain Retry Tenant',
            'slug' => 'domain-retry-tenant',
            'status' => 'provisioning',
            'crm_application_instance_id' => 92,
        ]);
        $domain = Domain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'retry.example.com',
            'is_primary' => false,
            'verified_at' => null,
        ]);
        $url = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenant->id}/domain";
        $payload = ['host' => 'retry.example.com', 'verified' => true];

        $this->json('PUT', $url, $payload, $this->signedJsonHeaders('PUT', $url, $payload, (string) Str::uuid()))
            ->assertOk()
            ->assertJsonPath('data.domain.host', 'retry.example.com')
            ->assertJsonPath('data.domain.verified', true);

        $this->assertTrue($domain->fresh()->is_primary);
        $this->assertNotNull($domain->fresh()->verified_at);
        $this->assertSame(1, $tenant->domains()->count());
    }

    public function test_reusing_an_idempotency_key_for_different_content_is_rejected(): void
    {
        $url = 'https://admin.counterpos.pk/api/control/v1/tenants';
        $key = (string) Str::uuid();
        $first = ['crm_application_instance_id' => 101, 'name' => 'First', 'slug' => 'first'];
        $second = ['crm_application_instance_id' => 102, 'name' => 'Second', 'slug' => 'second'];

        $this->json('POST', $url, $first, $this->signedJsonHeaders('POST', $url, $first, $key))->assertCreated();
        $this->json('POST', $url, $second, $this->signedJsonHeaders('POST', $url, $second, $key))
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_conflict');
    }

    public function test_crm_can_configure_an_administrator_without_exposing_the_password(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Administrator Tenant',
            'slug' => 'administrator-tenant',
            'status' => 'provisioning',
            'crm_application_instance_id' => 110,
        ]);
        $key = (string) Str::uuid();
        $administrators = \Mockery::mock(TenantAdministratorService::class);
        $administrators->shouldReceive('configure')->once()
            ->with($tenant->id, \Mockery::on(fn (array $data) => $data['email'] === 'owner@example.com'
                && $data['password'] === 'SecureTenantPassword123!'), 'crm', $key)
            ->andReturnUsing(function (string $tenantId, array $data, string $source, string $externalReference): array {
                ProvisioningRun::query()->create([
                    'tenant_id' => $tenantId,
                    'action' => 'configure-administrator',
                    'source' => $source,
                    'status' => 'succeeded',
                    'idempotency_key' => $externalReference,
                    'external_reference' => $externalReference,
                    'result' => ['status' => 'configured', 'admin_email' => $data['email'], 'created' => true],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                return ['status' => 'configured', 'admin_email' => $data['email'], 'created' => true];
            });
        $this->app->instance(TenantAdministratorService::class, $administrators);

        $url = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenant->id}/administrator";
        $payload = [
            'name' => 'Store Owner',
            'email' => 'owner@example.com',
            'password' => 'SecureTenantPassword123!',
        ];
        $response = $this->json('PUT', $url, $payload, $this->signedJsonHeaders('PUT', $url, $payload, $key))
            ->assertOk()
            ->assertJsonPath('data.result.admin_email', 'owner@example.com')
            ->assertJsonPath('data.result.created', true);

        $this->assertStringNotContainsString('SecureTenantPassword123!', $response->getContent());
    }

    public function test_migration_requests_are_tracked_and_idempotent(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Migration Tenant',
            'slug' => 'migration-tenant',
            'status' => 'active',
            'crm_application_instance_id' => 111,
        ]);
        $key = (string) Str::uuid();
        $migrations = \Mockery::mock(TenantMigrationService::class);
        $migrations->shouldReceive('run')->once()
            ->with($tenant->id, \Mockery::type(BufferedOutput::class), 'crm', $key, true)
            ->andReturnUsing(function (string $tenantId, $output, string $source, string $externalReference): array {
                ProvisioningRun::query()->create([
                    'tenant_id' => $tenantId,
                    'action' => 'migrate',
                    'source' => $source,
                    'status' => 'succeeded',
                    'idempotency_key' => $externalReference,
                    'external_reference' => $externalReference,
                    'result' => ['status' => 'skipped', 'applied' => 0, 'schema_version' => 12],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                return ['status' => 'skipped', 'applied' => 0, 'schema_version' => 12];
            });
        $this->app->instance(TenantMigrationService::class, $migrations);

        $url = "https://admin.counterpos.pk/api/control/v1/tenants/{$tenant->id}/migrations";
        $first = $this->json('POST', $url, [], $this->signedJsonHeaders('POST', $url, [], $key))
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.result.status', 'skipped');

        $this->json('POST', $url, [], $this->signedJsonHeaders('POST', $url, [], $key))
            ->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));
    }

    private function signedJsonHeaders(string $method, string $url, array $payload, string $idempotencyKey): array
    {
        return $this->signedHeaders($method, $url, json_encode($payload)) + ['Idempotency-Key' => $idempotencyKey];
    }

    private function signedHeaders(string $method, string $url, string $body = '', ?string $timestamp = null, ?string $nonce = null): array
    {
        $timestamp ??= (string) time();
        $nonce ??= (string) Str::uuid();
        $target = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }
        $canonical = implode("\n", [$timestamp, $nonce, strtoupper($method), $target, hash('sha256', $body)]);

        return [
            'X-CRM-Key' => self::API_KEY,
            'X-CRM-Timestamp' => $timestamp,
            'X-CRM-Nonce' => $nonce,
            'X-CRM-Signature' => hash_hmac('sha256', $canonical, self::API_SECRET),
        ];
    }
}
