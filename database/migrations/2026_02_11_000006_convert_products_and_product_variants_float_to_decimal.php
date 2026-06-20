<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('TaxNet', 15, 2)->nullable()->default(0)->change();
            $table->decimal('stock_alert', 15, 2)->nullable()->default(0)->change();
            $table->decimal('weight', 15, 2)->nullable()->change();
            $table->decimal('points', 15, 2)->default(0)->change();
            $table->decimal('discount', 15, 2)->nullable()->change();
            $table->decimal('wholesale_price', 15, 2)->change();
            $table->decimal('min_price', 15, 2)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('wholesale', 15, 2)->nullable()->default(0)->change();
            $table->decimal('min_price', 15, 2)->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->float('cost', 10, 0)->change();
            $table->float('price', 10, 0)->change();
            $table->float('TaxNet', 10, 0)->nullable()->default(0)->change();
            $table->float('stock_alert', 10, 0)->nullable()->default(0)->change();
            $table->float('weight', 10, 2)->nullable()->change();
            $table->float('points', 10, 2)->default(0)->change();
            $table->float('discount', 10, 0)->nullable()->change();
            $table->float('wholesale_price', 10, 0)->change();
            $table->float('min_price', 10, 0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->float('cost', 10, 0)->change();
            $table->float('price', 10, 0)->change();
            $table->float('wholesale', 10, 0)->nullable()->default(0)->change();
            $table->float('min_price', 10, 0)->nullable()->default(0)->change();
        });
    }
};
