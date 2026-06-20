<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCashDrawerAutoOpenToPosSettingsTable extends Migration
{
    /**
     * Run the migrations.
     * Cash drawer is typically connected to the receipt printer RJ11/RJ12 port.
     * When enabled, an ESC/POS "kick" is sent via QZ Tray on cash payment completion.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->boolean('cash_drawer_auto_open')->default(false)->after('show_zatca_qr');
            $table->string('cash_drawer_printer_name', 192)->nullable()->after('cash_drawer_auto_open');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['cash_drawer_auto_open', 'cash_drawer_printer_name']);
        });
    }
}
