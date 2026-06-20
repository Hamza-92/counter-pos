<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('registration_enabled')->default(true)->after('enabled');
            $table->boolean('require_invite_code')->default(false)->after('registration_enabled');
            $table->boolean('require_admin_approval')->default(false)->after('require_invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['registration_enabled', 'require_invite_code', 'require_admin_approval']);
        });
    }
};
