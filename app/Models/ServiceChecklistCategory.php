<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceChecklistCategory extends Model
{
    use HasFactory;

    protected $table = 'service_checklist_categories';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ServiceChecklistItem::class, 'category_id');
    }
}

















