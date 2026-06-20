<?php

namespace App\Support;

use Carbon\Carbon;

class ZatcaQr
{
    /**
     * Build a TLV segment with tag and UTF-8 value.
     */
    protected static function tlv(int $tag, string $value): string
    {
        $utf8 = mb_convert_encoding($value ?? '', 'UTF-8', 'UTF-8');
        $len = strlen($utf8); // bytes length

        return chr($tag).chr($len).$utf8;
    }

    /**
     * Generate Base64 of ZATCA Phase 1 TLV payload.
     * Fields: 1 Seller Name, 2 VAT Number, 3 Timestamp (ISO 8601), 4 Total With VAT, 5 VAT Amount
     */
    public static function generate(string $sellerName, string $vatNumber, string $timestampIso8601, string $totalWithVat, string $vatAmount): string
    {
        $payload =
            self::tlv(1, $sellerName).
            self::tlv(2, $vatNumber).
            self::tlv(3, $timestampIso8601).
            self::tlv(4, self::normalizeAmount($totalWithVat)).
            self::tlv(5, self::normalizeAmount($vatAmount));

        return base64_encode($payload);
    }

    /**
     * Convert amount strings to normalized 2-decimal dot format.
     */
    protected static function normalizeAmount(string $amount): string
    {
        // Remove thousands separators and unify decimals
        $normalized = str_replace([',', ' '], ['', ''], $amount);
        if (! is_numeric($normalized)) {
            // Fallback for locales using comma
            $normalized = str_replace(',', '.', $amount);
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * Ensure a timestamp string is ISO 8601 (with timezone).
     */
    public static function toIso8601(string $dateTime, ?string $tz = null): string
    {
        $carbon = $tz ? Carbon::parse($dateTime, $tz) : Carbon::parse($dateTime);

        return $carbon->toIso8601String();
    }
}





