<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddApprovalStatusToTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            // New explicit approval column.
            // Keep it nullable during the data backfill to avoid issues on large tables.
            $table->string('approval_status', 50)->nullable()->after('statut');
        });

        // OLD TRANSFERS SAFETY:
        // All existing transfers created before this feature should behave exactly
        // like they did before – i.e. as already "approved".
        DB::table('transfers')
            ->whereNull('approval_status')
            ->update(['approval_status' => 'approved']);

        // For NEW rows, the business default is "pending".
        // We enforce this at the DB level after backfilling existing data.
        // Note: This uses MySQL syntax and assumes the default Stocky stack.
        try {
            DB::statement("ALTER TABLE transfers MODIFY COLUMN approval_status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // If the ALTER fails for any reason (e.g. different DB engine),
            // we still keep the column and rely on application-level defaulting.
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
}

















