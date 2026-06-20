<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractTask extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at', 'due_date'];

    protected $fillable = ['contract_id', 'title', 'due_date', 'status', 'description'];

    protected $casts = [
        'contract_id' => 'integer',
        'due_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
    }
}
