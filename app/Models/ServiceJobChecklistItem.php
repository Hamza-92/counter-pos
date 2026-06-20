<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceJobChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'service_job_checklist_items';

    protected $dates = [
        'completed_at',
        'deleted_at',
    ];

    protected $fillable = [
        'service_job_id',
        'category_id',
        'item_id',
        'category_name',
        'item_name',
        'is_completed',
        'completed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function job()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceChecklistCategory::class, 'category_id');
    }

    public function item()
    {
        return $this->belongsTo(ServiceChecklistItem::class, 'item_id');
    }
}

















