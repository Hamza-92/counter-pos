<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_sales')) {
            return;
        }

        // Safety: fail fast if duplicates already exist among active rows
        $duplicate = DB::table('payment_sales')
            ->select('Ref', DB::raw('COUNT(*) as c'))
            ->whereNull('deleted_at')
            ->groupBy('Ref')
            ->having('c', '>', 1)
            ->limit(1)
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                "Cannot add composite unique index on payment_sales (Ref, deleted_at); duplicate active Ref detected: '{$duplicate->Ref}'. " .
                'Please de-duplicate existing active payment_sales records before running this migration.'
            );
        }

        Schema::table('payment_sales', function (Blueprint $table) {
            // Drop old unique index on Ref (best-effort; name may differ)
            try {
                $table->dropUnique('payment_sales_ref_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique(['Ref']);
                } catch (\Throwable $e2) {
                    // ignore
                }
            }

            // Add composite unique key (Ref, deleted_at)
            // Allows creating a new payment if the previous one was soft-deleted.
            try {
                $table->unique(['Ref', 'deleted_at'], 'payment_sales_ref_deleted_at_unique');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_sales')) {
            return;
        }

        Schema::table('payment_sales', function (Blueprint $table) {
            try {
                $table->dropUnique('payment_sales_ref_deleted_at_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique(['Ref', 'deleted_at']);
                } catch (\Throwable $e2) {
                    // ignore
                }
            }

            // Re-add old unique on Ref (best-effort)
            try {
                $table->unique('Ref', 'payment_sales_ref_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};

