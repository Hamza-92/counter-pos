<?php

use Illuminate\Support\Facades\Route;

// The authenticated control-plane UI is implemented in the next phase. Until
// then the exact control host deliberately exposes no tenant application.
// Do not use Route::fallback here. Laravel intentionally evaluates fallback
// routes after every normal route, which would allow a domainless tenant route
// to claim the control host first. This domain-scoped catch-all is registered
// before tenant routes and therefore closes the entire control-host namespace.
Route::any('/{controlPath?}', static function () {
    return response('Control plane initialization is pending.', 503)
        ->header('Cache-Control', 'no-store');
})->where('controlPath', '.*');
