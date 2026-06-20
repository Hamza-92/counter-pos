<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractRenewal extends Model
{
    protected $fillable = [
        'contract_id', 'renewed_from_contract_id', 'renewal_date', 'new_end_date', 'notes',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'renewed_from_contract_id' => 'integer',
        'renewal_date' => 'date',
        'new_end_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(Contract::class, 'renewed_from_contract_id', 'id');
    }
}
