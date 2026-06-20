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
        Schema::table('client_opening_balance_payments', function (Blueprint $table) {
            $table->foreign('client_id', 'client_id_opening_balance_payments')->references('id')->on('clients')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('user_id', 'user_id_opening_balance_payments')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('payment_method_id', 'payment_method_id_opening_balance_payments')->references('id')->on('payment_methods')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('account_id', 'account_id_opening_balance_payments')->references('id')->on('accounts')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_opening_balance_payments', function (Blueprint $table) {
            $table->dropForeign('client_id_opening_balance_payments');
            $table->dropForeign('user_id_opening_balance_payments');
            $table->dropForeign('payment_method_id_opening_balance_payments');
            $table->dropForeign('account_id_opening_balance_payments');
        });
    }
};