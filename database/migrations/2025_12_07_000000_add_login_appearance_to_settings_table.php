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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('login_hero_title')
                ->nullable()
                ->after('page_title_suffix')
                ->default('Welcome back!');

            $table->string('login_hero_subtitle')
                ->nullable()
                ->after('login_hero_title')
                ->default('Sign in to access your account and keep your operations in sync.');

            $table->string('login_panel_title')
                ->nullable()
                ->after('login_hero_subtitle')
                ->default('Sign In');

            $table->string('login_panel_subtitle')
                ->nullable()
                ->after('login_panel_title')
                ->default('Access your dashboard and manage everything from one place.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'login_hero_title',
                'login_hero_subtitle',
                'login_panel_title',
                'login_panel_subtitle',
            ]);
        });
    }
};















