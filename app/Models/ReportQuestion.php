<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportQuestion extends Model
{
    protected $fillable = [
        'title',
        'report_key',
        'default_filters',
        'default_compare',
        'needs_insights',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'default_filters' => 'array',
        'default_compare' => 'array',
        'needs_insights' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active questions
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id')->orderBy('title');
    }
}
