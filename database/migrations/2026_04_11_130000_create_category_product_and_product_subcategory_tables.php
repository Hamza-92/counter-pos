<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCategoryProductAndProductSubcategoryTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->integer('product_id');
                $table->integer('category_id');
                $table->timestamps();

                $table->unique(['product_id', 'category_id'], 'category_product_product_category_unique');

                $table->foreign('product_id', 'category_product_product_id_foreign')
                    ->references('id')
                    ->on('products')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');

                $table->foreign('category_id', 'category_product_category_id_foreign')
                    ->references('id')
                    ->on('categories')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');
            });
        }

        if (! Schema::hasTable('product_subcategory')) {
            Schema::create('product_subcategory', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->integer('product_id');
                $table->integer('sub_category_id');
                $table->timestamps();

                $table->unique(['product_id', 'sub_category_id'], 'product_subcategory_product_sub_unique');

                $table->foreign('product_id', 'product_subcategory_product_id_foreign')
                    ->references('id')
                    ->on('products')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');

                $table->foreign('sub_category_id', 'product_subcategory_sub_category_id_foreign')
                    ->references('id')
                    ->on('subcategories')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');
            });
        }

        $this->migrateLegacyCategoryAndSubcategoryLinks();
    }

    /**
     * Seed pivot tables from legacy products.category_id and products.sub_category_id.
     */
    protected function migrateLegacyCategoryAndSubcategoryLinks(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $now = now();

        if (Schema::hasTable('category_product') && Schema::hasTable('categories')) {
            $this->backfillCategoryProduct($now);
        }

        if (Schema::hasTable('product_subcategory') && Schema::hasTable('subcategories')) {
            $this->backfillProductSubcategory($now);
        }
    }

    protected function backfillCategoryProduct($now): void
    {
        $catQuery = DB::table('categories')->where('id', '>', 0);
        if (Schema::hasColumn('categories', 'deleted_at')) {
            $catQuery->whereNull('deleted_at');
        }
        $validCategoryIds = array_flip($catQuery->pluck('id')->all());

        $prodQuery = DB::table('products')
            ->select('id', 'category_id')
            ->whereNotNull('category_id')
            ->where('category_id', '>', 0)
            ->orderBy('id');

        if (Schema::hasColumn('products', 'deleted_at')) {
            $prodQuery->whereNull('deleted_at');
        }

        $prodQuery->chunkById(500, function ($rows) use ($validCategoryIds, $now) {
            foreach ($rows as $product) {
                if (! isset($validCategoryIds[(int) $product->category_id])) {
                    continue;
                }

                DB::table('category_product')->insertOrIgnore([
                    'product_id' => (int) $product->id,
                    'category_id' => (int) $product->category_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    protected function backfillProductSubcategory($now): void
    {
        if (! Schema::hasColumn('products', 'sub_category_id')) {
            return;
        }

        $subQuery = DB::table('subcategories')->where('id', '>', 0);
        if (Schema::hasColumn('subcategories', 'deleted_at')) {
            $subQuery->whereNull('deleted_at');
        }
        $validSubIds = array_flip($subQuery->pluck('id')->all());

        $prodQuery = DB::table('products')
            ->select('id', 'sub_category_id')
            ->whereNotNull('sub_category_id')
            ->where('sub_category_id', '>', 0)
            ->orderBy('id');

        if (Schema::hasColumn('products', 'deleted_at')) {
            $prodQuery->whereNull('deleted_at');
        }

        $prodQuery->chunkById(500, function ($rows) use ($validSubIds, $now) {
            foreach ($rows as $product) {
                if (! isset($validSubIds[(int) $product->sub_category_id])) {
                    continue;
                }

                DB::table('product_subcategory')->insertOrIgnore([
                    'product_id' => (int) $product->id,
                    'sub_category_id' => (int) $product->sub_category_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down()
    {
        if (Schema::hasTable('product_subcategory')) {
            Schema::drop('product_subcategory');
        }
        if (Schema::hasTable('category_product')) {
            Schema::drop('category_product');
        }
    }
}
