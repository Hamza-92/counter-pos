<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\TenantAccessUnavailablePage;
use Tests\TestCase;

class TenantAccessUnavailablePageTest extends TestCase
{
    public function test_it_renders_the_restriction_reason_and_support_action(): void
    {
        config()->set('tenancy.support.email', 'support@counterpos.pk');
        config()->set('tenancy.support.website', 'https://counterpos.pk');

        $html = (new TenantAccessUnavailablePage)->render('suspended', 'Your subscription has expired.');

        $this->assertStringContainsString('Tenant access unavailable', $html);
        $this->assertStringContainsString('Your subscription has expired.', $html);
        $this->assertStringContainsString('Contact support', $html);
        $this->assertStringContainsString('support@counterpos.pk', $html);
        $this->assertStringContainsString('Your store data is safe', $html);
    }

    public function test_it_escapes_restriction_reasons_before_rendering_them(): void
    {
        config()->set('tenancy.support.email', '');
        config()->set('tenancy.support.website', '');

        $html = (new TenantAccessUnavailablePage)->render('suspended', '<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert', $html);
        $this->assertStringNotContainsString('%%SUPPORT_ACTIONS%%', $html);
    }
}
