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
        Schema::table('payment_purchases', function (Blueprint $table) {
            $table->decimal('montant', 15, 2)->change();
            $table->decimal('change', 15, 2)->default(0)->change();
        });

        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->decimal('montant', 15, 2)->change();
            $table->decimal('change', 15, 2)->default(0)->change();
        });

        Schema::table('payment_sales', function (Blueprint $table) {
            $table->decimal('montant', 15, 2)->change();
            $table->decimal('change', 15, 2)->default(0)->change();
        });

        Schema::table('payment_sale_returns', function (Blueprint $table) {
            $table->decimal('montant', 15, 2)->change();
            $table->decimal('change', 15, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_purchases', function (Blueprint $table) {
            $table->float('montant', 10, 0)->change();
            $table->float('change', 10, 0)->default(0)->change();
        });

        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->float('montant', 10, 0)->change();
            $table->float('change', 10, 0)->default(0)->change();
        });

        Schema::table('payment_sales', function (Blueprint $table) {
            $table->float('montant', 10, 0)->change();
            $table->float('change', 10, 0)->default(0)->change();
        });

        Schema::table('payment_sale_returns', function (Blueprint $table) {
            $table->float('montant', 10, 0)->change();
            $table->float('change', 10, 0)->default(0)->change();
        });
    }
};
