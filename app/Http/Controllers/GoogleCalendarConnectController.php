<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class GoogleCalendarConnectController extends Controller
{
    /**
     * Build OAuth client for connect/callback (no stored token).
     */
    private function oauthClient(): GoogleClient
    {
        $s = Setting::first();
        $clientId = $s ? ($s->google_calendar_client_id ?? config('google_calendar.client_id')) : config('google_calendar.client_id');
        $clientSecret = $s ? ($s->google_calendar_client_secret ?? config('google_calendar.client_secret')) : config('google_calendar.client_secret');
        $redirectUri = $s && ! empty($s->google_calendar_redirect_uri) ? $s->google_calendar_redirect_uri : (config('google_calendar.redirect_uri') ?? url('/google-calendar/callback'));

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope('https://www.googleapis.com/auth/calendar');
        $client->addScope('https://www.googleapis.com/auth/calendar.events');
        return $client;
    }

    /**
     * Redirect to Google OAuth consent screen.
     * Route: GET /google-calendar/connect (middleware: web, auth:web)
     */
    public function connect(Request $request): RedirectResponse
    {
        $s = Setting::first();
        $clientId = $s ? ($s->google_calendar_client_id ?? config('google_calendar.client_id')) : config('google_calendar.client_id');
        $clientSecret = $s ? ($s->google_calendar_client_secret ?? config('google_calendar.client_secret')) : config('google_calendar.client_secret');
        if (! $clientId || ! $clientSecret) {
            Log::warning('Google Calendar: client_id or client_secret not set');
            return redirect($this->settingsRedirectUrl('error=config'));
        }

        $client = $this->oauthClient();
        $client->setState(encrypt(json_encode([
            'intent' => 'google_calendar_connect',
            'time'   => time(),
        ])));
        $authUrl = $client->createAuthUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback: exchange code for tokens and save refresh_token.
     * Route: GET /google-calendar/callback (middleware: web, auth:web)
     */
    public function callback(Request $request): RedirectResponse
    {
        $s = Setting::first();
        $clientId = $s ? ($s->google_calendar_client_id ?? config('google_calendar.client_id')) : config('google_calendar.client_id');
        $clientSecret = $s ? ($s->google_calendar_client_secret ?? config('google_calendar.client_secret')) : config('google_calendar.client_secret');
        if (! $clientId || ! $clientSecret) {
            return redirect($this->settingsRedirectUrl('error=config'));
        }

        $code = $request->query('code');
        $error = $request->query('error');

        if ($error) {
            Log::warning('Google Calendar OAuth error', ['error' => $error]);
            return redirect($this->settingsRedirectUrl('error='.urlencode($error)));
        }

        if (! $code) {
            return redirect($this->settingsRedirectUrl('error=no_code'));
        }

        try {
            $client = $this->oauthClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Log::warning('Google Calendar token error', $token);
                return redirect($this->settingsRedirectUrl('error=token'));
            }

            $refreshToken = $token['refresh_token'] ?? null;
            if (! $refreshToken) {
                Log::warning('Google Calendar: no refresh_token in response; user may need to revoke app and reconnect with prompt=consent');
                return redirect($this->settingsRedirectUrl('error=no_refresh_token'));
            }

            $settings = Setting::first();
            if (! $settings) {
                return redirect($this->settingsRedirectUrl('error=no_settings'));
            }
            $settings->google_calendar_refresh_token = $refreshToken;
            $settings->save();

            return redirect($this->settingsRedirectUrl('google_calendar=connected'));
        } catch (\Throwable $e) {
            Log::error('Google Calendar callback exception', ['message' => $e->getMessage()]);
            return redirect($this->settingsRedirectUrl('error=exception'));
        }
    }

    /**
     * Disconnect: clear stored refresh token (optional route for admin).
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $settings = Setting::first();
        if ($settings) {
            $settings->google_calendar_refresh_token = null;
            $settings->save();
        }
        return redirect($this->settingsRedirectUrl('google_calendar=disconnected'));
    }

    private function settingsRedirectUrl(string $query): string
    {
        $base = config('google_calendar.redirect_after_connect') ?? url('/');
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.$query;
    }
}
