<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleUuidToSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('sales', 'sale_uuid')) {
            Schema::table('sales', function (Blueprint $table) {
                // UUID generated on the frontend (crypto.randomUUID) to make
                // POS sale creation idempotent across:
                //  - direct online POS requests
                //  - offline queue saves
                //  - offline sync retries
                $table->uuid('sale_uuid')->nullable()->unique()->after('id');
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
        if (Schema::hasColumn('sales', 'sale_uuid')) {
            Schema::table('sales', function (Blueprint $table) {
                // Drop unique index then column (index name is inferred)
                try {
                    $table->dropUnique(['sale_uuid']);
                } catch (\Throwable $e) {
                    // Index might already be missing; ignore to keep rollback resilient
                }

                $table->dropColumn('sale_uuid');
            });
        }
    }
}


