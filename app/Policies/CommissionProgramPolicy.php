<?php

namespace App\Policies;

use App\Models\CommissionProgram;
use App\Models\Permission;
use App\Models\User;

class CommissionProgramPolicy
{
    public function view(User $user)
    {
        return $this->hasPermission($user, 'commissions_view');
    }

    public function create(User $user)
    {
        return $this->hasPermission($user, 'commissions_add');
    }

    public function update(User $user)
    {
        return $this->hasPermission($user, 'commissions_edit');
    }

    public function delete(User $user)
    {
        return $this->hasPermission($user, 'commissions_delete');
    }

    protected function hasPermission(User $user, string $name): bool
    {
        $permission = Permission::with('roles')->where('name', $name)->first();
        if (! $permission) {
            return false;
        }
        return $user->hasRole($permission->roles);
    }
}
