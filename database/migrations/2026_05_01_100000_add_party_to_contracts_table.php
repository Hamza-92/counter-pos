<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPartyToContractsTable extends Migration
{
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

    public function up()
    {
        if (!Schema::hasColumn('contracts', 'party_type')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('party_type', 20)->default('customer')->after('contract_number');
            });
        }

        if (!Schema::hasColumn('contracts', 'employee_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->integer('employee_id')->nullable()->after('client_id')->index();
            });
        }

        $this->dropForeignIfExists('contracts', 'contracts_client_fk');

        DB::statement('ALTER TABLE contracts MODIFY client_id INT NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('client_id', 'contracts_client_fk')
                ->references('id')->on('clients')
                ->onUpdate('RESTRICT')->onDelete('RESTRICT');

            $table->foreign('employee_id', 'contracts_employee_fk')
                ->references('id')->on('employees')
                ->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    public function down()
    {
        $this->dropForeignIfExists('contracts', 'contracts_employee_fk');
        $this->dropForeignIfExists('contracts', 'contracts_client_fk');

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'employee_id')) {
                $table->dropColumn('employee_id');
            }
            if (Schema::hasColumn('contracts', 'party_type')) {
                $table->dropColumn('party_type');
            }
        });

        DB::statement('ALTER TABLE contracts MODIFY client_id INT NOT NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('client_id', 'contracts_client_fk')
                ->references('id')->on('clients')
                ->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }
}
