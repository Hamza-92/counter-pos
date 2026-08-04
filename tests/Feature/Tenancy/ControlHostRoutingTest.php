<?php

namespace Tests\Feature\Tenancy;

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

        return parent::createApplication();
    }

    public function test_control_host_cannot_fall_through_to_tenant_login(): void
    {
        $this->get('https://admin.counterpos.pk/login')
            ->assertStatus(503)
            ->assertSeeText('Control plane initialization is pending.');
    }

    public function test_control_host_cannot_fall_through_to_tenant_api(): void
    {
        $this->getJson('https://admin.counterpos.pk/api/get_user_auth')
            ->assertStatus(503);
    }
}
