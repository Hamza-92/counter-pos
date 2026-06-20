<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url', 2048);
                $table->string('secret', 128);
                $table->json('events');
                $table->json('headers')->nullable();
                $table->boolean('is_active')->default(1);
                $table->unsignedInteger('timeout_seconds')->default(15);
                $table->timestamp('last_fired_at')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('is_active');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
