<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('service_jobs', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->nullable()->after('parent_job_id');
                $table->index('quotation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('service_jobs', 'quotation_id')) {
                $table->dropIndex(['quotation_id']);
                $table->dropColumn('quotation_id');
            }
        });
    }
};
