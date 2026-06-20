<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_job_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('Ref', 192)->nullable();
            $table->unsignedBigInteger('service_job_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->date('date');
            $table->float('montant', 10, 0)->default(0);
            $table->float('change', 10, 0)->default(0);
            $table->string('payment_kind', 30)->default('payment'); // deposit | payment | refund
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index('service_job_id');
            $table->index('payment_method_id');
            $table->index('account_id');
            $table->index('date');
            $table->index('payment_kind');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_job_payments');
    }
};
