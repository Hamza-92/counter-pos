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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('name'); // Field label/name
            $table->string('field_type'); // text, number, textarea, date, select, checkbox
            $table->string('entity_type'); // 'client' or 'provider'
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('default_value')->nullable(); // For select fields, JSON array of options
            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
