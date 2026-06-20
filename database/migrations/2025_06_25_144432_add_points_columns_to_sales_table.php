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
        Schema::table('sales', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->float('used_points', 10, 2)->default(0)->after('notes');
            $table->float('earned_points', 10, 2)->default(0)->after('used_points');
            $table->float('discount_from_points', 10, 2)->default(0)->after('earned_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
};
