<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use HandlesAuthorization;

    public function view(User $user)
    {
        $permission = Permission::where('name', 'assets')->first();

        return $permission ? $user->hasRole($permission->roles) : false;
    }

    public function create(User $user)
    {
        $permission = Permission::where('name', 'assets')->first();

        return $permission ? $user->hasRole($permission->roles) : false;
    }

    public function update(User $user)
    {
        $permission = Permission::where('name', 'assets')->first();

        return $permission ? $user->hasRole($permission->roles) : false;
    }

    public function delete(User $user)
    {
        $permission = Permission::where('name', 'assets')->first();

        return $permission ? $user->hasRole($permission->roles) : false;
    }
}





