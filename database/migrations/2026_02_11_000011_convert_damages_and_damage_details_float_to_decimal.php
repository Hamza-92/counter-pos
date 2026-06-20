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
        Schema::table('damages', function (Blueprint $table) {
            $table->decimal('items', 15, 2)->nullable()->default(0)->change();
        });

        Schema::table('damage_details', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            $table->float('items', 10, 0)->nullable()->default(0)->change();
        });

        Schema::table('damage_details', function (Blueprint $table) {
            $table->float('quantity', 10, 0)->change();
        });
    }
};
