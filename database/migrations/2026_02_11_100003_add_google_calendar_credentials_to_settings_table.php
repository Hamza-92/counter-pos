<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('google_calendar_client_id', 255)->nullable()->after('google_calendar_calendar_id');
            $table->text('google_calendar_client_secret')->nullable()->after('google_calendar_client_id');
            $table->string('google_calendar_redirect_uri', 512)->nullable()->after('google_calendar_client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_client_id',
                'google_calendar_client_secret',
                'google_calendar_redirect_uri',
            ]);
        });
    }
};
