<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KnowledgeBaseArticleGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        $permission = Permission::where('name', 'knowledge_base_view')->first();
        return $permission && $user->hasRole($permission->roles);
    }

    public function view(User $user)
    {
        return $this->viewAny($user);
    }

    public function create(User $user)
    {
        return $this->viewAny($user);
    }

    public function update(User $user)
    {
        return $this->viewAny($user);
    }

    public function delete(User $user)
    {
        return $this->viewAny($user);
    }
}
