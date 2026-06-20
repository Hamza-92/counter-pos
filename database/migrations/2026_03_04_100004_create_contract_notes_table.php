<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractNotesTable extends Migration
{
    public function up()
    {
        Schema::create('contract_notes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('contract_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('content');
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_notes');
    }
}
