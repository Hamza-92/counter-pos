<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tag')->unique();
            $table->string('name');
            $table->unsignedBigInteger('asset_category_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->double('purchase_cost', 15, 2)->nullable();
            $table->string('status')->default('in_use'); // in_use, maintenance, retired
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('assigned_to_id')->nullable(); // optional reference to users.id
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['name', 'status']);
            $table->index('asset_category_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assets');
    }
};
