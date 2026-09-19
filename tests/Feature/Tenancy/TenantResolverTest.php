<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\Tenant;
use App\Tenancy\HostNormalizer;
use App\Tenancy\TenantAccessEvaluator;
use App\Tenancy\TenantResolver;
use InvalidArgumentException;

class TenantResolverTest extends ControlPlaneTestCase
{
    public function test_it_resolves_only_an_exact_verified_active_domain(): void
    {
        $tenant = $this->activeTenant('Acme', 'acme');
        Domain::create([
            'tenant_id' => $tenant->id,
            'host' => 'Shop.CounterPOS.pk',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $resolver = new TenantResolver(new HostNormalizer, new TenantAccessEvaluator);
        $context = $resolver->resolve('shop.counterpos.pk');

        $this->assertNotNull($context);
        $this->assertSame($tenant->id, $context->tenantId);
        $this->assertSame('shop.counterpos.pk', $context->host);
        $this->assertTrue($context->access->allowed);
        $this->assertNull($resolver->resolve('other.counterpos.pk'));
    }

    public function test_it_rejects_an_unverified_domain(): void
    {
        $tenant = $this->activeTenant('Acme', 'acme');
        Domain::create([
            'tenant_id' => $tenant->id,
            'host' => 'shop.counterpos.pk',
            'is_primary' => true,
        ]);
        $resolver = new TenantResolver(new HostNormalizer, new TenantAccessEvaluator);

        $this->assertNull($resolver->resolve('shop.counterpos.pk'));
    }

    public function test_an_active_tenant_does_not_require_a_local_subscription(): void
    {
        $tenant = $this->activeTenant('Acme', 'acme');
        Domain::create([
            'tenant_id' => $tenant->id,
            'host' => 'shop.counterpos.pk',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $context = (new TenantResolver(new HostNormalizer, new TenantAccessEvaluator))
            ->resolve('shop.counterpos.pk');

        $this->assertNotNull($context);
        $this->assertTrue($context->access->allowed);
    }

    public function test_the_control_host_cannot_be_assigned_to_a_tenant(): void
    {
        $tenant = $this->activeTenant('Acme', 'acme');
        $this->expectException(InvalidArgumentException::class);

        Domain::create([
            'tenant_id' => $tenant->id,
            'host' => 'admin.counterpos.pk',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
    }

    private function activeTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }
}
