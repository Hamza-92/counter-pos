<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\ProvisioningRun;
use App\Models\ControlPlane\Tenant;
use Illuminate\Support\Str;

class CrmLinkageTest extends ControlPlaneTestCase
{
    public function test_it_links_a_tenant_and_its_operations_to_crm_records(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'CRM linked tenant',
            'slug' => 'crm-linked-tenant',
            'status' => 'provisioning',
            'crm_application_instance_id' => 42,
            'data_template_code' => 'grocery',
            'data_template_version' => 1,
        ]);

        $run = ProvisioningRun::query()->create([
            'tenant_id' => $tenant->id,
            'action' => 'migrate',
            'source' => 'crm',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
            'external_reference' => 'crm-operation-123',
        ]);

        $tenant = $tenant->fresh();

        $this->assertSame(42, $tenant->crm_application_instance_id);
        $this->assertSame('grocery', $tenant->data_template_code);
        $this->assertSame(1, $tenant->data_template_version);
        $this->assertTrue($tenant->provisioningRuns->contains($run));
        $this->assertSame('crm', $run->fresh()->source);
        $this->assertSame('crm-operation-123', $run->fresh()->external_reference);
    }
}