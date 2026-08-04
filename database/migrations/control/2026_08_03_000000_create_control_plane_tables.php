<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('control');

        $schema->create('super_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true)->index();
            $table->text('totp_secret')->nullable();
            $table->text('recovery_code_hashes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 24)->default('provisioning')->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('manual_suspension_reason')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedInteger('schema_version')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });

        $schema->create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('host');
            $table->string('normalized_host')->unique();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'is_primary']);
        });

        $schema->create('tenant_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->string('driver', 20)->default('mysql');
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database_name')->unique();
            $table->string('username');
            $table->text('password');
            $table->string('migration_username')->nullable();
            $table->text('migration_password')->nullable();
            $table->string('ssl_mode', 30)->nullable();
            $table->unsignedInteger('credential_version')->default(1);
            $table->timestamp('last_connection_test_at')->nullable();
            $table->boolean('last_connection_succeeded')->nullable();
            $table->text('last_connection_error')->nullable();
            $table->timestamps();
        });

        $schema->create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('billing_interval', 30)->default('monthly');
            $table->decimal('price', 18, 2)->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->boolean('is_active')->default(true)->index();
            $table->json('features')->nullable();
            $table->timestamps();
        });

        $schema->create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('grace_ends_at')->nullable();
            $table->decimal('agreed_amount', 18, 2);
            $table->char('currency', 3);
            $table->uuid('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->index(['tenant_id', 'ends_at']);
        });

        $schema->create('manual_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('manual_payments')->restrictOnDelete();
            $table->string('reference')->nullable()->unique();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->dateTime('paid_at');
            $table->string('method', 80)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by');
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->index(['tenant_id', 'paid_at']);
        });

        $schema->create('provisioning_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('action', 60);
            $table->unsignedInteger('target_schema_version')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->json('result')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('control_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('target_type', 100)->nullable();
            $table->uuid('target_id')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });

        $schema->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        $schema->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        $schema->create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        $schema->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $schema->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('control');
        foreach ([
            'failed_jobs', 'jobs', 'cache_locks', 'cache', 'sessions',
            'control_audit_logs', 'provisioning_runs', 'manual_payments',
            'subscriptions', 'plans', 'tenant_databases', 'domains', 'tenants', 'super_admins',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
