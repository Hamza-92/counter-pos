<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Journal Entry Lines detail table for double-entry accounting.
 */
class CreateAccJournalEntryLinesTable extends Migration
{
    public function up()
    {
        Schema::create('acc_journal_entry_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('journal_entry_id')->index();
            $table->integer('coa_id')->nullable()->index(); // link to chart of accounts
            $table->integer('account_id')->nullable()->index(); // optional legacy account mapping
            $table->decimal('debit', 20, 6)->default(0);
            $table->decimal('credit', 20, 6)->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_journal_entry_lines');
    }
}





