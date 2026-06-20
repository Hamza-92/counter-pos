<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdjustmentDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('adjustment_detail_batches')) {
            return;
        }

        Schema::create('adjustment_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('adjustment_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            // 'in' = qty added back to the batch (adjustment type "add"),
            // 'out' = qty subtracted from the batch (adjustment type "sub").
            // Stored explicitly so reverseForAdjustmentDetails() doesn't need to
            // look up the parent's type when un-doing the movement.
            $table->enum('direction', ['in', 'out']);

            $table->double('qty')->default(0);

            $table->timestamps(6);

            $table->index('adjustment_detail_id', 'adb_adj_detail_idx');
            $table->index('product_batch_id', 'adb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('adjustment_detail_batches');
    }
}
