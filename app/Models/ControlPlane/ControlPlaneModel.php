<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Model;

abstract class ControlPlaneModel extends Model
{
    protected $connection = 'control';
}
