<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('contract_attachments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('contract_id')->index();
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_attachments');
    }
}
