<?php

namespace App\Policies;

use App\Models\Adjustment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdjustmentPolicy
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
     * @param  \App\Models\Adjustment  $adjustment
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'adjustment_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'adjustment_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Adjustment  $adjustment
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'adjustment_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Adjustment  $adjustment
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'adjustment_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function Stock_Adjustment_Report(User $user)
    {
        $permission = Permission::where('name', 'Stock_Adjustment_Report')->first();

        return $user->hasRole($permission->roles);
    }

    public function check_record(User $user, $adjustment)
    {
        return $user->id === $adjustment->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, Adjustment $adjustment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, Adjustment $adjustment)
    {
        //
    }
}
