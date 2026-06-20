<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToExpenseDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->foreign('expense_id', 'expense_documents_expense_id_foreign')
                ->references('id')
                ->on('expenses')
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
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->dropForeign('expense_documents_expense_id_foreign');
        });
    }
}

















