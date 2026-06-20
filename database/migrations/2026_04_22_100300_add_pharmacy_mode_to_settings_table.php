<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPharmacyModeToSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'pharmacy_mode')) {
                $table->boolean('pharmacy_mode')->default(false);
            }
            if (! Schema::hasColumn('settings', 'expiry_warning_days')) {
                $table->integer('expiry_warning_days')->default(90);
            }
            if (! Schema::hasColumn('settings', 'block_expired_sale')) {
                $table->boolean('block_expired_sale')->default(false);
            }
            if (! Schema::hasColumn('settings', 'print_expiry_on_receipt')) {
                $table->boolean('print_expiry_on_receipt')->default(false);
            }
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $cols = ['pharmacy_mode', 'expiry_warning_days', 'block_expired_sale', 'print_expiry_on_receipt'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
