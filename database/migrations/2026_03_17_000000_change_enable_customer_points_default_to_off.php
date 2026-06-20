<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pos_settings')->update(['enable_customer_points' => 0]);

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->boolean('enable_customer_points')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->boolean('enable_customer_points')->default(1)->change();
        });
    }
};
