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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_preorder')->default(false)->after('hide_from_online_store');
            $table->date('preorder_available_date')->nullable()->after('is_preorder');
            $table->unsignedInteger('preorder_limit')->nullable()->after('preorder_available_date');
            $table->string('preorder_note', 500)->nullable()->after('preorder_limit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_preorder', 'preorder_available_date', 'preorder_limit', 'preorder_note']);
        });
    }
};
