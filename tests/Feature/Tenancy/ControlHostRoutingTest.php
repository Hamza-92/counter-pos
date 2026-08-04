<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\SuperAdmin;
use App\Models\ControlPlane\Tenant;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ControlHostRoutingTest extends TestCase
{
    public function createApplication()
    {
        $_ENV['TENANCY_ENABLED'] = 'true';
        $_SERVER['TENANCY_ENABLED'] = 'true';
        $_ENV['CONTROL_PLANE_HOST'] = 'admin.counterpos.pk';
        $_SERVER['CONTROL_PLANE_HOST'] = 'admin.counterpos.pk';
        $_ENV['CONTROL_DB_DRIVER'] = 'sqlite';
        $_SERVER['CONTROL_DB_DRIVER'] = 'sqlite';
        $_ENV['CONTROL_DB_DATABASE'] = ':memory:';
        $_SERVER['CONTROL_DB_DATABASE'] = ':memory:';

        $app = parent::createApplication();
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->call('migrate', [
            '--database' => 'control',
            '--path' => 'database/migrations/control',
            '--force' => true,
        ]);

        return $app;
    }

    public function test_control_host_cannot_fall_through_to_tenant_login(): void
    {
        $this->get('https://admin.counterpos.pk/login')
            ->assertOk()
            ->assertSeeText('Restricted administration.');
    }

    public function test_control_host_cannot_fall_through_to_tenant_api(): void
    {
        $this->getJson('https://admin.counterpos.pk/api/get_user_auth')
            ->assertNotFound();
    }

    public function test_superadmin_can_sign_in_with_a_one_time_recovery_code(): void
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Local Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-horse-battery-staple'),
            'is_active' => true,
        ]);
        $admin->forceFill([
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'recovery_code_hashes' => [Hash::make('ABCD-EFGH-IJKL')],
        ])->save();

        $this->post('https://admin.counterpos.pk/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery-staple',
            'code' => 'ABCD-EFGH-IJKL',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($admin, 'control');
        $this->assertSame([], $admin->fresh()->recovery_code_hashes);
    }

    public function test_authenticated_superadmin_can_register_a_tenant(): void
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Local Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-horse-battery-staple'),
            'is_active' => true,
        ]);
        $admin->forceFill([
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'recovery_code_hashes' => [Hash::make('MNOP-QRST-UVWX')],
        ])->save();

        $this->post('https://admin.counterpos.pk/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery-staple',
            'code' => 'MNOP-QRST-UVWX',
        ])->assertRedirect();

        $this->post('https://admin.counterpos.pk/tenants', [
                'name' => 'Tenant Alpha',
                'slug' => 'tenant-alpha',
                'contact_email' => 'owner@alpha.test',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('provisioning', Tenant::query()->where('slug', 'tenant-alpha')->value('status'));
    }
}
