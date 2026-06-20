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
            if (! Schema::hasColumn('sales', 'quickbooks_invoice_id')) {
                $table->string('quickbooks_invoice_id')
                    ->nullable()
                    ->index()
                    ->after('discount_from_points');
            }

            if (! Schema::hasColumn('sales', 'quickbooks_realm_id')) {
                $table->string('quickbooks_realm_id')
                    ->nullable()
                    ->index()
                    ->after('quickbooks_invoice_id');
            }

            if (! Schema::hasColumn('sales', 'quickbooks_synced_at')) {
                $table->timestamp('quickbooks_synced_at')
                    ->nullable()
                    ->after('quickbooks_realm_id');
            }

            if (! Schema::hasColumn('sales', 'quickbooks_sync_error')) {
                $table->string('quickbooks_sync_error', 2048)
                    ->nullable()
                    ->after('quickbooks_synced_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'quickbooks_invoice_id',
                'quickbooks_realm_id',
                'quickbooks_synced_at',
                'quickbooks_sync_error',
            ]);
        });
    }
};
