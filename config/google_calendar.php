<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google OAuth2 credentials (from Google Cloud Console)
    |--------------------------------------------------------------------------
    */
    'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    'redirect_uri'  => env('GOOGLE_CALENDAR_REDIRECT_URI', null), // e.g. https://yourdomain.com/google-calendar/callback

    /*
    |--------------------------------------------------------------------------
    | Where to redirect after connect/callback (admin SPA base URL)
    |--------------------------------------------------------------------------
    */
    'redirect_after_connect' => env('GOOGLE_CALENDAR_REDIRECT_AFTER', null), // e.g. https://yourdomain.com (defaults to url('/'))

    /*
    |--------------------------------------------------------------------------
    | Default calendar ID (when not set in store settings)
    |--------------------------------------------------------------------------
    */
    'default_calendar_id' => 'primary',

    /*
    |--------------------------------------------------------------------------
    | Event reminders (minutes before start)
    |--------------------------------------------------------------------------
    | email = email reminder, popup = in-app/browser push
    */
    'reminders' => [
        ['method' => 'email', 'minutes' => 24 * 60],   // 1 day before
        ['method' => 'email', 'minutes' => 60],        // 1 hour before
        ['method' => 'popup', 'minutes' => 30],        // 30 min before
    ],
];
