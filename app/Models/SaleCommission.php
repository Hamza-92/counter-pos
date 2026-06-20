<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleCommission extends Model
{
    use SoftDeletes;
    protected $table = 'sale_commissions';

    protected $dates = ['deleted_at', 'calculated_at'];

    protected $fillable = [
        'sale_id', 'sales_agent_id', 'commission_program_id', 'commission_rule_id',
        'base_amount', 'commission_amount', 'status', 'commission_receipt_id', 'calculated_at', 'notes',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'sales_agent_id' => 'integer',
        'commission_program_id' => 'integer',
        'commission_rule_id' => 'integer',
        'commission_receipt_id' => 'integer',
        'base_amount' => 'decimal:4',
        'commission_amount' => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function salesAgent()
    {
        return $this->belongsTo(SalesAgent::class, 'sales_agent_id');
    }

    public function commissionProgram()
    {
        return $this->belongsTo(CommissionProgram::class, 'commission_program_id');
    }

    public function commissionRule()
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function commissionReceipt()
    {
        return $this->belongsTo(CommissionReceipt::class, 'commission_receipt_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
