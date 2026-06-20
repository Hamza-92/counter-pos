<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::first();
        $warehouseId = $setting?->warehouse_id ?: Warehouse::value('id');
        $currency = $setting?->Currency?->symbol ?? '$';

        $payload = [
            'id' => 1, // single-row table: primary key
            'enabled' => 1,
            'store_name' => 'StoreX',
            'primary_color' => '#6c5ce7',
            'secondary_color' => '#00c2ff',
            'font_family' => 'Arial, sans-serif',
            'favicon_path' => 'images/store/favicon.ico',
            'hero_image_path' => 'images/store/hero_image.jpg',
            'language' => 'en',

            'currency_code' => $currency,
            'default_warehouse_id' => $warehouseId,
            'allow_overselling' => 1,
            'hide_out_of_stock' => 0,

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

            'homepage_lineup' => json_encode([
                ['type' => 'hero'],
                ['type' => 'newsletter'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // INSERT ONLY. If a row with id=1 already exists, this is ignored.
        DB::table('store_settings')->insertOrIgnore([$payload]);
    }
}
