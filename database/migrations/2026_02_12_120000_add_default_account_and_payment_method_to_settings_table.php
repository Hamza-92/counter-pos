<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultAccountAndPaymentMethodToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'default_account_id')) {
                $table->unsignedBigInteger('default_account_id')->nullable()->after('warehouse_id');
            }
            if (! Schema::hasColumn('settings', 'default_payment_method_id')) {
                $table->unsignedBigInteger('default_payment_method_id')->nullable()->after('default_account_id');
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
            if (Schema::hasColumn('settings', 'default_account_id')) {
                $table->dropColumn('default_account_id');
            }
            if (Schema::hasColumn('settings', 'default_payment_method_id')) {
                $table->dropColumn('default_payment_method_id');
            }
        });
    }
}
