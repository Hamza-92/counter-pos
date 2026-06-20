<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubcategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('subcategories')) {
            Schema::create('subcategories', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                // Match existing `categories` / `products` PK style (INT)
                $table->integer('id', true);

                // Parent category (same INT type as categories.id)
                $table->integer('category_id');

                $table->string('name', 191);
                $table->text('description')->nullable();

                // Simple status flag (1 = active, 0 = inactive)
                $table->boolean('status')->default(true);

                $table->timestamps();

                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->onUpdate('RESTRICT')
                    ->onDelete('RESTRICT');

                // Ensure subcategory name is unique within a category
                $table->unique(['category_id', 'name'], 'subcategories_cat_name_unique');
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
        if (Schema::hasTable('subcategories')) {
            Schema::drop('subcategories');
        }
    }
}


