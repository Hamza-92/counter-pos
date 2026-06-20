<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'sort_order',
        'limit',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'limit' => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'collection_product')
            ->withPivot(['sort_order', 'pinned'])
            ->withTimestamps()
            ->orderBy('collection_product.sort_order');
    }
}
