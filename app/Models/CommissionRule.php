<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionRule extends Model
{
    use SoftDeletes;
    protected $table = 'commission_rules';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'commission_program_id', 'name', 'type', 'source', 'value',
        'min_threshold', 'max_cap', 'applies_to', 'sales_agent_id', 'priority', 'is_active',
    ];

    protected $casts = [
        'commission_program_id' => 'integer',
        'sales_agent_id' => 'integer',
        'value' => 'decimal:4',
        'min_threshold' => 'decimal:4',
        'max_cap' => 'decimal:4',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function commissionProgram()
    {
        return $this->belongsTo(CommissionProgram::class, 'commission_program_id');
    }

    public function salesAgent()
    {
        return $this->belongsTo(SalesAgent::class, 'sales_agent_id');
    }

    public function saleCommissions()
    {
        return $this->hasMany(SaleCommission::class, 'commission_rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent($query, $salesAgentId)
    {
        return $query->where(function ($q) use ($salesAgentId) {
            $q->where('applies_to', 'all_agents')
                ->orWhere('sales_agent_id', $salesAgentId);
        });
    }
}
