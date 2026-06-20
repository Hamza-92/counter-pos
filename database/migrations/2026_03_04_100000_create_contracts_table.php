<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractsTable extends Migration
{
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('contract_number')->unique();
            $table->integer('client_id')->index();
            $table->integer('project_id')->nullable()->index();
            $table->string('subject');
            $table->decimal('value', 16, 2)->default(0);
            $table->string('type')->nullable(); // e.g. service, lease, sales, nda, other
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('hide_from_customer')->default(false);
            $table->string('status')->default('draft'); // draft, active, expired, cancelled
            $table->string('signer_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contracts');
    }
}
