<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('control')->create('tenant_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('database_name');
            $table->string('relative_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('status', 24)->default('verified')->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Backup history is intentionally retained. Removal is a manual,
        // reviewed control-database operation.
    }
};
