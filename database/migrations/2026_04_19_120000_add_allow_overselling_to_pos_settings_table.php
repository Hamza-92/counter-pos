<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the `allow_overselling` toggle to the pos_settings table.
     * Default value is 0 (OFF) so existing installations preserve their
     * current strict stock-check behavior after upgrade.
     */
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'allow_overselling')) {
                $table->boolean('allow_overselling')
                    ->default(0)
                    ->after('enable_customer_points');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'allow_overselling')) {
                $table->dropColumn('allow_overselling');
            }
        });
    }
};
