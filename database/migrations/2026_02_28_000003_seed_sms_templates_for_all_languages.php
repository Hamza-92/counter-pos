<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Copy English SMS templates to all other languages as default so users can edit them.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sms_messages', 'locale')) {
            return;
        }

        $locales = [];
        if (Schema::hasTable('languages')) {
            $locales = DB::table('languages')->pluck('locale')->unique()->values()->all();
        }
        if (empty($locales)) {
            return;
        }

        $englishTemplates = DB::table('sms_messages')
            ->where('locale', 'en')
            ->whereNull('deleted_at')
            ->get(['name', 'text']);

        if ($englishTemplates->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($locales as $locale) {
            if ($locale === 'en') {
                continue;
            }
            foreach ($englishTemplates as $row) {
                $exists = DB::table('sms_messages')
                    ->where('name', $row->name)
                    ->where('locale', $locale)
                    ->whereNull('deleted_at')
                    ->exists();
                if (!$exists) {
                    DB::table('sms_messages')->insert([
                        'name' => $row->name,
                        'locale' => $locale,
                        'text' => $row->text,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('sms_messages', 'locale') || !Schema::hasTable('languages')) {
            return;
        }

        $otherLocales = DB::table('languages')->where('locale', '!=', 'en')->pluck('locale')->all();
        if (!empty($otherLocales)) {
            DB::table('sms_messages')->whereIn('locale', $otherLocales)->delete();
        }
    }
};
