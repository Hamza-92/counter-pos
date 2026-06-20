<?php

namespace App\Models\AccountingV2;

use Illuminate\Database\Eloquent\Model;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class JournalEntry extends Model
{
    protected $table = 'acc_journal_entries';

    protected $fillable = [
        'date', 'description', 'reference_type', 'reference_id', 'status', 'posted_at', 'created_by',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }
}





