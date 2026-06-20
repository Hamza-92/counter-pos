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
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            // Add logo_size field (in pixels, default 60)
            $table->integer('logo_size')->default(60)->after('show_logo');
        });

        // Set default logo_size for existing records
        DB::table('pos_settings')->update(['logo_size' => 60]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn('logo_size');
        });
    }
};
