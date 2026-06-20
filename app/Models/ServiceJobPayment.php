<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceJobPayment extends Model
{
    use HasFactory;

    protected $table = 'service_job_payments';

    protected $fillable = [
        'Ref',
        'service_job_id',
        'user_id',
        'payment_method_id',
        'account_id',
        'date',
        'montant',
        'change',
        'payment_kind',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'date' => 'datetime',
        'deleted_at' => 'datetime',
        'montant' => 'float',
        'change' => 'float',
        'service_job_id' => 'integer',
        'user_id' => 'integer',
        'payment_method_id' => 'integer',
        'account_id' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payment_method()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
