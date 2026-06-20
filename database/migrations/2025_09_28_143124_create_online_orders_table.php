<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);

            // New core fields
            $table->date('date')->nullable();            // order date (set on create)
            $table->time('time')->nullable();            // order time (set on create)
            $table->string('ref', 64)->unique();         // human-friendly reference (e.g., SO-20250928-0001)
            $table->string('status', 20)->default('pending');

            // Minimal checkout fields
            $table->integer('client_id')->nullable();
            $table->integer('warehouse_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);

            $table->timestamps();

            // (optional) FKs
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();

            // Helpful indexes
            $table->index(['client_id']);
            $table->index(['warehouse_id']);
            $table->index(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_orders');
    }
};
