<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('knowledge_base_articles', 'published_at')) {
                $table->date('published_at')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            if (Schema::hasColumn('knowledge_base_articles', 'published_at')) {
                $table->dropColumn('published_at');
            }
        });
    }
};
