<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign(['warehouse_id'], 'sub_warehouse_id')->references(['id'])->on('warehouses');
            $table->foreign(['user_id'], 'sub_user_id')->references(['id'])->on('users');
            $table->foreign(['product_id'], 'sub_product_id')->references(['id'])->on('products');
            $table->foreign(['client_id'], 'sub_client_id')->references(['id'])->on('clients');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign('sub_warehouse_id');
            $table->dropForeign('sub_user_id');
            $table->dropForeign('sub_product_id');
            $table->dropForeign('sub_client_id');
        });
    }
};
