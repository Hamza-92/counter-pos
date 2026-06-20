<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseReturnDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('purchase_return_detail_batches')) {
            return;
        }

        Schema::create('purchase_return_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('purchase_return_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->timestamps(6);

            $table->index('purchase_return_detail_id', 'prdb_pr_detail_idx');
            $table->index('product_batch_id', 'prdb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_return_detail_batches');
    }
}
