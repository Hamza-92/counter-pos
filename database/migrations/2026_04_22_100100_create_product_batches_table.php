<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductBatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('product_batches')) {
            return;
        }

        Schema::create('product_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedInteger('product_id');
            $table->unsignedInteger('product_variant_id')->nullable();
            $table->unsignedInteger('warehouse_id');

            $table->string('batch_no', 100);
            $table->date('expiry_date')->nullable();
            $table->date('mfg_date')->nullable();

            $table->double('qty')->default(0);
            $table->double('unit_cost')->nullable();

            $table->unsignedInteger('provider_id')->nullable();
            $table->unsignedBigInteger('source_purchase_id')->nullable();

            // active | quarantined | expired | written_off
            $table->string('status', 20)->default('active');

            $table->string('barcode', 191)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps(6);
            $table->softDeletes();

            $table->index(['product_id', 'warehouse_id'], 'pb_product_warehouse_idx');
            $table->index(['product_id', 'expiry_date'], 'pb_product_expiry_idx');
            $table->index(['warehouse_id', 'expiry_date'], 'pb_warehouse_expiry_idx');
            $table->index('status', 'pb_status_idx');
            $table->index('batch_no', 'pb_batch_no_idx');
            $table->unique(
                ['product_id', 'product_variant_id', 'warehouse_id', 'batch_no'],
                'pb_product_variant_warehouse_batch_uq'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_batches');
    }
}
