<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesAgent extends Model
{
    use SoftDeletes;
    protected $table = 'sales_agents';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'user_id', 'code', 'name', 'email', 'phone', 'is_active', 'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commissionRules()
    {
        return $this->hasMany(CommissionRule::class, 'sales_agent_id');
    }

    public function saleCommissions()
    {
        return $this->hasMany(SaleCommission::class, 'sales_agent_id');
    }

    public function commissionReceipts()
    {
        return $this->hasMany(CommissionReceipt::class, 'sales_agent_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'sales_agent_id');
    }
}
