<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDocument extends Model
{
    protected $table = 'sale_documents';

    protected $fillable = [
        'sale_id', 'name', 'path', 'size', 'mime_type',
    ];

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}


















