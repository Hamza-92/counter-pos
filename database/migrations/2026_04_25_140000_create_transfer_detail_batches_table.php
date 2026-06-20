<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('transfer_detail_batches')) {
            return;
        }

        Schema::create('transfer_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('transfer_detail_id');
            // The source batch (debited) and the destination batch (credited).
            // dest_batch_id is nullable for transfers in 'sent' status — the destination
            // batch is created/credited only on completion.
            $table->unsignedBigInteger('source_batch_id');
            $table->unsignedBigInteger('dest_batch_id')->nullable();

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->timestamps(6);

            $table->index('transfer_detail_id', 'tdb_transfer_detail_idx');
            $table->index('source_batch_id', 'tdb_source_batch_idx');
            $table->index('dest_batch_id', 'tdb_dest_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transfer_detail_batches');
    }
}
