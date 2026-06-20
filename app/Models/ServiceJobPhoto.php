<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceJobPhoto extends Model
{
    use HasFactory;

    protected $table = 'service_job_photos';

    protected $dates = [
        'deleted_at',
    ];

    protected $fillable = [
        'service_job_id',
        'user_id',
        'stage',
        'path',
        'original_name',
        'mime_type',
        'size',
        'caption',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'service_job_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
