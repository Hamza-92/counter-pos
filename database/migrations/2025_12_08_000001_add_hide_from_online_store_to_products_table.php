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
        Schema::table('products', function (Blueprint $table) {
            // Flag to hide a product from the public online store (still usable in POS)
            if (! Schema::hasColumn('products', 'hide_from_online_store')) {
                $table->boolean('hide_from_online_store')
                    ->default(false)
                    ->after('is_featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'hide_from_online_store')) {
                $table->dropColumn('hide_from_online_store');
            }
        });
    }
};













