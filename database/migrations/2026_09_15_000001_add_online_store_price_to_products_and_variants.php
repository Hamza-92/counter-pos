<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'online_store_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('online_store_price', 15, 2)
                    ->nullable()
                    ->after('price');
            });
        }

        if (Schema::hasTable('product_variants') && ! Schema::hasColumn('product_variants', 'online_store_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('online_store_price', 15, 2)
                    ->nullable()
                    ->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'online_store_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('online_store_price');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'online_store_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('online_store_price');
            });
        }
    }
};
