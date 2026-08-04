<?php

namespace App\Providers;

use App\Models\Setting;
use App\Tenancy\TenancyManager;
use App\Tenancy\TenantQueueBootstrapper;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Console\ClientCommand;
use Laravel\Passport\Console\InstallCommand;
use Laravel\Passport\Console\KeysCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TenancyManager::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        Schema::defaultStringLength(191);

        if (config('tenancy.enabled', false)) {
            Config::set('queue.connections.database.connection', 'control');
            Config::set('queue.failed.database', 'control');

            Queue::createPayloadUsing(function (): array {
                $manager = app(TenancyManager::class);

                return $manager->hasTenant() ? ['tenant_id' => $manager->tenant()->tenantId] : [];
            });

            Queue::before(function (JobProcessing $event): void {
                $tenantId = $event->job->payload()['tenant_id'] ?? null;
                if (is_string($tenantId) && $tenantId !== '') {
                    app(TenantQueueBootstrapper::class)->initialize($tenantId);
                }
            });
            Queue::after(static fn (JobProcessed $event) => app(TenantQueueBootstrapper::class)->reset());
            Queue::exceptionOccurred(static fn (JobExceptionOccurred $event) => app(TenantQueueBootstrapper::class)->reset());
        }

        /* ADD THIS LINES */
        $this->commands([
            InstallCommand::class,
            ClientCommand::class,
            KeysCommand::class,
        ]);

        View::composer('*', function ($view) {
            if (config('tenancy.enabled', false) && ! app(TenancyManager::class)->hasTenant()) {
                return;
            }

            $excluded = [
                'api',
                'setup',
                'update',
                'password',
                'online_store',
            ];

            $firstSegment = Request::segment(1); // Get the first segment of the URL

            if (! in_array($firstSegment, $excluded)) {
                $view->with('app_settings', Setting::first());
                $view->with('categories', \App\Models\Category::with('subcategories')->orderBy('name')->get());
            }
        });

        // Set the default guard to 'store' for all 'store/*' routes
        $this->app['router']->matched(function (\Illuminate\Routing\Events\RouteMatched $event) {
            if ($event->route->action['middleware'] === 'auth.store') {
                Auth::shouldUse('store');
            }
        });
    }
}
