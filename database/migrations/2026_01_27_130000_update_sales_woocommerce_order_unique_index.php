<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {

            // Add composite unique index
            $table->unique(
                ['woocommerce_order_id', 'deleted_at'],
                'sales_woo_order_id_deleted_at_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            // Drop composite unique
            $table->dropUnique('sales_woo_order_id_deleted_at_unique');

        });
    }
};

