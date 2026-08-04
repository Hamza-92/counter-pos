<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\Plan;
use App\Models\ControlPlane\Subscription;
use App\Models\ControlPlane\Tenant;
use App\Tenancy\HostNormalizer;
use App\Tenancy\TenantAccessEvaluator;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TenantResolverTest extends ControlPlaneTestCase
{
    public function test_it_resolves_only_an_exact_verified_active_subscribed_domain(): void
    {
        $tenant = $this->activeTenant('Acme', 'acme');
        Domain::create([
            'tenant_id' => $tenant->id,
            'host' => 'Shop.CounterPOS.pk',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $this->subscribe($tenant);

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
        $this->subscribe($tenant);

        $resolver = new TenantResolver(new HostNormalizer, new TenantAccessEvaluator);

        $this->assertNull($resolver->resolve('shop.counterpos.pk'));
    }

    public function test_an_active_tenant_without_a_subscription_is_denied(): void
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
        $this->assertFalse($context->access->allowed);
        $this->assertSame('expired', $context->access->state);
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

    private function subscribe(Tenant $tenant): void
    {
        $plan = Plan::create([
            'name' => 'Monthly',
            'billing_interval' => 'monthly',
            'price' => 1000,
            'currency' => 'PKR',
        ]);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addMonth(),
            'agreed_amount' => 1000,
            'currency' => 'PKR',
        ]);
    }
}
