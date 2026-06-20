<?php

namespace App\Policies;

use App\Models\KnowledgeBaseArticle;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KnowledgeBaseArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, KnowledgeBaseArticle $article)
    {
        if (! $article->is_internal) {
            return true;
        }
        $permission = Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }

    public function create(User $user)
    {
        $permission = Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }

    public function update(User $user)
    {
        $permission = Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }

    public function delete(User $user)
    {
        $permission = Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }
}
