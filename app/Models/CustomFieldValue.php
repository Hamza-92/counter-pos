<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFieldValue extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'custom_field_id',
        'entity_id',
        'entity_type',
        'value',
    ];

    /**
     * Get the custom field that owns this value
     */
    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    /**
     * Get the parent entity (Client or Provider)
     */
    public function entity()
    {
        return $this->morphTo();
    }
}
