<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->integer('id', true);
            $table->string('Ref', 192)->nullable();

            $table->integer('customer_id')->index('bookings_customer_id');
            $table->integer('product_id')->nullable()->index('bookings_product_id');
            $table->decimal('price', 10, 2)->nullable();

            $table->date('booking_date');
            $table->time('booking_time');
            $table->time('booking_end_time')->nullable();

            $table->string('status', 50)->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
}












