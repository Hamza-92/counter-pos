<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
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
     * @param  \App\Models\ExpenseCategory  $expenseCategory
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'account')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'account')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ExpenseCategory  $expenseCategory
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'account')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ExpenseCategory  $expenseCategory
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'account')->first();

        return $user->hasRole($permission->roles);
    }

    public function accounting_dashboard(User $user)
    {
        $permission = Permission::where('name', 'accounting_dashboard')->first();

        return $user->hasRole($permission->roles);
    }

    public function chart_of_accounts(User $user)
    {
        $permission = Permission::where('name', 'chart_of_accounts')->first();

        return $user->hasRole($permission->roles);
    }

    public function journal_entries(User $user)
    {
        $permission = Permission::where('name', 'journal_entries')->first();

        return $user->hasRole($permission->roles);
    }

    public function trial_balance(User $user)
    {
        $permission = Permission::where('name', 'trial_balance')->first();

        return $user->hasRole($permission->roles);
    }

    public function accounting_profit_loss(User $user)
    {
        $permission = Permission::where('name', 'accounting_profit_loss')->first();

        return $user->hasRole($permission->roles);
    }

    public function balance_sheet(User $user)
    {
        $permission = Permission::where('name', 'balance_sheet')->first();

        return $user->hasRole($permission->roles);
    }

    public function accounting_tax_report(User $user)
    {
        $permission = Permission::where('name', 'accounting_tax_report')->first();

        return $user->hasRole($permission->roles);
    }

    public function check_record(User $user, $expenseCategory)
    {
        return $user->id === $expenseCategory->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\ExpenseCategory  $expenseCategory
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\ExpenseCategory  $expenseCategory
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
