<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sale_detail_batches')) {
            return;
        }

        Schema::create('sale_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('sale_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_price')->nullable();

            $table->timestamps(6);

            $table->index('sale_detail_id', 'sdb_sale_detail_idx');
            $table->index('product_batch_id', 'sdb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_detail_batches');
    }
}
