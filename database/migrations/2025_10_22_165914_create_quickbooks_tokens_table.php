<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_books_tokens', function (Blueprint $table) {
            $table->id();

            // match users.id = INT UNSIGNED
            $table->integer('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');

            // QBO company id (realm) and environment
            $table->string('realm_id');
            $table->string('environment')->default('Development'); // Development|Production

            // OAuth2 tokens + expiries
            $table->longText('access_token');
            $table->longText('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();

            $table->timestamps();

            // allow same realm in both environments (Dev/Prod)
            $table->unique(['realm_id', 'environment']);
            $table->index('realm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_books_tokens');
    }
};
