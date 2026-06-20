<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDocument extends Model
{
    protected $table = 'purchase_documents';

    protected $fillable = [
        'purchase_id', 'name', 'path', 'size', 'mime_type'
    ];

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function purchase()
    {
        return $this->belongsTo('App\Models\Purchase');
    }
}

















