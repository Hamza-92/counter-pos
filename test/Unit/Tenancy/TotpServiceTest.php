<?php

namespace Tests\Unit\Tenancy;

use App\Services\ControlPlane\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_it_generates_authenticator_compatible_secrets_and_uris(): void
    {
        $service = new TotpService;
        $secret = $service->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertStringContainsString('otpauth://totp/', $service->provisioningUri($secret, 'admin@example.test'));
        $this->assertCount(8, $service->generateRecoveryCodes());
    }

    public function test_it_verifies_a_known_totp_vector(): void
    {
        $service = new TotpService;

        $this->assertTrue($service->verify(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            '287082',
            0,
            59,
        ));
        $this->assertFalse($service->verify(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            '000000',
            0,
            59,
        ));
    }
}
