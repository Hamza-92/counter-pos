<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractCommentsTable extends Migration
{
    public function up()
    {
        Schema::create('contract_comments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('contract_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_comments');
    }
}
