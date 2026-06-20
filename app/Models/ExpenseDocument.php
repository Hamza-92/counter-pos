<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseDocument extends Model
{
    protected $table = 'expense_documents';

    protected $fillable = [
        'expense_id',
        'name',
        'path',
        'size',
        'mime_type',
    ];

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }
}


















