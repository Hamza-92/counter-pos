<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceFormatToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            // Store preferred invoice format for POS printing: 'thermal' (default) or 'a4'
            $table->string('invoice_format', 20)->after('invoice_footer')->default('thermal');
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
            if (Schema::hasColumn('settings', 'invoice_format')) {
                $table->dropColumn('invoice_format');
            }
        });
    }
}

