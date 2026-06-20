<?php

namespace App\Http\Controllers;

use App\Models\QuickBooksAudit;
use App\Models\QuickBooksToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use QuickBooksOnline\API\DataService\DataService;

class QuickBooksController extends Controller
{
    private function env(): string
    {
        $e = strtolower(trim(config('services.quickbooks.env', 'Development')));

        return in_array($e, ['development', 'dev', 'sandbox'], true) ? 'Development' : 'Production';
    }

    private function redirectUri(): string
    {
        return (string) config('services.quickbooks.redirect');
    }

    /** Small audit helper (never blocks the main flow) */
    private function audit(string $operation, string $level, array $ctx = []): void
    {
        try {
            QuickBooksAudit::create([
                'user_id' => $ctx['user_id'] ?? (Auth::id() ?? null),
                'sale_id' => $ctx['sale_id'] ?? null,
                'realm_id' => $ctx['realm_id'] ?? null,
                'environment' => $ctx['environment'] ?? $this->env(),
                'operation' => $operation,              // connect|callback|disconnect|settings.save|status
                'level' => $level,                  // info|warning|error
                'message' => $ctx['message'] ?? null,
                'http_code' => $ctx['http'] ?? null,
                'request_payload' => isset($ctx['request']) ? (is_string($ctx['request']) ? $ctx['request'] : json_encode($ctx['request'])) : null,
                'response_body' => isset($ctx['response']) ? (is_string($ctx['response']) ? $ctx['response'] : json_encode($ctx['response'])) : null,
                'sdk_error' => $ctx['sdk_error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::debug('QuickBooksAudit write failed: '.$e->getMessage());
        }
    }

    /** GET /quickbooks/connect (behind auth) */
    public function connect(Request $request)
    {
        $env = $this->env();
        $redirect = $this->redirectUri();

        Log::info('QBO CONNECT start', compact('env', 'redirect'));

        $ds = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'RedirectURI' => $redirect,
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => $env, // Development | Production
        ]);

        $helper = $ds->getOAuth2LoginHelper();

        // Let the SDK generate the URL (it includes its own state)
        $authUrl = $helper->getAuthorizationCodeURL();

        // Extract the SDK's state so we cache the SAME one (avoid 2x state)
        $query = parse_url($authUrl, PHP_URL_QUERY);
        parse_str($query ?? '', $params);
        $sdkState = $params['state'] ?? null;

        if (! $sdkState) {
            // Extremely rare; generate exactly one if SDK didn’t
            $sdkState = Str::random(40);
            $authUrl .= (str_contains($authUrl, '?') ? '&' : '?').'state='.$sdkState;
        }

        Cache::put("qbo_state_{$sdkState}", [
            'user_id' => Auth::id(),
            'intended' => $request->query('intended', '/app/settings/quickbooks_sync'),
        ], now()->addMinutes(10));

        $this->audit('connect', 'info', [
            'message' => 'Auth URL generated',
            'request' => ['auth_url' => $authUrl, 'state' => $sdkState, 'env' => $env, 'redirect' => $redirect],
        ]);

        return redirect()->away($authUrl);
    }

    /** GET /quickbooks/callback (NO auth) */
    public function callback(Request $request)
    {
        $env = $this->env();
        $redirect = $this->redirectUri();
        $code = $request->query('code');
        $realmId = $request->query('realmId');
        $state = $request->query('state');
        $err = $request->query('error');
        $errDesc = $request->query('error_description');

        Log::info('QBO CALLBACK', [
            'env' => $env,
            'has_code' => (bool) $code,
            'has_realmId' => (bool) $realmId,
            'error' => $err,
            'error_desc' => $errDesc,
            'redirect_cfg' => $redirect,
            'query' => $request->query(),
        ]);

        if ($err) {
            $this->audit('callback', 'error', [
                'message' => 'Intuit returned an error',
                'request' => $request->query(),
            ]);

            return $this->renderError('Intuit returned an error', [
                'error' => $err,
                'description' => $errDesc,
                'redirect_uri_config' => $redirect,
                'hint' => 'Make sure your Redirect URI matches exactly (scheme/host/path).',
            ]);
        }

        if (! $code || ! $realmId) {
            $this->audit('callback', 'error', [
                'message' => 'Missing code or realmId on callback',
                'request' => $request->query(),
            ]);

            return $this->renderError('Missing code or realmId on callback', [
                'probable_causes' => [
                    'Opened /quickbooks/callback directly (must start at /quickbooks/connect).',
                    'Redirect URI mismatch in Intuit app settings.',
                    'SPA catch-all swallowed callback (exclude /quickbooks/*).',
                    'HTTP vs HTTPS mismatch (must be https).',
                ],
                'env' => $env,
                'redirect_uri_config' => $redirect,
                'callback_url_example' => url('/quickbooks/callback').'?code=...&realmId=...&state=...',
                'received_query' => $request->query(),
            ]);
        }

        $stash = Cache::pull("qbo_state_{$state}");
        if (! $stash) {
            Log::warning('QBO CALLBACK: state missing/expired', compact('state'));
            $this->audit('callback', 'warning', [
                'message' => 'State missing/expired (continuing)',
                'request' => ['returned_state' => $state],
            ]);
            // Proceed anyway; if you want strict, abort(403) here.
        }

        $ds = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'RedirectURI' => $redirect,
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => $env,
        ]);

        try {
            $helper = $ds->getOAuth2LoginHelper();
            $tokenObject = $helper->exchangeAuthorizationCodeForToken($code, $realmId);

            QuickBooksToken::updateOrCreate(
                ['realm_id' => $realmId, 'environment' => $env],
                [
                    'user_id' => $stash['user_id'] ?? null,
                    'access_token' => $tokenObject->getAccessToken(),
                    'refresh_token' => $tokenObject->getRefreshToken(),
                    'access_token_expires_at' => now()->addSeconds(3600), // QBO = 1 hour
                    'refresh_token_expires_at' => now()->addDays(100),
                ]
            );

            Log::info('QBO CONNECTED: tokens saved', ['realm' => $realmId, 'env' => $env]);

            $this->audit('callback', 'info', [
                'message' => 'OAuth connected',
                'realm_id' => $realmId,
            ]);

            $to = $stash['intended'] ?? '/app/settings/quickbooks_sync';

            return redirect($to)->with('success', 'QuickBooks connected.');

        } catch (\Throwable $e) {
            Log::error('QBO CALLBACK exchange failed', [
                'message' => $e->getMessage(),
                'realm' => $realmId,
            ]);

            $this->audit('callback', 'error', [
                'message' => 'OAuth exchange failed',
                'realm_id' => $realmId,
                'sdk_error' => $e->getMessage(),
            ]);

            return $this->renderError('QuickBooks connect failed', [
                'message' => $e->getMessage(),
                'env' => $env,
                'realmId' => $realmId,
                'redirect_uri_config' => $redirect,
            ]);
        }
    }

    /** GET /quickbooks/status (behind auth) */
    public function status()
    {
        $env = $this->env();
        $row = QuickBooksToken::where('environment', $env)->latest()->first();

        $data = [
            'env' => $env,
            'redirect' => $this->redirectUri(),
            'callback' => url('/quickbooks/callback'),
            'has_token' => (bool) $row,
            'token_realm' => $row?->realm_id,
            'updated_at' => (string) $row?->updated_at,
            'connect_url' => route('quickbooks.connect'),
        ];

        return response()->json($data);
    }

    /** POST /quickbooks/disconnect (behind auth) */
    public function disconnect()
    {
        $env = $this->env();
        $rows = QuickBooksToken::where('environment', $env)->get();
        foreach ($rows as $r) {
            $r->delete();
        }

        $this->audit('disconnect', 'info', ['message' => 'Disconnected and tokens removed']);

        return response()->json(['ok' => true]);
    }

    /** GET /quickbooks/settings (behind auth) — read from .env only */
    public function quickbookgetSettings()
    {
        return response()->json(config('services.quickbooks'));
    }

    /** POST /quickbooks/settings (behind auth) — write to .env */
    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'redirect' => 'required|url',
            'env' => 'required|in:Development,Production',
            'realm_id' => 'nullable|string',
            'income_account_name' => 'nullable|string',
        ]);

        $this->writeEnv([
            'QUICKBOOKS_CLIENT_ID' => $data['client_id'],
            'QUICKBOOKS_CLIENT_SECRET' => $data['client_secret'],
            'QUICKBOOKS_REDIRECT_URI' => $data['redirect'],
            'QUICKBOOKS_ENV' => $data['env'],
            'QUICKBOOKS_REALM_ID' => $data['realm_id'] ?? '',
            'QUICKBOOKS_INCOME_ACCOUNT_NAME' => $data['income_account_name'] ?? '',
        ]);

        // refresh config cache
        Artisan::call('config:clear');
        Artisan::call('config:cache');

        $this->audit('settings.save', 'info', [
            'message' => 'Settings saved to .env',
            'request' => ['env' => $data['env'], 'redirect' => $data['redirect']],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Optional: GET /quickbooks/debug (behind auth) — kept for convenience */
    public function debug()
    {
        $env = $this->env();
        $redirect = $this->redirectUri();
        $row = QuickBooksToken::where('environment', $env)->latest()->first();

        return response()->json([
            'env' => $env,
            'redirect' => $redirect,
            'callback' => url('/quickbooks/callback'),
            'has_token' => (bool) $row,
            'token_realm' => $row?->realm_id,
            'updated_at' => (string) $row?->updated_at,
            'connect_url' => route('quickbooks.connect'),
        ]);
    }

    private function writeEnv(array $pairs): void
    {
        $path = base_path('.env');
        if (! File::exists($path)) {
            return;
        }

        $env = File::get($path);
        foreach ($pairs as $key => $value) {
            // quote if necessary
            $escaped = (preg_match('/[\s#"\'=]/', (string) $value))
                ? '"'.str_replace('"', '\"', (string) $value).'"'
                : (string) $value;

            if (preg_match("/^{$key}=.*$/m", $env)) {
                $env = preg_replace("/^{$key}=.*$/m", "{$key}={$escaped}", $env);
            } else {
                $env .= PHP_EOL."{$key}={$escaped}";
            }
        }
        File::put($path, $env);
    }

    public function audits(Request $request)
    {
        $level = $request->query('level'); // optional
        $q = QuickBooksAudit::query()->latest();
        if ($level) {
            $q->where('level', $level);
        }
        $items = $q->paginate(25);

        return response()->json($items);
    }

    private function renderError(string $title, array $details = [])
    {
        $html = "<meta name='viewport' content='width=device-width, initial-scale=1' />"
              ."<div style='font-family:system-ui,Segoe UI,Roboto,Arial;padding:20px;max-width:820px;margin:40px auto'>"
              ."<h2 style='color:#b91c1c;margin:0 0 12px'>".e($title).'</h2><ul>';
        foreach ($details as $k => $v) {
            $pretty = is_array($v) ? '<pre style="white-space:pre-wrap">'.e(print_r($v, true)).'</pre>' : e((string) $v);
            $html .= "<li style='margin:6px 0'><strong>".e($k).":</strong> {$pretty}</li>";
        }
        $html .= "</ul><p><a href='".e(route('quickbooks.connect'))."'"
              ." style='background:#111827;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none'>Start Connect again</a></p></div>";

        return response($html, 400);
    }

    // Count totals with both NULL and empty string
    public function clientsStats()
    {
        $total = \App\Models\Client::where('deleted_at', '=', null)->count();
        $synced = \App\Models\Client::where('deleted_at', '=', null)->whereNotNull('quickbooks_id')
            ->where('quickbooks_id', '!=', '')
            ->count();
        $notSynced = max(0, $total - $synced);

        return response()->json([
            'total' => $total,
            'synced' => $synced,
            'not_synced' => $notSynced,
        ]);
    }

    // Paginated unsynced list with optional search
    public function clientsUnsynced(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 10;

        $builder = \App\Models\Client::query()
            ->where('deleted_at', '=', null)
            ->where(function ($w) {
                $w->whereNull('quickbooks_id')
                    ->orWhere('quickbooks_id', '');
            });

        if ($q !== '') {
            $builder->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $page = $builder->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'items' => $page->items(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
        ]);
    }

    // Sync all or selected clients
    public function syncClients(Request $request)
    {
        /** @var \App\Services\QuickBooksService $qb */
        $qb = app(\App\Services\QuickBooksService::class);

        // Optional selection
        $ids = $request->input('ids', []);
        $query = \App\Models\Client::query()
            ->where('deleted_at', '=', null)
            ->where(function ($w) {
                $w->whereNull('quickbooks_id')->orWhere('quickbooks_id', '');
            });

        if (is_array($ids) && ! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $candidates = $query->limit(250)->get(); // cap batch to protect timeouts

        $synced = 0;
        $failures = [];

        // Quick early exit if no candidates
        if ($candidates->isEmpty()) {
            return response()->json([
                'ok' => true,
                'synced_count' => 0,
                'failed_count' => 0,
                'failures' => [],
                'note' => 'No unsynced clients found',
            ]);
        }

        foreach ($candidates as $client) {
            try {
                // ensureCustomerStandalone should create or match by email
                $res = $qb->ensureCustomerStandalone($client);
                if (($res['ok'] ?? false) && ! empty($res['id'])) {
                    $client->update(['quickbooks_id' => (string) $res['id']]);
                    $synced++;
                } else {
                    $failures[] = [
                        'id' => $client->id,
                        'name' => $client->name,
                        'error' => $res['error'] ?? 'Unknown error',
                    ];
                }
            } catch (\Throwable $e) {
                $failures[] = [
                    'id' => $client->id,
                    'name' => $client->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'synced_count' => $synced,
            'failed_count' => count($failures),
            'failures' => $failures,
        ]);
    }
}
