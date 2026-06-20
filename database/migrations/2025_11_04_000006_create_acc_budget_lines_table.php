<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Budget lines per COA (optional future feature).
 */
class CreateAccBudgetLinesTable extends Migration
{
    public function up()
    {
        Schema::create('acc_budget_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('budget_id')->index();
            $table->integer('coa_id')->index();
            $table->decimal('amount', 20, 6)->default(0);
            $table->string('period', 16)->default('monthly'); // monthly, quarterly, yearly
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_budget_lines');
    }
}





