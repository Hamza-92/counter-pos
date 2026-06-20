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
        if (!Schema::hasColumn('sms_messages', 'locale')) {
            Schema::table('sms_messages', function (Blueprint $table) {
                $table->string('locale', 10)->default('en')->after('name');
            });
            \DB::table('sms_messages')->whereNull('locale')->update(['locale' => 'en']);
        }

        // Ensure no NULL or empty name (legacy rows) so unique index can be added in next migration
        \DB::statement("UPDATE sms_messages SET name = CONCAT('legacy_', id) WHERE name IS NULL OR TRIM(COALESCE(name, '')) = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sms_messages', 'locale')) {
            Schema::table('sms_messages', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
