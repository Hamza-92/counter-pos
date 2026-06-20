<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
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
     * @param  \App\Models\Product  $product
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'products_view')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'products_add')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Product  $product
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'products_edit')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Product  $product
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'products_delete')->first();

        return $user->hasRole($permission->roles);
    }

    public function product_import(User $user)
    {
        $permission = Permission::where('name', 'product_import')->first();

        return $user->hasRole($permission->roles);
    }

    public function barcode(User $user)
    {
        $permission = Permission::where('name', 'barcode_view')->first();

        return $user->hasRole($permission->roles);
    }

    public function Stock_Alerts(User $user)
    {
        $permission = Permission::where('name', 'Reports_quantity_alerts')->first();

        return $user->hasRole($permission->roles);
    }

    public function WarehouseStock(User $user)
    {
        $permission = Permission::where('name', 'Warehouse_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function internal_location_report(User $user)
    {
        $permission = Permission::where('name', 'internal_location_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function Top_products(User $user)
    {
        $permission = Permission::where('name', 'Top_products')->first();

        return $user->hasRole($permission->roles);
    }

    public function stock_report(User $user)
    {
        $permission = Permission::where('name', 'stock_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function negative_stock_report(User $user)
    {
        $permission = Permission::where('name', 'negative_stock_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function product_report(User $user)
    {
        $permission = Permission::where('name', 'product_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function count_stock(User $user)
    {
        $permission = Permission::where('name', 'count_stock')->first();

        return $user->hasRole($permission->roles);
    }

    public function inventory_valuation(User $user)
    {
        $permission = Permission::where('name', 'inventory_valuation')->first();

        return $user->hasRole($permission->roles);
    }

    public function opening_stock_import(User $user)
    {
        $permission = Permission::where('name', 'opening_stock_import')->first();

        return $user->hasRole($permission->roles);
    }

    public function zeroSalesProducts(User $user)
    {
        $permission = Permission::where('name', 'zeroSalesProducts')->first();

        return $user->hasRole($permission->roles);
    }

    public function Dead_Stock_Report(User $user)
    {
        $permission = Permission::where('name', 'Dead_Stock_Report')->first();

        return $user->hasRole($permission->roles);
    }

    public function Stock_Aging_Report(User $user)
    {
        $permission = Permission::where('name', 'Stock_Aging_Report')->first();

        return $user->hasRole($permission->roles);
    }

    public function Stock_Inventory_Valuation(User $user)
    {
        $permission = Permission::where('name', 'Stock_Inventory_Valuation')->first();

        return $user->hasRole($permission->roles);
    }

    public function batch_view(User $user)
    {
        $permission = Permission::where('name', 'batch_view')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function batch_manage(User $user)
    {
        $permission = Permission::where('name', 'batch_manage')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function batch_writeoff(User $user)
    {
        $permission = Permission::where('name', 'batch_writeoff')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function batch_force_override(User $user)
    {
        $permission = Permission::where('name', 'batch_force_override')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function expiry_report(User $user)
    {
        $permission = Permission::where('name', 'expiry_report')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function Batch_Register_Report(User $user)
    {
        $permission = Permission::where('name', 'Batch_Register_Report')->first();

        if (!$permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Product  $product
     * @return mixed
     */
    public function restore(User $user, Article $product)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Product  $product
     * @return mixed
     */
    public function forceDelete(User $user, Article $product)
    {
        //
    }
}
