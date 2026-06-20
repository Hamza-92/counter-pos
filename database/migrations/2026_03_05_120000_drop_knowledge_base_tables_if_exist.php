<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time fix: drops Knowledge Base tables if they exist so the create migrations can run cleanly.
 *
 * Steps:
 * 1. php artisan migrate   (runs this migration and drops the 3 tables)
 * 2. In MySQL: DELETE FROM migrations WHERE migration IN (
 *      '2026_03_05_120001_create_knowledge_base_article_groups_table',
 *      '2026_03_05_120002_create_knowledge_base_articles_table',
 *      '2026_03_05_120003_create_knowledge_base_article_feedbacks_table'
 *    );
 * 3. php artisan migrate   (recreates the 3 tables)
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('knowledge_base_article_feedbacks');
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('knowledge_base_article_groups');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down()
    {
        // No-op: the real create migrations will recreate tables
    }
};
