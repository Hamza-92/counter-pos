<?php

use Database\Seeders\TranslationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema; // ✅ import the seeder

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('locale'); // e.g. 'en', 'ar', 'fr'
            $table->string('key');    // e.g. 'List_accounts'
            $table->text('value');    // e.g. 'List of Accounts'
            $table->boolean('is_default')->default(false);
            $table->timestamps(6);

            $table->unique(['locale', 'key']); // prevent duplicates
        });

        // ✅ Manually run the seeder class here
        (new TranslationSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
