<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('control')->create('crm_api_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('idempotency_key')->unique();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('control')->dropIfExists('crm_api_requests');
    }
};
