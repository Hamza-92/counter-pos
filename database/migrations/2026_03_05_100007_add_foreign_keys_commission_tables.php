<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysCommissionTables extends Migration
{
    /**
     * Drop a foreign key if it exists (safe for re-running migration).
     */
    private function dropForeignIfExists(string $table, string $name): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name]
        );
        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($name) {
                $t->dropForeign($name);
            });
        }
    }

    public function up()
    {
        // Drop existing FKs if present (e.g. from a previous partial run) to avoid 1022 duplicate key
        $this->dropForeignIfExists('sales', 'sales_sales_agent_id_foreign');
        $this->dropForeignIfExists('sale_commissions', 'sale_commissions_sale_id_foreign');
        $this->dropForeignIfExists('sale_commissions', 'sale_commissions_sales_agent_id_foreign');
        $this->dropForeignIfExists('sale_commissions', 'sale_commissions_commission_program_id_foreign');
        $this->dropForeignIfExists('sale_commissions', 'sale_commissions_commission_rule_id_foreign');
        $this->dropForeignIfExists('sale_commissions', 'sale_commissions_commission_receipt_id_foreign');
        $this->dropForeignIfExists('commission_receipts', 'commission_receipts_sales_agent_id_foreign');
        $this->dropForeignIfExists('commission_receipts', 'commission_receipts_payment_method_id_foreign');
        $this->dropForeignIfExists('commission_rules', 'commission_rules_commission_program_id_foreign');
        $this->dropForeignIfExists('commission_rules', 'commission_rules_sales_agent_id_foreign');
        $this->dropForeignIfExists('sales_agents', 'sales_agents_user_id_foreign');

        // user_id must match users.id type (signed INT); sales_agents uses unsignedInteger by default
        DB::statement('ALTER TABLE sales_agents MODIFY user_id INT NULL');
        Schema::table('sales_agents', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('commission_rules', function (Blueprint $table) {
            $table->foreign('commission_program_id')->references('id')->on('commission_programs')->cascadeOnDelete();
            $table->foreign('sales_agent_id')->references('id')->on('sales_agents')->nullOnDelete();
        });

        // payment_method_id must match payment_methods.id type (signed INT)
        DB::statement('ALTER TABLE commission_receipts MODIFY payment_method_id INT NULL');
        Schema::table('commission_receipts', function (Blueprint $table) {
            $table->foreign('sales_agent_id')->references('id')->on('sales_agents')->cascadeOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->nullOnDelete();
        });

        // sale_id must match sales.id type (signed INT)
        DB::statement('ALTER TABLE sale_commissions MODIFY sale_id INT NOT NULL');
        Schema::table('sale_commissions', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('sales_agent_id')->references('id')->on('sales_agents')->cascadeOnDelete();
            $table->foreign('commission_program_id')->references('id')->on('commission_programs')->cascadeOnDelete();
            $table->foreign('commission_rule_id')->references('id')->on('commission_rules')->cascadeOnDelete();
            $table->foreign('commission_receipt_id')->references('id')->on('commission_receipts')->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('sales_agent_id')->references('id')->on('sales_agents')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['sales_agent_id']);
        });
        Schema::table('sale_commissions', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['sales_agent_id']);
            $table->dropForeign(['commission_program_id']);
            $table->dropForeign(['commission_rule_id']);
            $table->dropForeign(['commission_receipt_id']);
        });
        DB::statement('ALTER TABLE sale_commissions MODIFY sale_id INT UNSIGNED NOT NULL');
        Schema::table('commission_receipts', function (Blueprint $table) {
            $table->dropForeign(['sales_agent_id']);
            $table->dropForeign(['payment_method_id']);
        });
        DB::statement('ALTER TABLE commission_receipts MODIFY payment_method_id INT UNSIGNED NULL');
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropForeign(['commission_program_id']);
            $table->dropForeign(['sales_agent_id']);
        });
        Schema::table('sales_agents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        DB::statement('ALTER TABLE sales_agents MODIFY user_id INT UNSIGNED NULL');
    }
}
