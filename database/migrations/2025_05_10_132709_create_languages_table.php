<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('name');             // e.g. "French"
            $table->string('locale')->unique(); // e.g. "fr", "en", "ar"
            $table->string('flag')->nullable(); // e.g. "fr.png"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps(6);
        });

        DB::table('languages')->insert([
            [
                'name' => 'English',
                'locale' => 'en',
                'flag' => 'gb.svg',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Français',
                'locale' => 'fr',
                'flag' => 'fr.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'العربية',
                'locale' => 'ar',
                'flag' => 'sa.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Turkish',
                'locale' => 'tur',
                'flag' => 'tr.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Thaï',
                'locale' => 'thai',
                'flag' => 'th.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Hindi',
                'locale' => 'hn',
                'flag' => 'in.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'German',
                'locale' => 'de',
                'flag' => 'de.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Spanish',
                'locale' => 'es',
                'flag' => 'es.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Italien',
                'locale' => 'it',
                'flag' => 'it.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Indonesian',
                'locale' => 'Ind',
                'flag' => 'id.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Simplified Chinese',
                'locale' => 'sm_ch',
                'flag' => 'cn.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Traditional Chinese',
                'locale' => 'tr_ch',
                'flag' => 'cn.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Russian',
                'locale' => 'ru',
                'flag' => 'ru.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Vietnamese',
                'locale' => 'vn',
                'flag' => 'vn.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Korean',
                'locale' => 'kr',
                'flag' => 'kr.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Bangla',
                'locale' => 'ba',
                'flag' => 'bd.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Portuguese',
                'locale' => 'br',
                'flag' => 'pt.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Danish',
                'locale' => 'da',
                'flag' => 'dk.svg',
                'is_active' => true,
                'is_default' => false,
            ],

            // Not

            [
                'name' => 'Japanese',
                'locale' => 'ja',
                'flag' => 'jp.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Polish',
                'locale' => 'pl',
                'flag' => 'pl.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Swahili',
                'locale' => 'sw',
                'flag' => 'ke.svg',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Hausa',
                'locale' => 'ha',
                'flag' => 'ng.svg',
                'is_active' => true,
                'is_default' => false,
            ],

            [
                'name' => 'Yoruba',
                'locale' => 'yo',
                'flag' => 'ng.svg',
                'is_active' => true,
                'is_default' => false,
            ],

            [
                'name' => 'Amharic',
                'locale' => 'am',
                'flag' => 'et.svg',
                'is_active' => true,
                'is_default' => false,
            ],
        ]);

        // Get default_language from settings table
        $defaultLocale = DB::table('settings')->value('default_language') ?? 'en';

        // Reset all is_default flags
        DB::table('languages')->update(['is_default' => false]);

        // Set is_default to true for the matching locale
        DB::table('languages')->where('locale', $defaultLocale)->update(['is_default' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
