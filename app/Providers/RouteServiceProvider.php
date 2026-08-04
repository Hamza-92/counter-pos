<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';
    // protected $namespace = 'App';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        // Register the exact control domain first. The tenant routes are
        // intentionally domainless because customers bring their own hosts;
        // registering them first would shadow matching control-plane paths.
        $this->mapControlRoutes();

        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapPortalRoutes();
    }

    protected function mapControlRoutes()
    {
        if (! config('tenancy.enabled', false)) {
            return;
        }

        Route::domain(config('tenancy.control_host'))
            ->middleware(['web', 'control.host'])
            ->group(base_path('routes/control.php'));
    }

    /**
     * Define the client portal API routes (session-based auth).
     */
    protected function mapPortalRoutes()
    {
        Route::middleware($this->tenantMiddleware('web'))
            ->namespace($this->namespace)
            ->group(base_path('routes/portal.php'));
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware($this->tenantMiddleware('web'))
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware($this->tenantMiddleware('api'))
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }

    private function tenantMiddleware(string $group): array
    {
        return config('tenancy.enabled', false) ? [$group, 'tenant.host'] : [$group];
    }
}
