<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Journal Entries header table for double-entry accounting.
 */
class CreateAccJournalEntriesTable extends Migration
{
    public function up()
    {
        Schema::create('acc_journal_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->date('date');
            $table->string('reference_type', 64)->nullable(); // sale, purchase, expense, deposit, transfer, manual
            $table->integer('reference_id')->nullable();
            $table->string('status', 16)->default('draft'); // draft, posted
            $table->timestamp('posted_at', 6)->nullable();
            $table->text('description')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_journal_entries');
    }
}





