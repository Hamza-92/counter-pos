<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreContact extends Model
{
    protected $table = 'store_contact';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'company_name', 'company_adress', 'company_email', 'company_phone', 'company_description',
    ];
}
