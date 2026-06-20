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
            if (!Schema::hasColumn('sales', 'woocommerce_order_id')) {
                $table->unsignedBigInteger('woocommerce_order_id')->nullable()->after('sale_uuid');
            }
            if (!Schema::hasColumn('sales', 'woocommerce_order_number')) {
                $table->string('woocommerce_order_number', 64)->nullable()->after('woocommerce_order_id');
            }
            if (!Schema::hasColumn('sales', 'woocommerce_order_status')) {
                $table->string('woocommerce_order_status', 32)->nullable()->after('woocommerce_order_number');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            // Drop unique index then columns (best-effort; index name may differ)
            if (Schema::hasColumn('sales', 'woocommerce_order_id')) {
                $table->dropColumn('woocommerce_order_id');
            }
            if (Schema::hasColumn('sales', 'woocommerce_order_number')) {
                $table->dropColumn('woocommerce_order_number');
            }
            if (Schema::hasColumn('sales', 'woocommerce_order_status')) {
                $table->dropColumn('woocommerce_order_status');
            }
        });
    }
};

