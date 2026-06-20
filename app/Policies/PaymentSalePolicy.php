<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentSalePolicy
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
     * @param  \App\Models\PaymentSale  $PaymentSale
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'payment_sales_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'payment_sales_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\PaymentSale  $PaymentSale
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'payment_sales_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\PaymentSale  $PaymentSale
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'payment_sales_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function Reports_payments_Sales(User $user)
    {
        $permission = Permission::where('name', 'Reports_payments_Sales')->first();

        return $user->hasRole($permission->roles);
    }

    public function report_transactions(User $user)
    {
        $permission = Permission::where('name', 'report_transactions')->first();

        return $user->hasRole($permission->roles);
    }

    public function cash_flow_report(User $user)
    {
        $permission = Permission::where('name', 'cash_flow_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function check_record(User $user, $payment)
    {
        return $user->id === $payment->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\PaymentSale  $PaymentSale
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\PaymentSale  $PaymentSale
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
