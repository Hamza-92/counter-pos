<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'hide_site_name')) {
                $table->boolean('hide_site_name')
                    ->default(false)
                    ->after('customize_button_visible');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'hide_site_name')) {
                $table->dropColumn('hide_site_name');
            }
        });
    }
};
