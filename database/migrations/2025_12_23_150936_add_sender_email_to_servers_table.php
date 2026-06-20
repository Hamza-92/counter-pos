<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('sender_email', 191)->nullable()->after('sender_name');
        });

        // Copy email from settings table to sender_email in servers table
        $settings = DB::table('settings')->where('deleted_at', null)->first();
        if ($settings && $settings->email) {
            DB::table('servers')
                ->where('deleted_at', null)
                ->update(['sender_email' => $settings->email]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('sender_email');
        });
    }
};
