<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('webhook_id');
                $table->string('event');
                $table->string('status')->default('pending'); // pending, success, failed, retrying
                $table->unsignedTinyInteger('attempt')->default(0);
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->longText('payload')->nullable();
                $table->longText('response_body')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamps();

                $table->index('webhook_id');
                $table->index('status');
                $table->index('event');
                $table->index('created_at');

                $table->foreign('webhook_id')
                    ->references('id')->on('webhooks')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
