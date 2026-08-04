<?php

$allowedDatabaseHosts = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('TENANT_DB_ALLOWED_HOSTS', 'localhost,127.0.0.1'))
)));

return [
    'enabled' => (bool) env('TENANCY_ENABLED', false),
    'control_host' => strtolower((string) env('CONTROL_PLANE_HOST', 'admin.counterpos.pk')),
    'require_verified_domain' => (bool) env('TENANT_REQUIRE_VERIFIED_DOMAIN', true),
    'require_subscription' => (bool) env('TENANT_REQUIRE_SUBSCRIPTION', true),
    'resolution_cache_seconds' => (int) env('TENANT_RESOLUTION_CACHE_SECONDS', 30),
    'resolution_cache_store' => (string) env('TENANT_RESOLUTION_CACHE_STORE', 'file'),

    'database' => [
        'allowed_drivers' => ['mysql'],
        'allowed_hosts' => $allowedDatabaseHosts,
        'name_prefix' => (string) env('TENANT_DB_NAME_PREFIX', ''),
    ],

    'cookies' => [
        'control' => (string) env('CONTROL_SESSION_COOKIE', 'counterpos_control_session'),
        'tenant_prefix' => (string) env('TENANT_SESSION_COOKIE_PREFIX', 'counterpos_tenant_'),
    ],

    // Standalone-only endpoints that mutate shared files/configuration or run
    // global update operations. They remain unavailable until tenant-scoped
    // replacements are implemented.
    'unsafe_shared_paths' => [
        'setup', 'setup/*', 'update', 'update/*',
        'api/get_version_info', 'api/one_click_update', 'api/update/*',
        'api/clear_cache', 'api/update_status_module',
        'api/get_config_mail', 'api/update_config_mail/*', 'api/test_config_mail',
        'api/get_sms_config*', 'api/update_*_config', 'api/update_Default_SMS',
        'api/payment_gateway', 'api/get_payment_gateway*',
        'quickbooks/*', 'api/quickbooks/*',
    ],
];
