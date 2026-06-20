<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWholesaleAndMinPriceToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Add new columns (nullable first to avoid errors)
            $table->float('wholesale_price', 10, 0)->after('price');
            $table->float('min_price', 10, 0)->after('wholesale_price');
        });

        // Copy existing price values into the new columns
        DB::table('products')->update([
            'wholesale_price' => DB::raw('price'),
        ]);
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'min_price']);
        });
    }
}
