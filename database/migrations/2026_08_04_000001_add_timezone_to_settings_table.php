<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || Schema::hasColumn('settings', 'timezone')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('date_format');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'timezone')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
