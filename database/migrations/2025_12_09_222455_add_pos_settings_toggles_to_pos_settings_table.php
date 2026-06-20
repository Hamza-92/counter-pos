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
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->boolean('quick_add_customer')->default(1)->after('products_per_page');
            $table->boolean('barcode_scanning_sound')->default(1)->after('quick_add_customer');
            $table->boolean('show_product_images')->default(1)->after('barcode_scanning_sound');
            $table->boolean('show_stock_quantity')->default(1)->after('show_product_images');
            $table->boolean('enable_hold_sales')->default(1)->after('show_stock_quantity');
            $table->boolean('enable_customer_points')->default(1)->after('enable_hold_sales');
            $table->boolean('show_categories')->default(1)->after('enable_customer_points');
            $table->boolean('show_brands')->default(1)->after('show_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'quick_add_customer', 
                'barcode_scanning_sound', 
                'show_product_images',
                'show_stock_quantity',
                'enable_hold_sales',
                'enable_customer_points',
                'show_categories',
                'show_brands'
            ]);
        });
    }
};
