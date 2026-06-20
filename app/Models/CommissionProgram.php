<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionProgram extends Model
{
    use SoftDeletes;
    protected $table = 'commission_programs';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'description', 'is_active', 'valid_from', 'valid_to',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function commissionRules()
    {
        return $this->hasMany(CommissionRule::class, 'commission_program_id');
    }

    public function saleCommissions()
    {
        return $this->hasMany(SaleCommission::class, 'commission_program_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidAt($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
        });
    }
}
