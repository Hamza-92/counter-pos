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
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            // Header toggles
            $table->boolean('show_logo')->default(1)->after('note_customer');
            $table->boolean('show_store_name')->default(1)->after('show_logo');
            $table->boolean('show_reference')->default(1)->after('show_store_name');
            $table->boolean('show_date')->default(1)->after('show_reference');
            $table->boolean('show_seller')->default(1)->after('show_date');

            // Totals / payments toggles
            $table->boolean('show_paid')->default(1)->after('show_discount');
            $table->boolean('show_due')->default(1)->after('show_paid');
            $table->boolean('show_payments')->default(1)->after('show_due');

            // ZATCA QR toggle (works in addition to global zatca_enabled)
            $table->boolean('show_zatca_qr')->default(1)->after('show_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'show_reference',
                'show_date',
                'show_seller',
                'show_paid',
                'show_due',
                'show_payments',
                'show_zatca_qr',
            ]);
        });
    }
};






