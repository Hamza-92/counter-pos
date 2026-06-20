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
        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('payment_method', 30)->default('cod')->after('total');
            $table->string('payment_status', 20)->default('pending')->after('payment_method');
            $table->string('stripe_payment_intent_id', 128)->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'stripe_payment_intent_id']);
        });
    }
};
