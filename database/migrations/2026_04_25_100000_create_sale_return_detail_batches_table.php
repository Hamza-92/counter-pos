<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleReturnDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sale_return_detail_batches')) {
            return;
        }

        Schema::create('sale_return_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('sale_return_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_price')->nullable();

            $table->timestamps(6);

            $table->index('sale_return_detail_id', 'srdb_sr_detail_idx');
            $table->index('product_batch_id', 'srdb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_return_detail_batches');
    }
}
