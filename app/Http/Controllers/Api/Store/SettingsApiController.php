<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\StoreSetting;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

class SettingsApiController extends Controller
{
    /**
     * Return settings (create sane defaults if missing).
     * Also migrates old home_collections -> homepage_lineup (once), if present.
     */
    public function show(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);
        $s = StoreSetting::first();
        $setting = \App\Models\Setting::first();
        $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);
        $currencies = Currency::whereNull('deleted_at')->get(['id', 'name', 'symbol']);
        $default_currency_id = $setting?->Currency?->id ?? null;

        if (! $s) {
            $s = StoreSetting::create([
                'enabled' => 1,
                'store_name' => 'StoreX',
                'primary_color' => '#6c5ce7',
                'secondary_color' => '#00c2ff',
                'font_family' => 'Arial, sans-serif',
                'favicon_path' => 'images/store/favicon.ico',
                'hero_image_path' => 'images/store/hero_image.jpg',
                'language' => 'en',
                'currency_code' => $default_currency_id,
                'default_warehouse_id' => $setting?->warehouse_id ?: (Warehouse::first()?->id ?? null),
                'allow_overselling' => true,
                'hide_out_of_stock' => false,
                'hide_prices_for_guests' => false,

                'contact_email' => 'info@storex.test',
                'contact_phone' => '+1234567890',
                'contact_address' => '123 Main St, Sample City',

                'hero_title' => 'Sell online & in-store',
                'hero_subtitle' => 'Beautiful storefront. Synced inventory.',
                'seo_meta_title' => 'Online Store',
                'seo_meta_description' => 'A modern online storefront powered by your POS & Inventory system.',

                'topbar_text_left' => '🚚 Free shipping on orders over $99',
                'topbar_text_right' => '🔥 Summer deals are live!',
                'footer_text' => 'A beautiful demo storefront paired with your POS & Inventory system.',

                'social_links' => json_encode([
                    ['platform' => 'facebook',  'url' => 'https://facebook.com'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

                'homepage_lineup' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'homepage_layout' => 'default',

            ]);
        }

        // ---- One-time migration: old home_collections -> new homepage_lineup
        // Accept old shape: [{collection_id, title, limit, visible, sort_order}]
        if (empty($s->homepage_lineup) && ! empty($s->home_collections)) {
            $migrated = $this->migrateHomeCollectionsToHomepageLineup($s->home_collections);
            if (! empty($migrated)) {
                $s->homepage_lineup = $migrated;
                $s->save();
            }
        }

        $s->default_currency_id = $default_currency_id;

        $pendingCustomersCount = \App\Models\EcommerceClient::where('status', 0)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'settings' => $s,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'pending_customers_count' => $pendingCustomersCount,
        ]);
    }

    /**
     * Update settings + handle image uploads.
     */
    public function update(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        // --- Normalize boolean to 0/1 so in:0,1 passes ---
        if ($request->has('enabled')) {
            $request->merge([
                'enabled' => (int) filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        foreach (['registration_enabled', 'require_invite_code', 'require_admin_approval'] as $regField) {
            if ($request->has($regField)) {
                $request->merge([
                    $regField => (int) filter_var($request->input($regField), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
        if ($request->has('allow_overselling')) {
            $request->merge([
                'allow_overselling' => (int) filter_var($request->input('allow_overselling'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        if ($request->has('hide_out_of_stock')) {
            $request->merge([
                'hide_out_of_stock' => (int) filter_var($request->input('hide_out_of_stock'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        if ($request->has('hide_prices_for_guests')) {
            $request->merge([
                'hide_prices_for_guests' => (int) filter_var($request->input('hide_prices_for_guests'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        if ($request->has('show_stock')) {
            $request->merge([
                'show_stock' => (int) filter_var($request->input('show_stock'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // --- Validation ---
        $data = $request->validate([
            'enabled' => 'nullable|in:0,1',
            'registration_enabled' => 'nullable|in:0,1',
            'require_invite_code' => 'nullable|in:0,1',
            'require_admin_approval' => 'nullable|in:0,1',
            'allow_overselling' => 'nullable|in:0,1',
            'hide_out_of_stock' => 'nullable|in:0,1',
            'hide_prices_for_guests' => 'nullable|in:0,1',
            'show_stock' => 'nullable|in:0,1',

            'store_name' => 'nullable|string|max:190',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'font_family' => 'nullable|string|max:100',

            'language' => 'nullable|string|max:10',
            'default_currency_id' => 'required|integer',
            'default_warehouse_id' => 'required|integer',

            'contact_email' => 'nullable|email|max:190',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:255',

            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:1000',

            'seo_meta_title' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string|max:1000',

            'topbar_text_left' => 'nullable|string|max:190',
            'topbar_text_right' => 'nullable|string|max:190',
            'footer_text' => 'nullable|string|max:255',

            'social_links' => 'nullable',
            'homepage_lineup' => 'nullable',
            'homepage_layout' => 'nullable|string|in:default',
            'home_collections' => 'nullable',

            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',

            // Laravel 12: image rule excludes SVG by default; using file + mimes
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'favicon' => 'nullable|file|mimes:jpg,jpeg,png,webp,ico|mimetypes:image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon|max:2048',
            'hero_image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        // --- Decode JSON fields ---
        foreach (['social_links', 'homepage_lineup', 'home_collections'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = $decoded;
                }
            }
        }

        // --- Normalize unified lineup ---
        if (array_key_exists('homepage_lineup', $data)) {
            $data['homepage_lineup'] = $this->normalizeHomepageLineup($data['homepage_lineup']);
        }

        // --- Migrate legacy collections if needed ---
        if (! empty($data['home_collections']) && empty($data['homepage_lineup'])) {
            $data['homepage_lineup'] = $this->migrateHomeCollectionsToHomepageLineup($data['home_collections']);
        }
        unset($data['home_collections']); // don't persist legacy field

        // --- Ensure model exists ---
        $s = StoreSetting::first() ?: new StoreSetting;

        // --- Ensure storage directory exists ---
        $targetDir = public_path('images/store');
        if (! File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // ============================
        // IMAGE UPLOAD HANDLERS
        // ============================

        // --- LOGO (200x200 max) ---
        if ($request->hasFile('logo')) {
            if ($s->logo_path && File::exists(public_path($s->logo_path))) {
                File::delete(public_path($s->logo_path));
            }

            $ext = strtolower($request->file('logo')->guessExtension() ?: 'png');
            $filename = (string) Str::uuid().'.'.$ext;

            Image::make($request->file('logo')->getRealPath())
                ->resize(200, 200, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                })
                ->encode($ext, 85)
                ->save($targetDir.'/'.$filename);

            $data['logo_path'] = 'images/store/'.$filename;
        }

        // --- FAVICON ---
        if ($request->hasFile('favicon')) {
            if ($s->favicon_path && File::exists(public_path($s->favicon_path))) {
                File::delete(public_path($s->favicon_path));
            }

            $file = $request->file('favicon');
            $mime = $file->getMimeType() ?: '';
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

            if ($ext === 'ico' || in_array($mime, ['image/x-icon', 'image/vnd.microsoft.icon'], true)) {
                $filename = (string) Str::uuid().'.ico';
                $file->move($targetDir, $filename);
            } else {
                $filename = (string) Str::uuid().'.png';
                Image::make($file->getRealPath())
                    ->fit(64, 64)
                    ->encode('png')
                    ->save($targetDir.'/'.$filename);
            }

            $data['favicon_path'] = 'images/store/'.$filename;
        }

        // --- HERO IMAGE (1600x800 max) ---
        if ($request->hasFile('hero_image')) {
            if ($s->hero_image_path && File::exists(public_path($s->hero_image_path))) {
                File::delete(public_path($s->hero_image_path));
            }

            $ext = strtolower($request->file('hero_image')->guessExtension() ?: 'jpg');
            $filename = (string) Str::uuid().'.'.$ext;

            Image::make($request->file('hero_image')->getRealPath())
                ->resize(1600, 800, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                })
                ->encode($ext, 82)
                ->save($targetDir.'/'.$filename);

            $data['hero_image_path'] = 'images/store/'.$filename;
        }

        // ============================
        // CURRENCY HANDLING
        // ============================

        if (! empty($data['default_currency_id'])) {
            $currency = Currency::find($data['default_currency_id']);
            if ($currency) {
                $data['currency_code'] = $currency->symbol;

                $setting = \App\Models\Setting::first();
                if ($setting) {
                    $setting->currency_id = $currency->id;
                    $setting->save();
                }
            }
        }

        // ============================
        // SAVE CHANGES
        // ============================

        $s->fill($data)->save();

        return response()->json($s->fresh());
    }

    /**
     * Return only calendar-related settings (for System Settings → Calendar tab).
     */
    public function showCalendar(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Setting::class);
        $s = Setting::first();
        if (! $s) {
            return response()->json([
                'google_calendar_connected' => false,
                'google_calendar_connect_url' => route('google_calendar.connect'),
                'google_calendar_disconnect_url' => route('google_calendar.disconnect'),
                'google_calendar_client_id' => null,
                'google_calendar_client_secret_set' => false,
                'google_calendar_redirect_uri' => null,
                'google_calendar_calendar_id' => null,
            ]);
        }
        return response()->json([
            'google_calendar_connected' => ! empty($s->google_calendar_refresh_token),
            'google_calendar_connect_url' => route('google_calendar.connect'),
            'google_calendar_disconnect_url' => route('google_calendar.disconnect'),
            'google_calendar_client_id' => $s->google_calendar_client_id,
            'google_calendar_client_secret_set' => ! empty($s->google_calendar_client_secret),
            'google_calendar_redirect_uri' => $s->google_calendar_redirect_uri,
            'google_calendar_calendar_id' => $s->google_calendar_calendar_id,
        ]);
    }

    /**
     * Update only calendar-related settings (credentials, calendar ID).
     */
    public function updateCalendar(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Setting::class);
        $data = $request->validate([
            'google_calendar_client_id' => 'nullable|string|max:255',
            'google_calendar_client_secret' => 'nullable|string|max:65535',
            'google_calendar_redirect_uri' => 'nullable|string|max:512',
            'google_calendar_calendar_id' => 'nullable|string|max:255',
        ]);
        $s = Setting::first();
        if (! $s) {
            return response()->json(['error' => 'Settings record not found.'], 404);
        }
        // Do not overwrite client_secret with empty value (user may leave it blank to keep existing)
        if (array_key_exists('google_calendar_client_secret', $data) && $data['google_calendar_client_secret'] === '') {
            unset($data['google_calendar_client_secret']);
        }
        $s->fill($data)->save();
        return response()->json(['success' => true]);
    }

    /**
     * Normalize homepage_lineup to an array of items like:
     *  - {"type":"hero"}
     *  - {"type":"newsletter"}
     *  - {"type":"collection","slug":"best_sellers","limit":8,"layout":"grid","title_override":""}
     */
    private function normalizeHomepageLineup($val): array
    {
        if (! $val || ! is_array($val)) {
            return [];
        }

        $out = [];
        $seenCollections = [];
        $hasHero = false;
        $hasNewsletter = false;
        $hasPromoBanner = false;
        $hasCategories = false;

        foreach ($val as $row) {
            if (! is_array($row) || empty($row['type'])) {
                continue;
            }
            $type = strtolower((string) $row['type']);

            if ($type === 'hero') {
                if ($hasHero) {
                    continue;
                }
                $out[] = ['type' => 'hero'];
                $hasHero = true;

                continue;
            }

            if ($type === 'newsletter') {
                if ($hasNewsletter) {
                    continue;
                }
                $out[] = ['type' => 'newsletter'];
                $hasNewsletter = true;

                continue;
            }

            if ($type === 'promo_banner') {
                if ($hasPromoBanner) {
                    continue;
                }
                $hasPromoBanner = true;
                $out[] = [
                    'type' => 'promo_banner',
                    'title' => isset($row['title']) ? (string) $row['title'] : '',
                    'subtitle' => isset($row['subtitle']) ? (string) $row['subtitle'] : '',
                    'button_text' => isset($row['button_text']) ? (string) $row['button_text'] : __('Shop Now'),
                    'link' => isset($row['link']) ? (string) $row['link'] : '',
                ];

                continue;
            }

            if ($type === 'categories') {
                if ($hasCategories) {
                    continue;
                }
                $hasCategories = true;
                $out[] = [
                    'type' => 'categories',
                    'title' => isset($row['title']) ? (string) $row['title'] : '',
                ];

                continue;
            }

            if ($type === 'collection') {
                // Accept slug (preferred). Back-compat: accept "handle"
                $slug = isset($row['slug']) ? (string) $row['slug'] : (isset($row['handle']) ? (string) $row['handle'] : '');
                $slug = trim($slug);
                if ($slug === '') {
                    continue;
                }

                if (isset($seenCollections[$slug])) {
                    continue;
                } // de-dupe by slug
                $seenCollections[$slug] = true;

                $limit = isset($row['limit']) ? (int) $row['limit'] : 8;
                if ($limit <= 0) {
                    $limit = 8;
                }

                $layout = isset($row['layout']) && in_array($row['layout'], ['grid', 'carousel'], true)
                    ? $row['layout'] : 'grid';

                $titleOverride = isset($row['title_override']) ? (string) $row['title_override'] : '';

                $out[] = [
                    'type' => 'collection',
                    'slug' => $slug,
                    'limit' => $limit,
                    'layout' => $layout,
                    'title_override' => $titleOverride,
                ];
            }
        }

        return $out;
    }

    /**
     * Migrate legacy home_collections (by collection_id) to homepage_lineup (by slug).
     * Accepts rows like: [{collection_id, title, limit, visible, sort_order}]
     * Requires a Collection model with id/slug; missing slugs are skipped.
     */
    private function migrateHomeCollectionsToHomepageLineup($val): array
    {
        if (! $val || ! is_array($val)) {
            return [];
        }

        // Filter only visible rows and sort by sort_order
        $rows = array_values(array_filter($val, function ($r) {
            if (! is_array($r)) {
                return false;
            }
            if (empty($r['collection_id'])) {
                return false;
            }
            $visible = array_key_exists('visible', $r)
                ? filter_var($r['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true;

            return $visible !== false; // treat null as true
        }));

        usort($rows, function ($a, $b) {
            $sa = (int) ($a['sort_order'] ?? 9999);
            $sb = (int) ($b['sort_order'] ?? 9999);

            return $sa <=> $sb;
        });

        $ids = array_unique(array_map(fn ($r) => (int) $r['collection_id'], $rows));
        if (empty($ids)) {
            return [];
        }

        // Map IDs -> slugs (skip when model/table not available)
        $idToSlug = [];
        if (class_exists(Collection::class)) {
            $idToSlug = Collection::query()
                ->whereIn('id', $ids)
                ->pluck('slug', 'id')
                ->toArray();
        }

        $out = [];
        foreach ($rows as $r) {
            $cid = (int) $r['collection_id'];
            $slug = (string) ($idToSlug[$cid] ?? '');
            if ($slug === '') {
                continue;
            }

            $limit = (int) ($r['limit'] ?? 8);
            if ($limit <= 0) {
                $limit = 8;
            }

            $out[] = [
                'type' => 'collection',
                'slug' => $slug,
                'limit' => $limit,
                'layout' => 'grid',
                'title_override' => '', // legacy title is not forced on home; keep clean
            ];
        }

        // You can prepend hero/newsletter here if you want defaults; we leave as-is.
        return $out;
    }
}
