<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
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
     * @param  \App\Models\Client  $client
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'Customers_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'Customers_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Client  $client
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'Customers_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Client  $client
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'Customers_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function customers_import(User $user)
    {
        $permission = Permission::where('name', 'customers_import')->first();

        return $user->hasRole($permission->roles);
    }

    public function Reports_customers(User $user)
    {
        $permission = Permission::where('name', 'Reports_customers')->first();

        return $user->hasRole($permission->roles);
    }

    public function Reports_profit(User $user)
    {
        $permission = Permission::where('name', 'Reports_profit')->first();

        return $user->hasRole($permission->roles);
    }

    public function AI_Reports(User $user)
    {
        $permission = Permission::where('name', 'AI_Reports')->first();

        return $user->hasRole($permission->roles);
    }

    public function analytics_report(User $user)
    {
        $permission = Permission::where('name', 'analytics_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function return_ratio_report(User $user)
    {
        $permission = Permission::where('name', 'return_ratio_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function Top_customers(User $user)
    {
        $permission = Permission::where('name', 'Top_customers')->first();

        return $user->hasRole($permission->roles);
    }

    public function pay_due(User $user)
    {
        $permission = Permission::where('name', 'pay_due')->first();

        return $user->hasRole($permission->roles);
    }

    public function pay_sale_return_due(User $user)
    {
        $permission = Permission::where('name', 'pay_sale_return_due')->first();

        return $user->hasRole($permission->roles);
    }

    public function inactive_customers_report(User $user)
    {
        $permission = Permission::where('name', 'inactive_customers_report')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, Client $client)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, Client $client)
    {
        //
    }
}
