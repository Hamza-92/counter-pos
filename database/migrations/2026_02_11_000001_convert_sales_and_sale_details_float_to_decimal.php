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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tax_rate', 15, 2)->nullable()->default(0)->change();
            $table->decimal('TaxNet', 15, 2)->nullable()->default(0)->change();
            $table->decimal('discount', 15, 2)->nullable()->default(0)->change();
            $table->decimal('shipping', 15, 2)->nullable()->default(0)->change();
            $table->decimal('GrandTotal', 15, 2)->default(0)->change();
            $table->decimal('paid_amount', 15, 2)->default(0)->change();
            $table->decimal('used_points', 15, 2)->default(0)->change();
            $table->decimal('earned_points', 15, 2)->default(0)->change();
            $table->decimal('discount_from_points', 15, 2)->default(0)->change();
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->change();
            $table->decimal('TaxNet', 15, 2)->nullable()->change();
            $table->decimal('discount', 15, 2)->nullable()->change();
            $table->decimal('total', 15, 2)->change();
            $table->decimal('quantity', 12, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->float('tax_rate', 10, 0)->nullable()->default(0)->change();
            $table->float('TaxNet', 10, 0)->nullable()->default(0)->change();
            $table->float('discount', 10, 0)->nullable()->default(0)->change();
            $table->float('shipping', 10, 0)->nullable()->default(0)->change();
            $table->float('GrandTotal', 10, 0)->default(0)->change();
            $table->float('paid_amount', 10, 0)->default(0)->change();
            $table->float('used_points', 10, 2)->default(0)->change();
            $table->float('earned_points', 10, 2)->default(0)->change();
            $table->float('discount_from_points', 10, 2)->default(0)->change();
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->float('price', 10, 0)->change();
            $table->float('TaxNet', 10, 0)->nullable()->change();
            $table->float('discount', 10, 0)->nullable()->change();
            $table->float('total', 10, 0)->change();
            $table->float('quantity', 10, 0)->change();
        });
    }
};
