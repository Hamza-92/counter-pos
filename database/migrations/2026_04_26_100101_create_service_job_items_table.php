<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_job_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_job_id');
            $table->string('type', 20)->default('part'); // part | labor | other
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable(); // for stock decrement
            $table->string('description', 191);
            $table->float('quantity', 10, 0)->default(1);
            $table->float('unit_price', 10, 0)->default(0);
            $table->float('discount', 10, 0)->default(0);
            $table->string('discount_method', 10)->default('1'); // 1=fixed, 2=percent
            $table->float('tax_rate', 10, 0)->default(0);
            $table->string('tax_method', 10)->default('1'); // 1=exclusive, 2=inclusive
            $table->float('total', 10, 0)->default(0);
            $table->boolean('stock_deducted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index('service_job_id');
            $table->index('product_id');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_job_items');
    }
};
