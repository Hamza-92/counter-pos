<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add the new column
        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->integer('payment_method_id')->nullable()->after('Reglement');
        });

        // Step 2: Update values based on Reglement
        $mapping = [
            'credit card' => 1,
            'Cash' => 2,
            'cheque' => 3,
            'tpe' => 4,
            'Western Union' => 5,
            'bank transfer' => 6,
            'other' => 7,
        ];

        foreach ($mapping as $reglement => $id) {
            DB::table('payment_purchase_returns')
                ->where('Reglement', $reglement)
                ->update(['payment_method_id' => $id]);
        }

        // Step 3: Remove the old Reglement column
        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->dropColumn('Reglement');
        });

        // Step 4: Add foreign key constraint
        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('payment_purchase_returns', function (Blueprint $table) {
            $table->string('Reglement')->nullable();
            $table->dropColumn('payment_method_id');
        });
    }
};
