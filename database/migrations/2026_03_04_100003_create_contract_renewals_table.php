<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractRenewalsTable extends Migration
{
    public function up()
    {
        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('contract_id')->index();
            $table->integer('renewed_from_contract_id')->nullable()->index();
            $table->date('renewal_date');
            $table->date('new_end_date');
            $table->text('notes')->nullable();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_renewals');
    }
}
