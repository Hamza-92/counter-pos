<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentOpeningBalance extends Model
{
    use SoftDeletes;

    protected $table = 'client_opening_balance_payments';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'client_id', 'date', 'montant', 'Ref', 'change', 'payment_method_id', 'user_id', 'notes', 'account_id',
    ];

    protected $casts = [
        'montant' => 'double',
        'change' => 'double',
        'client_id' => 'integer',
        'user_id' => 'integer',
        'account_id' => 'integer',
        'payment_method_id' => 'integer',
    ];

    public function payment_method()
    {
        return $this->belongsTo('App\Models\PaymentMethod');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }
}
