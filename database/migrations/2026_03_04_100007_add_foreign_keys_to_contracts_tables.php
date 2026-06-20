<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToContractsTables extends Migration
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
            Schema::table($table, function (Blueprint $table) use ($name) {
                $table->dropForeign($name);
            });
        }
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop existing FKs if present (e.g. from a previous partial run) to avoid 1022 duplicate key
        $this->dropForeignIfExists('contract_tasks', 'contract_tasks_contract_fk');
        $this->dropForeignIfExists('contract_notes', 'contract_notes_user_fk');
        $this->dropForeignIfExists('contract_notes', 'contract_notes_contract_fk');
        $this->dropForeignIfExists('contract_renewals', 'contract_renewals_from_contract_fk');
        $this->dropForeignIfExists('contract_renewals', 'contract_renewals_contract_fk');
        $this->dropForeignIfExists('contract_comments', 'contract_comments_user_fk');
        $this->dropForeignIfExists('contract_comments', 'contract_comments_contract_fk');
        $this->dropForeignIfExists('contract_attachments', 'contract_attachments_contract_fk');
        $this->dropForeignIfExists('contracts', 'contracts_project_fk');
        $this->dropForeignIfExists('contracts', 'contracts_client_fk');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('client_id', 'contracts_client_fk')
                ->references('id')
                ->on('clients')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');

            $table->foreign('project_id', 'contracts_project_fk')
                ->references('id')
                ->on('projects')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');
        });

        Schema::table('contract_attachments', function (Blueprint $table) {
            $table->foreign('contract_id', 'contract_attachments_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
        });

        // user_id must match users.id type (integer); contract tables use unsignedBigInteger by default
        DB::statement('ALTER TABLE contract_comments MODIFY user_id INT NOT NULL');
        Schema::table('contract_comments', function (Blueprint $table) {
            $table->foreign('contract_id', 'contract_comments_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');

            $table->foreign('user_id', 'contract_comments_user_fk')
                ->references('id')
                ->on('users')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');
        });

        Schema::table('contract_renewals', function (Blueprint $table) {
            $table->foreign('contract_id', 'contract_renewals_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');

            $table->foreign('renewed_from_contract_id', 'contract_renewals_from_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('SET NULL');
        });

        // user_id must match users.id type (integer); contract tables use unsignedBigInteger by default
        DB::statement('ALTER TABLE contract_notes MODIFY user_id INT NOT NULL');
        Schema::table('contract_notes', function (Blueprint $table) {
            $table->foreign('contract_id', 'contract_notes_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');

            $table->foreign('user_id', 'contract_notes_user_fk')
                ->references('id')
                ->on('users')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');
        });

        Schema::table('contract_tasks', function (Blueprint $table) {
            $table->foreign('contract_id', 'contract_tasks_contract_fk')
                ->references('id')
                ->on('contracts')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign('contracts_client_fk');
            $table->dropForeign('contracts_project_fk');
        });

        Schema::table('contract_attachments', function (Blueprint $table) {
            $table->dropForeign('contract_attachments_contract_fk');
        });

        Schema::table('contract_comments', function (Blueprint $table) {
            $table->dropForeign('contract_comments_contract_fk');
            $table->dropForeign('contract_comments_user_fk');
        });
        DB::statement('ALTER TABLE contract_comments MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('contract_renewals', function (Blueprint $table) {
            $table->dropForeign('contract_renewals_contract_fk');
            $table->dropForeign('contract_renewals_from_contract_fk');
        });

        Schema::table('contract_notes', function (Blueprint $table) {
            $table->dropForeign('contract_notes_contract_fk');
            $table->dropForeign('contract_notes_user_fk');
        });
        DB::statement('ALTER TABLE contract_notes MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('contract_tasks', function (Blueprint $table) {
            $table->dropForeign('contract_tasks_contract_fk');
        });
    }
}
