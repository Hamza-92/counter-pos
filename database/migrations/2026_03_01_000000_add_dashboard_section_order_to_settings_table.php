<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardSectionOrderToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            
            if (!Schema::hasColumn('settings', 'dashboard_section_order')) {
                $table->text('dashboard_section_order')->nullable()->after('default_dashboard_date_range');
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
            if (Schema::hasColumn('settings', 'dashboard_section_order')) {
                $table->dropColumn('dashboard_section_order');
            }
          
        });
    }
}
