<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_order_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);

            $table->integer('order_id');     // references online_orders.id
            $table->integer('product_id')->nullable(); // optional
            $table->integer('product_variant_id')->nullable();
            $table->float('TaxNet', 10, 0)->nullable();
            $table->string('tax_method', 192)->nullable()->default('1');
            $table->float('discount', 10, 0)->nullable();
            $table->string('discount_method', 192)->nullable()->default('1');

            $table->decimal('qty', 12, 3)->default(1);   // supports fractional qty if needed
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->timestamps();

            // FKs
            $table->foreign('order_id')
                ->references('id')->on('online_orders')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->nullOnDelete();

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variants')
                ->nullOnDelete();

            $table->index(['order_id']);
            $table->index(['product_id']);
            $table->index(['product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_items');
    }
};
