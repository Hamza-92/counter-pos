<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseArticleFeedback;
use App\Models\KnowledgeBaseArticleGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeBaseArticleController extends Controller
{
    protected function canManageArticles($user)
    {
        $permission = \App\Models\Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }

    public function index(Request $request)
    {
        $user = $request->user('api');
        $canManage = $this->canManageArticles($user);

        $query = KnowledgeBaseArticle::query()->with('group:id,name,slug');
        if (! $canManage) {
            $query->where('is_internal', false);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }
        $groupId = $request->get('group_id');
        if ($groupId !== null && $groupId !== '') {
            $query->where('knowledge_base_article_group_id', $groupId);
        }

        $items = $query->orderBy('sort_order')->orderBy('title')->paginate(
            min((int) $request->get('per_page', 15), 50)
        );
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', KnowledgeBaseArticle::class);

        $data = $request->validate([
            'knowledge_base_article_group_id' => ['required', 'integer', 'exists:knowledge_base_article_groups,id'],
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'alpha_dash'],
            'content' => ['nullable', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
        ]);
        $data['is_internal'] = ! empty($data['is_internal']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $slugRule = Rule::unique('knowledge_base_articles', 'slug')
            ->where('knowledge_base_article_group_id', $data['knowledge_base_article_group_id']);
        $request->validate(['slug' => [$slugRule]]);

        $article = KnowledgeBaseArticle::create($data);
        $article->load('group:id,name,slug');
        return response()->json($article->fresh(), 201);
    }

    public function show(Request $request, KnowledgeBaseArticle $knowledge_base_article)
    {
        $this->authorizeForUser($request->user('api'), 'view', $knowledge_base_article);

        $knowledge_base_article->load('group:id,name,slug');
        $knowledge_base_article->loadCount(['feedbacks as feedback_helpful_count' => function ($q) {
            $q->where('helpful', true);
        }, 'feedbacks as feedback_not_helpful_count' => function ($q) {
            $q->where('helpful', false);
        }]);
        return response()->json($knowledge_base_article);
    }

    public function update(Request $request, KnowledgeBaseArticle $knowledge_base_article)
    {
        $this->authorizeForUser($request->user('api'), 'update', KnowledgeBaseArticle::class);

        $data = $request->validate([
            'knowledge_base_article_group_id' => ['sometimes', 'integer', 'exists:knowledge_base_article_groups,id'],
            'title' => ['sometimes', 'string', 'max:190'],
            'slug' => ['sometimes', 'string', 'max:190', 'alpha_dash'],
            'content' => ['sometimes', 'nullable', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ]);
        if (array_key_exists('slug', $data)) {
            $groupId = $data['knowledge_base_article_group_id'] ?? $knowledge_base_article->knowledge_base_article_group_id;
            \Validator::make(
                ['slug' => $data['slug']],
                ['slug' => [Rule::unique('knowledge_base_articles', 'slug')->where('knowledge_base_article_group_id', $groupId)->ignore($knowledge_base_article->id)]]
            )->validate();
        }
        if (array_key_exists('is_internal', $data)) {
            $data['is_internal'] = ! empty($data['is_internal']);
        }

        $knowledge_base_article->fill($data)->save();
        $knowledge_base_article->load('group:id,name,slug');
        return response()->json($knowledge_base_article->fresh());
    }

    public function destroy(Request $request, KnowledgeBaseArticle $knowledge_base_article)
    {
        $this->authorizeForUser($request->user('api'), 'delete', KnowledgeBaseArticle::class);

        $knowledge_base_article->delete();
        return response()->json(['ok' => true]);
    }

    public function submitFeedback(Request $request, KnowledgeBaseArticle $knowledge_base_article)
    {
        $this->authorizeForUser($request->user('api'), 'view', $knowledge_base_article);

        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        $user = $request->user('api');
        $existing = KnowledgeBaseArticleFeedback::where('knowledge_base_article_id', $knowledge_base_article->id)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            $existing->update(['helpful' => (bool) $data['helpful']]);
            return response()->json(['ok' => true, 'updated' => true]);
        }

        KnowledgeBaseArticleFeedback::create([
            'knowledge_base_article_id' => $knowledge_base_article->id,
            'user_id' => $user->id,
            'helpful' => (bool) $data['helpful'],
        ]);
        return response()->json(['ok' => true]);
    }
}
