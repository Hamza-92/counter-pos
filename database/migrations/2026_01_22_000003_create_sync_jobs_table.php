<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncJobsTable extends Migration
{
    public function up(): void
    {
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();

            $table->string('status', 20)->default('pending')->index(); // pending|running|completed|failed|cancelled

            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('success_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('percentage')->default(0);

            $table->string('stage', 100)->nullable();
            $table->unsignedBigInteger('current_product_id')->nullable();
            $table->string('current_sku', 255)->nullable();
            $table->text('last_error')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->boolean('cancel_requested')->default(false)->index();
            $table->dateTime('worker_heartbeat_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
}

