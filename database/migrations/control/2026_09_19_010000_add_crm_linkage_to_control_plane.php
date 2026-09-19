<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('control')->table('tenants', function (Blueprint $table): void {
            $table->unsignedBigInteger('crm_application_instance_id')->nullable()->unique()->after('id');
            $table->string('data_template_code', 64)->nullable()->after('schema_version');
            $table->unsignedInteger('data_template_version')->nullable()->after('data_template_code');
        });

        Schema::connection('control')->table('provisioning_runs', function (Blueprint $table): void {
            $table->string('source', 32)->default('control_panel')->index()->after('action');
            $table->string('external_reference', 120)->nullable()->unique()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::connection('control')->table('provisioning_runs', function (Blueprint $table): void {
            $table->dropUnique(['external_reference']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'external_reference']);
        });

        Schema::connection('control')->table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['crm_application_instance_id']);
            $table->dropColumn([
                'crm_application_instance_id',
                'data_template_code',
                'data_template_version',
            ]);
        });
    }
};