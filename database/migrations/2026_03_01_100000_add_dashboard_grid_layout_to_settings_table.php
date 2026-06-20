<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardGridLayoutToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'dashboard_grid_layout')) {
                $after = Schema::hasColumn('settings', 'dashboard_section_order')
                    ? 'dashboard_section_order'
                    : 'default_dashboard_date_range';
                $table->text('dashboard_grid_layout')->nullable()->after($after);
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
            if (Schema::hasColumn('settings', 'dashboard_grid_layout')) {
                $table->dropColumn('dashboard_grid_layout');
            }
        });
    }
}
