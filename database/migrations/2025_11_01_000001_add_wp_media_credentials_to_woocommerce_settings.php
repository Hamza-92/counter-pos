<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('woocommerce_settings')) {
            return;
        }
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('woocommerce_settings', 'wp_username')) {
                $table->string('wp_username')->nullable()->after('consumer_secret');
            }
            if (! Schema::hasColumn('woocommerce_settings', 'wp_app_password')) {
                $table->string('wp_app_password')->nullable()->after('wp_username');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('woocommerce_settings')) {
            return;
        }
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (Schema::hasColumn('woocommerce_settings', 'wp_app_password')) {
                $table->dropColumn('wp_app_password');
            }
            if (Schema::hasColumn('woocommerce_settings', 'wp_username')) {
                $table->dropColumn('wp_username');
            }
        });
    }
};
