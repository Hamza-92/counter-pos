<?php

namespace App\Models\AccountingV2;

use Illuminate\Database\Eloquent\Model;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class JournalEntryLine extends Model
{
    protected $table = 'acc_journal_entry_lines';

    protected $fillable = [
        'journal_entry_id', 'coa_id', 'account_id', 'debit', 'credit', 'memo',
    ];

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}





