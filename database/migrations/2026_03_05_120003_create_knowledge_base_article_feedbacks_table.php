<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('knowledge_base_article_feedbacks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('knowledge_base_article_id');
            // users.id is INT (not BIGINT, not UNSIGNED) in this project
            $table->integer('user_id')->nullable();
            $table->boolean('helpful');
            $table->timestamps();

            $table->foreign('knowledge_base_article_id', 'kb_article_feedback_article_fk')
                ->references('id')
                ->on('knowledge_base_articles')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'kb_article_feedback_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['knowledge_base_article_id', 'user_id'], 'kb_article_feedback_article_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('knowledge_base_article_feedbacks');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
