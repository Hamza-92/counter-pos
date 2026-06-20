<?php

// database/migrations/2025_10_12_000001_create_quickbooks_audits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_books_audits', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable()->index();   // who triggered
            $table->integer('sale_id')->nullable()->index();   // related sale (if any)
            $table->string('realm_id', 64)->nullable()->index();
            $table->string('environment', 20)->default('Development');    // Development|Production
            $table->string('operation', 32);                               // connect|disconnect|create|update|delete|settings.save|status
            $table->string('level', 16)->default('info');                  // info|warning|error
            $table->string('message', 512)->nullable();

            $table->integer('http_code')->nullable();
            $table->longText('request_payload')->nullable();               // JSON
            $table->longText('response_body')->nullable();                 // JSON/raw
            $table->longText('sdk_error')->nullable();                     // raw error

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_books_audits');
    }
};
