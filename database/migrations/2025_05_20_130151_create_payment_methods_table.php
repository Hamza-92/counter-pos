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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('name', 192);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
        });

        $paymentMethods = [
            ['id' => 1, 'name' => 'Credit Card'],
            ['id' => 2, 'name' => 'Cash'],
            ['id' => 3, 'name' => 'Check'],
            ['id' => 4, 'name' => 'TPE'],
            ['id' => 5, 'name' => 'Western Union'],
            ['id' => 6, 'name' => 'bank transfer'],
            ['id' => 7, 'name' => 'other'],
        ];

        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->updateOrInsert(['id' => $method['id']], $method);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};
