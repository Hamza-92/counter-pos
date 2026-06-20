<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Optional snapshot table for VAT/tax periods (read-only from UI).
 */
class CreateAccTaxReportsTable extends Migration
{
    public function up()
    {
        Schema::create('acc_tax_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('type', 16)->default('vat');
            $table->decimal('taxable_sales', 20, 6)->default(0);
            $table->decimal('output_tax', 20, 6)->default(0);
            $table->decimal('taxable_purchases', 20, 6)->default(0);
            $table->decimal('input_tax', 20, 6)->default(0);
            $table->decimal('net_tax', 20, 6)->default(0);
            $table->string('source', 16)->default('auto'); // auto, manual
            $table->timestamp('generated_at', 6)->nullable();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_tax_reports');
    }
}





