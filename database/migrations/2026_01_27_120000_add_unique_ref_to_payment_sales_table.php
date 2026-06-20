<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safety: fail fast if duplicates already exist
        $duplicate = DB::table('payment_sales')
            ->select('Ref', DB::raw('COUNT(*) as c'))
            ->groupBy('Ref')
            ->having('c', '>', 1)
            ->limit(1)
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                "Cannot add unique index on payment_sales.Ref; duplicate Ref detected: '{$duplicate->Ref}'. " .
                'Please de-duplicate existing payment_sales records before running this migration.'
            );
        }

        Schema::table('payment_sales', function (Blueprint $table) {
            $table->unique('Ref', 'payment_sales_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_sales', function (Blueprint $table) {
            $table->dropUnique('payment_sales_ref_unique');
        });
    }
};

