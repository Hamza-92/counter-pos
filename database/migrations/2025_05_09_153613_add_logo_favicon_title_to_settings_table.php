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
        Schema::table('settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('app_name')->nullable()->after('quotation_with_stock')->default('Stocky | Ultimate Inventory With POS');
            $table->string('page_title_suffix')->nullable()->after('app_name')->default('Ultimate Inventory With POS');
            $table->string('favicon')->nullable()->after('logo')->default('favicon.ico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            //
        });
    }
};
