<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            // Add separate toggles for tax, discount, and shipping
            $table->boolean('show_tax')->default(1)->after('show_discount');
            $table->boolean('show_shipping')->default(1)->after('show_tax');
        });

        // Migrate existing show_discount value to all three new fields for backward compatibility
        DB::table('pos_settings')->update([
            'show_tax' => DB::raw('show_discount'),
            'show_shipping' => DB::raw('show_discount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['show_tax', 'show_shipping']);
        });
    }
};
