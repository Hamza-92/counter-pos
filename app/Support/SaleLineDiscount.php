<?php

namespace App\Support;

class SaleLineDiscount
{
    /**
     * Normalize the legacy discount method values used across sale endpoints.
     * Product discounts historically default to percentage (method 1).
     */
    public static function normalizeMethod($method): string
    {
        $value = strtolower(trim((string) $method));

        if (in_array($value, ['2', 'fixed', 'amount', 'value'], true)) {
            return '2';
        }

        return '1';
    }

    /**
     * Return display-only product discount values for a sale detail.
     * Amounts are calculated from the persisted rule; sale totals are untouched.
     */
    public static function forDetail($detail): array
    {
        $method = self::normalizeMethod($detail->discount_method ?? null);
        $price = max(0, (float) ($detail->price ?? 0));
        $quantity = max(0, (float) ($detail->quantity ?? 0));
        $value = max(0, (float) ($detail->discount ?? 0));

        $unitAmount = $method === '2'
            ? $value
            : ($price * $value / 100);

        return [
            'discount_method' => $method,
            'discount_value' => $value,
            // Keep extra precision for fractional quantities; format only in the UI.
            'discount_unit_amount' => round($unitAmount, 6),
            'discount_line_amount' => round($unitAmount * $quantity, 2),
        ];
    }
}
