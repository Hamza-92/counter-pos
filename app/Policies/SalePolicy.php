<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy
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
     * @param  \App\Models\Sale  $sale
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'Sales_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'Sales_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Sale  $sale
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'Sales_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Sale  $sale
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'Sales_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function Reports_sales(User $user)
    {
        $permission = Permission::where('name', 'Reports_sales')->first();

        return $user->hasRole($permission->roles);
    }

    public function Sales_pos(User $user)
    {
        $permission = Permission::where('name', 'Pos_view')->first();

        return $user->hasRole($permission->roles);
    }

    public function product_sales_report(User $user)
    {
        $permission = Permission::where('name', 'product_sales_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function report_sales_by_category(User $user)
    {
        $permission = Permission::where('name', 'report_sales_by_category')->first();

        return $user->hasRole($permission->roles);
    }

    public function report_sales_by_brand(User $user)
    {
        $permission = Permission::where('name', 'report_sales_by_brand')->first();

        return $user->hasRole($permission->roles);
    }

    public function draft_invoices_report(User $user)
    {
        $permission = Permission::where('name', 'draft_invoices_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function discount_summary_report(User $user)
    {
        $permission = Permission::where('name', 'discount_summary_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function tax_summary_report(User $user)
    {
        $permission = Permission::where('name', 'tax_summary_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function cash_register_report(User $user)
    {
        $permission = Permission::where('name', 'cash_register_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function customer_display_screen_setup(User $user)
    {
        $permission = Permission::where('name', 'customer_display_screen_setup')->first();

        return $user->hasRole($permission->roles);
    }

    public function quickbooks_settings(User $user)
    {
        $permission = Permission::where('name', 'quickbooks_settings')->first();

        return $user->hasRole($permission->roles);
    }

    public function customer_loyalty_points_report(User $user)
    {
        $permission = Permission::where('name', 'customer_loyalty_points_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function report_warranty(User $user)
    {
        $permission = Permission::where('name', 'report_warranty')->first();

        return $user->hasRole($permission->roles);
    }

    public function check_record(User $user, $sale)
    {
        return $user->id === $sale->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Sale  $sale
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Sale  $sale
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
