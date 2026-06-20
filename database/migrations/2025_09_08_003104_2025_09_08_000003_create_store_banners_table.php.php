<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('position')->default('home_hero'); // e.g. home_hero, home_middle, category_top
            $table->string('link')->nullable();
            $table->string('image')->nullable(); // stored path under storage/app/public/...
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_banners');
    }
};
