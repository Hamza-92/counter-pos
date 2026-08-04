<?php

namespace App\Http\Middleware;

use App\Tenancy\ControlPlaneContext;
use App\Tenancy\Exceptions\InvalidHostException;
use App\Tenancy\Exceptions\TenantDatabaseException;
use App\Tenancy\HostNormalizer;
use App\Tenancy\TenantDatabaseManager;
use App\Tenancy\TenantResolver;
use App\Tenancy\TenancyManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantOrControlPlane
{
    public function __construct(
        private readonly HostNormalizer $normalizer,
        private readonly TenantResolver $resolver,
        private readonly TenantDatabaseManager $databaseManager,
        private readonly TenancyManager $tenancy,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tenancy.enabled', false)) {
            return $next($request);
        }

        try {
            $host = $this->normalizer->normalize($request->getHost());
            $controlHost = $this->normalizer->normalize((string) config('tenancy.control_host'));
        } catch (InvalidHostException) {
            abort(404);
        }

        try {
            if (hash_equals($controlHost, $host)) {
                $this->databaseManager->useControlConnection();
                $this->tenancy->enterControlPlane(new ControlPlaneContext($host));
                Config::set('session.driver', 'database');
                Config::set('session.connection', 'control');
                Config::set('session.cookie', config('tenancy.cookies.control'));
                Config::set('session.domain', null);
                Config::set('session.same_site', 'strict');
                Config::set('session.secure', $request->isSecure());
                Config::set('cache.default', 'database');
                Config::set('cache.stores.database.connection', 'control');
                Config::set('cache.prefix', 'counterpos_control_cache');
                Config::set('queue.connections.database.connection', 'control');
                Config::set('queue.failed.database', 'control');
            } else {
                $tenant = $this->resolver->resolve($host);
                if ($tenant === null) {
                    abort(404);
                }

                if (! $tenant->access->allowed) {
                    return $this->accessDeniedResponse($tenant->access->reason, $tenant->access->state);
                }

                $this->databaseManager->initialize($tenant);
                $this->tenancy->enterTenant($tenant);
                Config::set('session.driver', 'database');
                Config::set('session.connection', 'tenant');
                Config::set('session.cookie', config('tenancy.cookies.tenant_prefix').substr(hash('sha256', $tenant->tenantId), 0, 16));
                Config::set('session.domain', null);
                Config::set('session.same_site', 'lax');
                Config::set('session.secure', $request->isSecure());
                Config::set('cache.default', 'database');
                Config::set('cache.stores.database.connection', 'tenant');
                Config::set('cache.prefix', 'tenant_'.$tenant->tenantId.'_cache');
                Config::set('queue.connections.database.connection', 'control');
                Config::set('queue.failed.database', 'control');
                $tenantUrl = $request->getScheme().'://'.$tenant->primaryHost;
                Config::set('filesystems.disks.public.root', storage_path('app/public/tenants/'.$tenant->tenantId));
                Config::set('filesystems.disks.public.url', $tenantUrl.'/storage/tenants/'.$tenant->tenantId);
                Config::set('filesystems.disks.local.root', storage_path('app/tenants/'.$tenant->tenantId.'/private'));
                Config::set('app.url', $tenantUrl);
                $request->attributes->set('tenant_id', $tenant->tenantId);
            }

            return $next($request);
        } catch (TenantDatabaseException) {
            Log::warning('Tenant database initialization failed.', [
                'host_hash' => hash('sha256', $host),
                'request_id' => $request->headers->get('X-Request-Id'),
            ]);

            return response('Service temporarily unavailable.', 503)
                ->header('Cache-Control', 'no-store');
        } finally {
            $this->databaseManager->reset();
            $this->tenancy->reset();
        }
    }

    private function accessDeniedResponse(?string $reason, string $state): Response
    {
        $status = $state === 'suspended' ? 423 : 402;
        $message = htmlspecialchars($reason ?: 'This account is not currently active.', ENT_QUOTES, 'UTF-8');

        return response(
            '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="robots" content="noindex">'.
            '<title>Account unavailable</title><body><main><h1>Account unavailable</h1><p>'.$message.'</p></main></body></html>',
            $status,
            ['Cache-Control' => 'no-store']
        );
    }
}
