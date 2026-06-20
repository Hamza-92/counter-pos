<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'sync_issue_type')) {
                $table->string('sync_issue_type', 64)->nullable()->after('woocommerce_id');
            }
            if (!Schema::hasColumn('clients', 'sync_issue_message')) {
                $table->text('sync_issue_message')->nullable()->after('sync_issue_type');
            }
            if (!Schema::hasColumn('clients', 'sync_issue_source')) {
                $table->string('sync_issue_source', 32)->nullable()->after('sync_issue_message');
            }
            if (!Schema::hasColumn('clients', 'sync_issue_at')) {
                $table->dateTime('sync_issue_at')->nullable()->after('sync_issue_source');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'sync_issue_at')) {
                $table->dropColumn('sync_issue_at');
            }
            if (Schema::hasColumn('clients', 'sync_issue_source')) {
                $table->dropColumn('sync_issue_source');
            }
            if (Schema::hasColumn('clients', 'sync_issue_message')) {
                $table->dropColumn('sync_issue_message');
            }
            if (Schema::hasColumn('clients', 'sync_issue_type')) {
                $table->dropColumn('sync_issue_type');
            }
        });
    }
};

