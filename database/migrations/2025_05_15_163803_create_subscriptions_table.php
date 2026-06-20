<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->date('date');
            $table->integer('user_id')->index('user_id');
            $table->integer('client_id')->index('client_id');
            $table->integer('product_id')->index('product_id');
            $table->integer('warehouse_id')->index('warehouse_id');
            $table->string('cycle_type', 192);
            $table->integer('total_cycles');
            $table->string('billing_cycle', 50);
            $table->integer('remaining_cycles');
            $table->double('price_per_cycle');
            $table->double('price_per_unit');
            $table->double('quantity');
            $table->date('next_billing_date');
            $table->string('status', 192);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};
