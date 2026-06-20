<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionReceipt extends Model
{
    use SoftDeletes;
    protected $table = 'commission_receipts';

    protected $dates = ['deleted_at', 'paid_at'];

    protected $fillable = [
        'sales_agent_id', 'Ref', 'amount', 'paid_at', 'payment_method_id', 'notes',
    ];

    protected $casts = [
        'sales_agent_id' => 'integer',
        'payment_method_id' => 'integer',
        'amount' => 'decimal:4',
        'paid_at' => 'date',
    ];

    public function salesAgent()
    {
        return $this->belongsTo(SalesAgent::class, 'sales_agent_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function saleCommissions()
    {
        return $this->hasMany(SaleCommission::class, 'commission_receipt_id');
    }
}
