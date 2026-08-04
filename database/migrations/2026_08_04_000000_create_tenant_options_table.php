<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_options')) {
            return;
        }

        Schema::create('tenant_options', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_options');
    }
};
