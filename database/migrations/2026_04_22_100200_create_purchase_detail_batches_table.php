<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('purchase_detail_batches')) {
            return;
        }

        Schema::create('purchase_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('purchase_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->timestamps(6);

            $table->index('purchase_detail_id', 'pdb_purchase_detail_idx');
            $table->index('product_batch_id', 'pdb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_detail_batches');
    }
}
