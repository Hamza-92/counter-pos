<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Budgets header table (optional future feature).
 */
class CreateAccBudgetsTable extends Migration
{
    public function up()
    {
        Schema::create('acc_budgets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('name', 192);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_budgets');
    }
}





