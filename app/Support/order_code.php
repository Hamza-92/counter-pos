<?php

if (! function_exists('generate_order_code')) {
    function generate_order_code(): string
    {
        $prefix = 'SO-'.now()->format('Ymd').'-';
        $last = \App\Models\StoreOrder::where('code', 'like', $prefix.'%')
            ->orderBy('id', 'desc')->value('code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
