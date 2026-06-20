<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebhookPolicy
{
    use HandlesAuthorization;

    protected function hasPermission(User $user, string $name): bool
    {
        $permission = Permission::where('name', $name)->first();
        if (!$permission) {
            return false;
        }
        return $user->hasRole($permission->roles);
    }

    public function viewAny(User $user)
    {
        return $this->hasPermission($user, 'webhooks_view');
    }

    public function view(User $user, Webhook $webhook = null)
    {
        return $this->hasPermission($user, 'webhooks_view');
    }

    public function create(User $user)
    {
        return $this->hasPermission($user, 'webhooks_add');
    }

    public function update(User $user, Webhook $webhook = null)
    {
        return $this->hasPermission($user, 'webhooks_edit');
    }

    public function delete(User $user, Webhook $webhook = null)
    {
        return $this->hasPermission($user, 'webhooks_delete');
    }
}
