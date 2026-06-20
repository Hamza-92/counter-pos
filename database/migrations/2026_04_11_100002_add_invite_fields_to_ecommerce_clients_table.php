<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('invite_code_id')->nullable()->after('status');
            $table->unsignedBigInteger('referred_by')->nullable()->after('invite_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_clients', function (Blueprint $table) {
            $table->dropColumn(['invite_code_id', 'referred_by']);
        });
    }
};
