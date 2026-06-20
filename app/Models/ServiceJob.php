<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceJob extends Model
{
    use HasFactory;

    protected $table = 'service_jobs';

    protected $dates = [
        'scheduled_date',
        'started_at',
        'completed_at',
        'quote_valid_until',
        'quote_approved_at',
        'warranty_expires_at',
        'delivered_at',
        'deleted_at',
    ];

    protected $fillable = [
        'Ref',
        'client_id',
        'technician_id',
        'service_item',
        'job_type',
        'status',
        'scheduled_date',
        'started_at',
        'completed_at',
        'notes',

        // Device identity
        'device_brand',
        'device_model',
        'device_serial',
        'device_imei',
        'device_color',
        'device_password',
        'accessories',

        // Intake / diagnostic
        'condition_on_arrival',
        'reported_issue',
        'diagnosis',
        'diagnostic_fee',

        // Quote
        'quote_amount',
        'quote_valid_until',
        'quote_approved_at',
        'quote_approved_by',

        // Totals & payment
        'total_amount',
        'paid_amount',
        'payment_status',

        // Warranty
        'warranty_days',
        'warranty_expires_at',
        'parent_job_id',
        'quotation_id',

        // Delivery
        'delivered_at',
        'pickup_signature',

        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'accessories' => 'array',
        'diagnostic_fee' => 'float',
        'quote_amount' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'warranty_days' => 'integer',
        'client_id' => 'integer',
        'technician_id' => 'integer',
        'parent_job_id' => 'integer',
        'quotation_id' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function technician()
    {
        return $this->belongsTo(ServiceTechnician::class, 'technician_id');
    }

    public function checklistItems()
    {
        return $this->hasMany(ServiceJobChecklistItem::class, 'service_job_id');
    }

    public function items()
    {
        return $this->hasMany(ServiceJobItem::class, 'service_job_id');
    }

    public function payments()
    {
        return $this->hasMany(ServiceJobPayment::class, 'service_job_id');
    }

    public function photos()
    {
        return $this->hasMany(ServiceJobPhoto::class, 'service_job_id');
    }

    public function parentJob()
    {
        return $this->belongsTo(ServiceJob::class, 'parent_job_id');
    }

    public function warrantyClaims()
    {
        return $this->hasMany(ServiceJob::class, 'parent_job_id');
    }

    public function getBalanceDueAttribute(): float
    {
        return (float) ($this->total_amount ?? 0) - (float) ($this->paid_amount ?? 0);
    }
}
