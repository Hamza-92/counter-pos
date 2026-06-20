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
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('custom_field_id');
            $table->integer('entity_id'); // ID of client or provider
            $table->string('entity_type'); // 'App\Models\Client' or 'App\Models\Provider'
            $table->text('value')->nullable(); // Store the field value
            $table->timestamps(6);
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['custom_field_id', 'entity_id', 'entity_type'], 'custom_field_value_lookup');
            $table->foreign('custom_field_id')->references('id')->on('custom_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
