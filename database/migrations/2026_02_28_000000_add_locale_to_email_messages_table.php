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
        if (!Schema::hasColumn('email_messages', 'locale')) {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->string('locale', 10)->default('en')->after('name');
            });
            \DB::table('email_messages')->whereNull('locale')->update(['locale' => 'en']);
        }

        $indexExists = \DB::select("SHOW INDEX FROM email_messages WHERE Key_name = 'email_messages_name_locale_unique'");
        if (empty($indexExists)) {
            \DB::statement('ALTER TABLE email_messages ADD UNIQUE email_messages_name_locale_unique (name(100), locale)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = \DB::select("SHOW INDEX FROM email_messages WHERE Key_name = 'email_messages_name_locale_unique'");
        if (!empty($indexExists)) {
            \DB::statement('ALTER TABLE email_messages DROP INDEX email_messages_name_locale_unique');
        }
        if (Schema::hasColumn('email_messages', 'locale')) {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
