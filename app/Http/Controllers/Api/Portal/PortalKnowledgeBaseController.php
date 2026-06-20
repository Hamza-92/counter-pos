<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseArticleGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalKnowledgeBaseController extends Controller
{
    /**
     * GET /api/portal/knowledge-base - list public articles (is_internal=false), grouped.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $search = trim((string) $request->input('q', ''));
        $groupId = $request->input('group_id');

        $articles = KnowledgeBaseArticle::query()
            ->where('is_internal', false)
            ->with('group:id,name,slug')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('content', 'LIKE', "%{$search}%");
                });
            })
            ->when($groupId, fn ($q) => $q->where('knowledge_base_article_group_id', $groupId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'knowledge_base_article_group_id', 'title', 'slug', 'sort_order']);

        $groups = KnowledgeBaseArticleGroup::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'groups' => $groups,
            'articles' => $articles,
        ]);
    }

    /**
     * GET /api/portal/knowledge-base/{slug} - public article by slug.
     */
    public function show(Request $request, $slug)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $article = KnowledgeBaseArticle::query()
            ->where('is_internal', false)
            ->where('slug', $slug)
            ->with('group:id,name,slug')
            ->firstOrFail();

        return response()->json([
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $article->content,
            'group' => $article->group ? [
                'id' => $article->group->id,
                'name' => $article->group->name,
                'slug' => $article->group->slug,
            ] : null,
            'updated_at' => optional($article->updated_at)->toIso8601String(),
        ]);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
