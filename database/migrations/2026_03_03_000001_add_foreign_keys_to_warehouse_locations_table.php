<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToWarehouseLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->foreign('warehouse_id', 'warehouse_locations_wh_fk')
                ->references('id')
                ->on('warehouses')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->dropForeign('warehouse_locations_wh_fk');
        });
    }
}

