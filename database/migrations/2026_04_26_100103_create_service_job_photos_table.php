<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_job_photos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_job_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('stage', 30)->default('intake'); // intake | before | after | delivery
            $table->string('path', 500);
            $table->string('original_name', 191)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->nullable(); // bytes
            $table->text('caption')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index('service_job_id');
            $table->index('stage');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_job_photos');
    }
};
