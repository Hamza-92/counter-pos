<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubCategoryIdToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'sub_category_id')) {
            Schema::table('products', function (Blueprint $table) {
                // Align with existing integer columns (category_id, brand_id, etc.)
                $table->integer('sub_category_id')->nullable()->after('category_id');
                $table->index('sub_category_id', 'sub_category_id');

                // Foreign key to subcategories table
                $table->foreign('sub_category_id', 'products_sub_category_id_foreign')
                    ->references('id')
                    ->on('subcategories')
                    ->onUpdate('RESTRICT')
                    ->onDelete('RESTRICT');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sub_category_id')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop FK and index before dropping the column
                if (Schema::hasColumn('products', 'sub_category_id')) {
                    // Named explicitly in up()
                    $table->dropForeign('products_sub_category_id_foreign');
                    $table->dropIndex('sub_category_id');
                    $table->dropColumn('sub_category_id');
                }
            });
        }
    }
}


