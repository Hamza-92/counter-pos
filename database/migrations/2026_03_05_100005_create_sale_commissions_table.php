<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleCommissionsTable extends Migration
{
    public function up()
    {
        Schema::create('sale_commissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('sale_id')->index();
            $table->unsignedInteger('sales_agent_id')->index();
            $table->unsignedInteger('commission_program_id')->index();
            $table->unsignedInteger('commission_rule_id')->index();
            $table->decimal('base_amount', 14, 4)->default(0);
            $table->decimal('commission_amount', 14, 4)->default(0);
            $table->string('status', 32)->default('pending'); // pending, approved, paid, cancelled
            $table->unsignedInteger('commission_receipt_id')->nullable()->index();
            $table->timestamp('calculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_commissions');
    }
}
