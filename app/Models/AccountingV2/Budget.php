<?php

namespace App\Models\AccountingV2;

use Illuminate\Database\Eloquent\Model;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class Budget extends Model
{
    protected $table = 'acc_budgets';

    protected $fillable = [
        'name', 'start_date', 'end_date', 'currency', 'notes', 'is_active',
    ];

    public function lines()
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }
}





