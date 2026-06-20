<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('login_hero_badge')->nullable()->after('login_panel_subtitle');
            $table->string('login_hero_feature_1')->nullable()->after('login_hero_badge');
            $table->string('login_hero_feature_2')->nullable()->after('login_hero_feature_1');
            $table->string('login_hero_feature_3')->nullable()->after('login_hero_feature_2');
            $table->string('login_btn_text')->nullable()->after('login_hero_feature_3');
            $table->string('login_footer_text')->nullable()->after('login_btn_text');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'login_hero_badge',
                'login_hero_feature_1',
                'login_hero_feature_2',
                'login_hero_feature_3',
                'login_btn_text',
                'login_footer_text',
            ]);
        });
    }
};
