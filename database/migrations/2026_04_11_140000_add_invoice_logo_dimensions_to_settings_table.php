<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('invoice_logo_width')
                ->default(180)
                ->after('invoice_format');
            $table->unsignedSmallInteger('invoice_logo_height')
                ->default(60)
                ->after('invoice_logo_width');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['invoice_logo_width', 'invoice_logo_height']);
        });
    }
};
