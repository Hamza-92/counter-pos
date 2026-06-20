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
        Schema::table('collection_product', function (Blueprint $table) {
            $table->foreign(['collection_id'])->references(['id'])->on('collections')->onDelete('CASCADE');
            $table->foreign(['product_id'], 'collection_product_product_id')->references(['id'])->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('collection_product', function (Blueprint $table) {
            $table->dropForeign('collection_product_collection_id_foreign');
            $table->dropForeign('collection_product_product_id');
        });
    }
};
