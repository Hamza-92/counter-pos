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
        Schema::table('count_stock', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('category_id')->index('count_stock_category_id')->nullable()->after('warehouse_id');
            $table->foreign('category_id', 'count_stock_category_id')->references('id')->on('categories')->onUpdate('RESTRICT')->onDelete('RESTRICT');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('count_stock', function (Blueprint $table) {
            //
        });
    }
};
