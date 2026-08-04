<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\BlockUnsafeSharedOperations;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UnsafeOperationGuardTest extends ControlPlaneTestCase
{
    public function test_it_hides_standalone_destructive_endpoints_in_tenancy_mode(): void
    {
        config(['tenancy.enabled' => true]);
        $request = Request::create('/api/one_click_update', 'POST');

        $this->expectException(NotFoundHttpException::class);
        app(BlockUnsafeSharedOperations::class)->handle($request, static fn () => response('unsafe'));
    }

    /**
     * Customer-scoped settings and backups are safe because they persist on
     * the resolved tenant connection instead of changing shared code/config.
     * These endpoints must remain reachable in shared mode.
     */
    #[DataProvider('tenantScopedPathProvider')]
    public function test_it_allows_tenant_scoped_settings_and_backup_endpoints(string $path): void
    {
        config(['tenancy.enabled' => true]);
        $request = Request::create($path, 'GET');

        $response = app(BlockUnsafeSharedOperations::class)->handle(
            $request,
            static fn () => response('safe'),
        );

        $this->assertSame('safe', $response->getContent());
    }

    public static function tenantScopedPathProvider(): array
    {
        return [
            'mail settings' => ['/api/get_config_mail'],
            'sms settings' => ['/api/get_sms_config'],
            'payment gateway' => ['/api/get_payment_gateway'],
            'quickbooks' => ['/api/quickbooks/settings'],
            'list backups' => ['/api/get_backup'],
            'generate backup' => ['/api/generate_new_backup'],
            'clear tenant cache' => ['/api/clear_cache'],
        ];
    }

    public function test_legacy_destructive_migration_command_is_disabled(): void
    {
        $this->artisan('auto:Migrate')->assertExitCode(1);
    }
}
