<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = ['id'];

    protected $fillable = ['name', 'status', 'label', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function givePermissionTo(Permission $permission)
    {
        return $this->permissions()->save($permission);
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @return bool
     */
    public function hasPermission(Permission $permission, User $user)
    {
        return $this->hasRole($permission->roles);
    }

    /**
     * Determine if the role has the given permission.
     *
     * @param  mixed  $permission
     * @return bool
     */
    public function inRole($permission)
    {
        if (is_string($permission)) {
            return $this->permissions->contains('name', $permission);
        }

        return (bool) $permission->intersect($this->permissions)->count();
    }
}
