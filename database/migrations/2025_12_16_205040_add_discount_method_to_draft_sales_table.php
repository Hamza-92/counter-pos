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
        Schema::table('draft_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('draft_sales', 'discount_Method')) {
                $table->string('discount_Method', 10)->default('2')->after('discount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_sales', function (Blueprint $table) {
            if (Schema::hasColumn('draft_sales', 'discount_Method')) {
                $table->dropColumn('discount_Method');
            }
        });
    }
};
