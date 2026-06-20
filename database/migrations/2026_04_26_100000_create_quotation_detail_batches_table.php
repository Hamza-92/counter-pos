<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotationDetailBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('quotation_detail_batches')) {
            return;
        }

        Schema::create('quotation_detail_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('quotation_detail_id');
            $table->unsignedBigInteger('product_batch_id');

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->timestamps(6);

            $table->index('quotation_detail_id', 'qdb_quotation_detail_idx');
            $table->index('product_batch_id', 'qdb_product_batch_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_detail_batches');
    }
}
