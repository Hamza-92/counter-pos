<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('commission_receipts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('sales_agent_id')->index();
            $table->string('Ref', 192)->unique();
            $table->decimal('amount', 14, 4)->default(0);
            $table->date('paid_at');
            $table->unsignedInteger('payment_method_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_receipts');
    }
}
