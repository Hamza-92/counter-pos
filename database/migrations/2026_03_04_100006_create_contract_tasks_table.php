<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractTasksTable extends Migration
{
    public function up()
    {
        Schema::create('contract_tasks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('contract_id')->index();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('description')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_tasks');
    }
}
