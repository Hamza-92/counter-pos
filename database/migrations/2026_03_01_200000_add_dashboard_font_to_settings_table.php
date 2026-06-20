<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardFontToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $after = Schema::hasColumn('settings', 'dashboard_grid_layout')
                ? 'dashboard_grid_layout'
                : 'dashboard_section_order';
            if (!Schema::hasColumn('settings', 'dashboard_font_size')) {
                $table->string('dashboard_font_size', 20)->nullable()->after($after);
            }
            if (!Schema::hasColumn('settings', 'dashboard_font_family')) {
                $table->string('dashboard_font_family', 191)->nullable()->after('dashboard_font_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'dashboard_font_size')) {
                $table->dropColumn('dashboard_font_size');
            }
            if (Schema::hasColumn('settings', 'dashboard_font_family')) {
                $table->dropColumn('dashboard_font_family');
            }
        });
    }
}
