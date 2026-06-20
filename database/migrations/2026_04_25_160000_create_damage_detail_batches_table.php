<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDamageDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('damage_detail_batches')) {
            return;
        }

        Schema::create('damage_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('damage_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->timestamps(6);

            $table->index('damage_detail_id', 'ddb_damage_detail_idx');
            $table->index('product_batch_id', 'ddb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('damage_detail_batches');
    }
}
