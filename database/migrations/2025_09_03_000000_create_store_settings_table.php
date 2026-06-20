<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('store_name')->default('StoreX');

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color')->default('#6c5ce7');
            $table->string('secondary_color')->default('#00c2ff');
            $table->string('font_family')->default('Poppins, system-ui, Segoe UI, Roboto, Arial, sans-serif');

            // Hero section (multilang could be JSON later)
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_image_path')->nullable();

            // Content blocks
            $table->json('homepage_lineup')->nullable(); // order & visibility
            $table->json('social_links')->nullable();      // {facebook:"", instagram:""}

            // Business
            $table->integer('default_warehouse_id')->nullable();
            $table->string('currency_code', 8)->default('$');
            $table->string('language', 10)->default('en');

            // Contact & SEO
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('seo_meta_title')->nullable();
            $table->text('seo_meta_description')->nullable();

            $table->string('topbar_text_left')->nullable();
            $table->string('topbar_text_right')->nullable();
            $table->string('footer_text')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
