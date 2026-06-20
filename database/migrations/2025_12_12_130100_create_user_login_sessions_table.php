<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If a previous attempt created the table but failed adding the FK,
        // recreate it with the correct schema (this table is new/additive).
        if (Schema::hasTable('user_login_sessions')) {
            Schema::drop('user_login_sessions');
        }

        Schema::create('user_login_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Match existing schema: users.id is INT (not unsigned, not bigint)
            $table->integer('user_id')->index();
            // Passport access token id (oauth_access_tokens.id)
            $table->string('access_token_id', 100)->index();
            $table->string('session_id', 255)->nullable()->index();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->unique(['access_token_id'], 'user_login_sessions_access_token_unique');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_sessions');
    }
};



























































