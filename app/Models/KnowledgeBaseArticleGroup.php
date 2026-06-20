<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseArticleGroup extends Model
{
    protected $table = 'knowledge_base_article_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function articles()
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'knowledge_base_article_group_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }
}
