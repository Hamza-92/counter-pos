<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceJobPolicy
{
    use HandlesAuthorization;

    protected function checkPermission(User $user): bool
    {
        return $this->hasPermission($user, 'service_jobs');
    }

    protected function hasPermission(User $user, string $permissionName): bool
    {
        $permission = Permission::where('name', $permissionName)->first();

        return $permission ? $user->hasRole($permission->roles) : false;
    }

    public function view(User $user): bool
    {
        return $this->checkPermission($user);
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user);
    }

    public function update(User $user): bool
    {
        return $this->checkPermission($user);
    }

    public function delete(User $user): bool
    {
        return $this->checkPermission($user);
    }

    public function service_jobs_report(User $user): bool
    {
        return $this->hasPermission($user, 'service_jobs_report');
    }

    public function checklist_completion_report(User $user): bool
    {
        return $this->hasPermission($user, 'checklist_completion_report');
    }

    public function customer_maintenance_history_report(User $user): bool
    {
        return $this->hasPermission($user, 'customer_maintenance_history_report');
    }
}


