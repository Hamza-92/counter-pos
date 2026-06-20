<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWoocommerceSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('woocommerce_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('store_url');
            $table->string('consumer_key');
            $table->string('consumer_secret');
            // Single auto-sync flag (used by scheduler for stock sync)
            $table->boolean('enable_auto_sync')->default(0);
            $table->string('sync_interval')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('woocommerce_settings');
    }
}
