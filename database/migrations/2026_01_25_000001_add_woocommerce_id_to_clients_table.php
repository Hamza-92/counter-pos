<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || Schema::hasColumn('clients', 'woocommerce_id')) {
            return;
        }
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('woocommerce_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'woocommerce_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('woocommerce_id');
            });
        }
    }
};
