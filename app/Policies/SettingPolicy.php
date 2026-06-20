<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
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
     * @param  \App\Models\Setting  $setting
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'setting_system')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'setting_system')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Setting  $setting
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'setting_system')->first();

        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Setting  $setting
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'setting_system')->first();

        return $user->hasRole($permission->roles);
    }

    public function sms_settings(User $user)
    {
        $smsPermission = Permission::where('name', 'sms_settings')->first();
        $systemPermission = Permission::where('name', 'setting_system')->first();

        if (
            ($smsPermission && $user->hasRole($smsPermission->roles)) ||
            ($systemPermission && $user->hasRole($systemPermission->roles))
        ) {
            return true;
        }

        return false;
    }

    public function pos_settings(User $user)
{
    $posPermission = Permission::where('name', 'pos_settings')->first();
    $systemPermission = Permission::where('name', 'setting_system')->first();

    if (
        ($posPermission && $user->hasRole($posPermission->roles)) ||
        ($systemPermission && $user->hasRole($systemPermission->roles))
    ) {
        return true;
    }

    return false;
}

public function payment_gateway(User $user)
{
    $paymentPermission = Permission::where('name', 'payment_gateway')->first();
    $systemPermission = Permission::where('name', 'setting_system')->first();

    if (
        ($paymentPermission && $user->hasRole($paymentPermission->roles)) ||
        ($systemPermission && $user->hasRole($systemPermission->roles))
    ) {
        return true;
    }

    return false;
}

public function mail_settings(User $user)
{
    $mailPermission = Permission::where('name', 'mail_settings')->first();
    $systemPermission = Permission::where('name', 'setting_system')->first();

    if (
        ($mailPermission && $user->hasRole($mailPermission->roles)) ||
        ($systemPermission && $user->hasRole($systemPermission->roles))
    ) {
        return true;
    }

    return false;
}




    public function module_settings(User $user)
    {
        $permission = Permission::where('name', 'module_settings')->first();

        return $user->hasRole($permission->roles);
    }

    public function notification_template(User $user)
    {
        $permission = Permission::where('name', 'notification_template')->first();

        return $user->hasRole($permission->roles);
    }

    public function appearance_settings(User $user)
    {
        $appearancePermission = Permission::where('name', 'appearance_settings')->first();
        $systemPermission = Permission::where('name', 'setting_system')->first();
    
        if (
            ($appearancePermission && $user->hasRole($appearancePermission->roles)) ||
            ($systemPermission && $user->hasRole($systemPermission->roles))
        ) {
            return true;
        }
    
        return false;
    }
    

    public function translations_settings(User $user)
    {
        $permission = Permission::where('name', 'translations_settings')->first();

        return $user->hasRole($permission->roles);
    }

    public function login_device_management(User $user)
    {
        $loginDevicePermission = Permission::where('name', 'login_device_management')->first();
        $systemPermission = Permission::where('name', 'setting_system')->first();
    
        if (
            ($loginDevicePermission && $user->hasRole($loginDevicePermission->roles)) ||
            ($systemPermission && $user->hasRole($systemPermission->roles))
        ) {
            return true;
        }
    
        return false;
    }
    
    
    public function report_device_management(User $user)
    {
        $permission = Permission::where('name', 'report_device_management')->first();

        return $user->hasRole($permission->roles);
    }

    public function update_settings(User $user)
    {
        $permission = Permission::where('name', 'update_settings')->first();

        return $user->hasRole($permission->roles);
    }

    public function webhooks(User $user)
    {
        $webhookPermission = Permission::where('name', 'webhooks_view')->first();
        $systemPermission = Permission::where('name', 'setting_system')->first();

        if (
            ($webhookPermission && $user->hasRole($webhookPermission->roles)) ||
            ($systemPermission && $user->hasRole($systemPermission->roles))
        ) {
            return true;
        }

        return false;
    }


    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Setting  $setting
     * @return mixed
     */
    public function restore(User $user)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Setting  $setting
     * @return mixed
     */
    public function forceDelete(User $user)
    {
        //
    }
}
