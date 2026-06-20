<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractNote extends Model
{
    protected $fillable = ['contract_id', 'user_id', 'content'];

    protected $casts = ['contract_id' => 'integer', 'user_id' => 'integer'];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
