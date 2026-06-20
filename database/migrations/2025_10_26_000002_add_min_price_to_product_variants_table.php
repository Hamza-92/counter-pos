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
        if (! Schema::hasColumn('product_variants', 'min_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->float('min_price', 10, 0)->nullable()->default(0)->after('wholesale');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'min_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('min_price');
            });
        }
    }
};
