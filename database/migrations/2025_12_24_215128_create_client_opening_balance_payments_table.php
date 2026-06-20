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
        Schema::create('client_opening_balance_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('client_id')->index('client_id_opening_balance_payments');
            $table->integer('user_id')->index('user_id_opening_balance_payments');
            $table->date('date');
            $table->string('Ref', 192);
            $table->decimal('montant', 15, 2);
            $table->decimal('change', 15, 2)->default(0);
            $table->integer('payment_method_id')->nullable()->index('payment_method_id_opening_balance_payments');
            $table->integer('account_id')->nullable()->index('account_id_opening_balance_payments');
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_opening_balance_payments');
    }
};
