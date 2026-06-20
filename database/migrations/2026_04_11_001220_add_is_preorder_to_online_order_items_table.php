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
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->boolean('is_preorder')->default(false)->after('tax_method');
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->boolean('has_preorder_items')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->dropColumn('is_preorder');
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn('has_preorder_items');
        });
    }
};
