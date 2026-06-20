<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $model
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'users_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'users_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $model
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'users_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $model
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'users_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function backup(User $user)
    {
        $backupPermission = Permission::where('name', 'backup')->first();
        $systemPermission = Permission::where('name', 'setting_system')->first();
    
        if (
            ($backupPermission && $user->hasRole($backupPermission->roles)) ||
            ($systemPermission && $user->hasRole($systemPermission->roles))
        ) {
            return true;
        }
    
        return false;
    }

    public function system_health_view(User $user)
    {
        $permission = Permission::where('name', 'system_health_view')->first();

        return $permission && $user->hasRole($permission->roles);
    }
    

    public function users_report(User $user)
    {
        $permission = Permission::where('name', 'users_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function seller_report(User $user)
    {
        $permission = Permission::where('name', 'seller_report')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $model
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $model
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
