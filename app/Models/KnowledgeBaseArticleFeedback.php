<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseArticleFeedback extends Model
{
    protected $table = 'knowledge_base_article_feedbacks';

    protected $fillable = [
        'knowledge_base_article_id',
        'user_id',
        'helpful',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'knowledge_base_article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
