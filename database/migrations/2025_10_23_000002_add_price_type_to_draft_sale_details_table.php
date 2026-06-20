<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceTypeToDraftSaleDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('draft_sale_details', function (Blueprint $table) {
            if (! Schema::hasColumn('draft_sale_details', 'price_type')) {
                $table->string('price_type', 32)->default('retail')->after('discount_method');
            }
        });
    }

    public function down()
    {
        Schema::table('draft_sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('draft_sale_details', 'price_type')) {
                $table->dropColumn('price_type');
            }
        });
    }
}
