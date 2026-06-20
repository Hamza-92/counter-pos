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
            $table->string('sale_prefix', 10)->nullable()->default('SL')->after('default_tax');
            $table->string('purchase_prefix', 10)->nullable()->default('PR')->after('sale_prefix');
            $table->string('quotation_prefix', 10)->nullable()->default('QT')->after('purchase_prefix');
            $table->string('adjustment_prefix', 10)->nullable()->default('AD')->after('quotation_prefix');
            $table->string('transfer_prefix', 10)->nullable()->default('TR')->after('adjustment_prefix');
            $table->string('sale_return_prefix', 10)->nullable()->default('RT')->after('transfer_prefix');
            $table->string('purchase_return_prefix', 10)->nullable()->default('RT')->after('sale_return_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sale_prefix', 'purchase_prefix', 'quotation_prefix', 'adjustment_prefix', 'transfer_prefix', 'sale_return_prefix', 'purchase_return_prefix']);
        });
    }
};
