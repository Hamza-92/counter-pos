<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'opening_balance',
        'closing_balance',
        'total_sales',
        'cash_in',
        'cash_out',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'warehouse_id' => 'integer',
        'opening_balance' => 'double',
        'closing_balance' => 'double',
        'total_sales' => 'double',
        'cash_in' => 'double',
        'cash_out' => 'double',
        'difference' => 'double',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
