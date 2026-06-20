<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticleGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeBaseArticleGroupController extends Controller
{
    /**
     * Groups for filter dropdown (any authenticated user) - only groups that have at least one non-internal article.
     */
    public function forFilter(Request $request)
    {
        $query = KnowledgeBaseArticleGroup::query()
            ->whereHas('articles', function ($q) {
                $q->where('is_internal', false);
            })
            ->orderBy('sort_order')
            ->orderBy('name');
        return response()->json($query->get(['id', 'name', 'slug']));
    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', KnowledgeBaseArticleGroup::class);

        $query = KnowledgeBaseArticleGroup::query()->withCount('articles');
        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }
        $items = $query->orderBy('sort_order')->orderBy('name')->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', KnowledgeBaseArticleGroup::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'alpha_dash', 'unique:knowledge_base_article_groups,slug'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? (KnowledgeBaseArticleGroup::max('sort_order') ?? 0) + 10;

        $group = KnowledgeBaseArticleGroup::create($data);
        return response()->json($group->fresh(), 201);
    }

    public function show(Request $request, KnowledgeBaseArticleGroup $knowledge_base_article_group)
    {
        $this->authorizeForUser($request->user('api'), 'view', KnowledgeBaseArticleGroup::class);

        $knowledge_base_article_group->loadCount('articles');
        return response()->json($knowledge_base_article_group);
    }

    public function update(Request $request, KnowledgeBaseArticleGroup $knowledge_base_article_group)
    {
        $this->authorizeForUser($request->user('api'), 'update', KnowledgeBaseArticleGroup::class);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:190'],
            'slug' => ['sometimes', 'string', 'max:190', 'alpha_dash',
                Rule::unique('knowledge_base_article_groups', 'slug')->ignore($knowledge_base_article_group->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ]);

        $knowledge_base_article_group->fill($data)->save();
        return response()->json($knowledge_base_article_group->fresh());
    }

    public function destroy(Request $request, KnowledgeBaseArticleGroup $knowledge_base_article_group)
    {
        $this->authorizeForUser($request->user('api'), 'delete', KnowledgeBaseArticleGroup::class);

        $knowledge_base_article_group->delete();
        return response()->json(['ok' => true]);
    }
}
