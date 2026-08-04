<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\BlockUnsafeSharedOperations;
use Illuminate\Http\Request;
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

    public function test_legacy_destructive_migration_command_is_disabled(): void
    {
        $this->artisan('auto:Migrate')->assertExitCode(1);
    }
}
