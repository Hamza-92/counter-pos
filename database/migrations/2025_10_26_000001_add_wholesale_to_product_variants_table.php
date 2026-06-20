<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'wholesale')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->float('wholesale', 10, 0)->nullable()->default(0)->after('price');
            });

        }
        // Initialize new column from price without altering existing price data
        if (Schema::hasColumn('product_variants', 'wholesale')) {
            DB::table('product_variants')->update([
                'wholesale' => DB::raw('price'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'wholesale')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('wholesale');
            });
        }
    }
};
