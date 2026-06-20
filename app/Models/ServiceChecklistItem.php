<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'service_checklist_items';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceChecklistCategory::class, 'category_id');
    }
}

















