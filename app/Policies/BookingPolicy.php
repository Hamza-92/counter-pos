<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    /**
     * Resolve the 'bookings' permission and check if the user has it.
     */
    protected function checkPermission(User $user): bool
    {
        $permission = Permission::where('name', 'bookings')->first();

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
}












