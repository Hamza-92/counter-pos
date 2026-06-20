<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionRulesTable extends Migration
{
    public function up()
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('commission_program_id')->index();
            $table->string('name');
            $table->string('type', 32)->default('percentage'); // percentage, fixed
            $table->string('source', 32)->default('sale_total'); // sale_total, paid_amount
            $table->decimal('value', 14, 4)->default(0);
            $table->decimal('min_threshold', 14, 4)->nullable();
            $table->decimal('max_cap', 14, 4)->nullable();
            $table->string('applies_to', 32)->default('all_agents'); // all_agents, specific_agent
            $table->unsignedInteger('sales_agent_id')->nullable()->index();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_rules');
    }
}
