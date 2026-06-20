<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portal_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->onUpdate('cascade')->onDelete('cascade');
            $table->string('email', 192)->unique();
            $table->string('password')->nullable();
            $table->boolean('status')->default(0)->comment('0=disabled, 1=active');
            $table->string('invitation_token', 64)->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('portal_clients', function (Blueprint $table) {
            $table->index(['email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_clients');
    }
};
