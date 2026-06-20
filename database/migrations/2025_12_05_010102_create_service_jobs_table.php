<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('Ref', 192)->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('technician_id')->nullable(); // employees.id
            $table->string('service_item')->nullable(); // vehicle or item description
            $table->string('job_type')->nullable();
            $table->string('status')->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['client_id']);
            $table->index(['technician_id']);
            $table->index(['job_type']);
            $table->index(['status']);
            $table->index(['scheduled_date']);
            $table->index(['started_at']);
            $table->index(['completed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_jobs');
    }
};

















