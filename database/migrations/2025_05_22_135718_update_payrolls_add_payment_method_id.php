<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add the new column
        Schema::table('payrolls', function (Blueprint $table) {
            $table->integer('payment_method_id')->nullable()->after('payment_method');
        });

        // Step 2: Update values based on payment_method
        $mapping = [
            'credit card' => 1,
            'Cash' => 2,
            'cheque' => 3,
            'tpe' => 4,
            'Western Union' => 5,
            'bank transfer' => 6,
            'other' => 7,
        ];

        foreach ($mapping as $payment_method => $id) {
            DB::table('payrolls')
                ->where('payment_method', $payment_method)
                ->update(['payment_method_id' => $id]);
        }

        // Step 3: Remove the old payment_method column
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        // Step 4: Add foreign key constraint
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('payment_method')->nullable();
            $table->dropColumn('payment_method_id');
        });
    }
};
