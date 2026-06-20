<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy
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
     * @param  \App\Models\Unit  $unit
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'unit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'unit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Unit  $unit
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'unit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Unit  $unit
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'unit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Unit  $unit
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Unit  $unit
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
