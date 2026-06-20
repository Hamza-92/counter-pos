<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        // Disable query logging for speed & memory
        DB::disableQueryLog();

        $path = database_path('seeders/translations');
        $files = File::files($path);

        $allTranslations = [];

        foreach ($files as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME); // 'en', 'ar', etc.
            $translations = require $file;

            foreach ($translations as $key => $value) {
                $allTranslations[] = [
                    'locale' => $locale,
                    'key' => $key,
                    'value' => $value,
                    'is_default' => $locale === 'en' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($allTranslations, 1000) as $chunk) {
            DB::table('translations')->upsert(
                $chunk,
                ['locale', 'key'],
                ['value', 'is_default', 'updated_at']
            );
        }
    }
}
