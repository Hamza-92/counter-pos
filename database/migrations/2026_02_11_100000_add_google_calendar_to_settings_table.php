<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('google_calendar_refresh_token')->nullable()->after('backup_dropbox_access_token');
            $table->string('google_calendar_calendar_id', 255)->nullable()->after('google_calendar_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_refresh_token', 'google_calendar_calendar_id']);
        });
    }
};
