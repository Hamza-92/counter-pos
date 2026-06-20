<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_incoming_logs')) {
            Schema::create('webhook_incoming_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source'); // generic, woocommerce, quickbooks, etc.
                $table->string('event')->nullable();
                $table->string('ip', 64)->nullable();
                $table->json('headers')->nullable();
                $table->longText('payload')->nullable();
                $table->boolean('signature_valid')->default(0);
                $table->string('status')->default('received'); // received, processed, failed, ignored
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index('source');
                $table->index('status');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_incoming_logs');
    }
};
