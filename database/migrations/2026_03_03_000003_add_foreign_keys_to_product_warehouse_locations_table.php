<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToProductWarehouseLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_warehouse_locations', function (Blueprint $table) {
            $table->foreign('product_id', 'pwl_product_fk')
                ->references('id')
                ->on('products')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');

            $table->foreign('warehouse_id', 'pwl_warehouse_fk')
                ->references('id')
                ->on('warehouses')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');

            $table->foreign('warehouse_location_id', 'pwl_location_fk')
                ->references('id')
                ->on('warehouse_locations')
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
        Schema::table('product_warehouse_locations', function (Blueprint $table) {
            $table->dropForeign('pwl_product_fk');
            $table->dropForeign('pwl_warehouse_fk');
            $table->dropForeign('pwl_location_fk');
        });
    }
}

