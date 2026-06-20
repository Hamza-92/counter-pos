<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseArticle extends Model
{
    protected $table = 'knowledge_base_articles';

    protected $fillable = [
        'knowledge_base_article_group_id',
        'title',
        'slug',
        'content',
        'is_internal',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'date:Y-m-d',
    ];

    public function group()
    {
        return $this->belongsTo(KnowledgeBaseArticleGroup::class, 'knowledge_base_article_group_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(KnowledgeBaseArticleFeedback::class, 'knowledge_base_article_id');
    }
}
