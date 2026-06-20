<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomField extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'field_type',
        'entity_type',
        'is_required',
        'is_active',
        'default_value',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'default_value' => 'array', // For select fields, store as JSON
    ];

    /**
     * Get all values for this custom field
     */
    public function values()
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }

    /**
     * Get select options (for select field type)
     */
    public function getSelectOptionsAttribute()
    {
        if ($this->field_type === 'select' && $this->default_value) {
            return is_array($this->default_value) ? $this->default_value : json_decode($this->default_value, true);
        }
        return [];
    }
}
