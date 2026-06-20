<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWoocommerceVariationIdToProductVariantsTable extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'woocommerce_variation_id')) {
                $table->unsignedBigInteger('woocommerce_variation_id')->nullable()->index('pv_woocommerce_variation_id_idx');
            }
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'woocommerce_variation_id')) {
                $table->dropIndex('pv_woocommerce_variation_id_idx');
                $table->dropColumn('woocommerce_variation_id');
            }
        });
    }
}

