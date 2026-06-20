<?php

return [
    // NEW FEATURE - SAFE ADDITION
    // Toggle to enable/disable Accounting V2 without impacting legacy modules
    'enabled' => env('ACCOUNTING_V2_ENABLED', true),

    // Default codes for special accounts; created on demand if missing
    'codes' => [
        'root_assets' => '1000',
        'root_liabilities' => '2000',
        'root_equity' => '3000',
        'root_income' => '4000',
        'root_expense' => '5000',
        'retained_earnings' => '3100',
    ],

    // Toggle automatic journal generation from operational events (sales, purchases, payments, etc.)
    'auto_generate_journals' => env('ACCOUNTING_V2_AUTO_JOURNALS', false),

    // Auto-post generated entries immediately
    'auto_post_journals' => true,

    // Records created strictly before this date are ignored by automation.
    // Protects historical data when enabling the module on a live database.
    'baseline_date' => env('ACCOUNTING_V2_BASELINE_DATE'),
];
