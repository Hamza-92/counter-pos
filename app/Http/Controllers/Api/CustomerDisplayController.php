<?php

namespace App\Http\Controllers\Api;

use App\Events\CartUpdated;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomerDisplayController extends Controller
{
    /**
     * Generate a temporary access token for the public customer display page.
     * The token is stored in cache for 24 hours and returned alongside the full URL
     * and an optional QR code SVG (if the QR library is installed).
     */
    public function generate(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'customer_display_screen_setup', Sale::class);

        $token = Str::random(40);
        // Store token for 24 hours
        cache(['customer_display_token' => $token], now()->addDay());

        $url = url('/customer-display').'?token='.$token;

        // Generate QR if the optional package is available; otherwise return null
        $qrSvg = null;
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($url);
        }

        return response()->json([
            'token' => $token,
            'url' => $url,
            'qr' => $qrSvg,
        ]);
    }

    /**
     * Persist the latest cart for polling fallback and broadcast to channel.
     * screen_id supports multiple displays (e.g. screen_id=1, 2, 3).
     */
    public function broadcastCart(Request $request)
    {
        $cart = $request->input('cart', []);
        $completed = (bool) $request->boolean('completed', false);
        $screenId = (string) ($request->input('screen_id', '1'));

        // Keep only safe shape
        $payload = [
            'currency' => $cart['currency'] ?? ($cart['symbol'] ?? ''),
            'discount' => (float) ($cart['discount'] ?? 0),
            'TaxNet' => (float) ($cart['TaxNet'] ?? ($cart['tax'] ?? 0)),
            'shipping' => (float) ($cart['shipping'] ?? 0),
            'GrandTotal' => (float) ($cart['GrandTotal'] ?? ($cart['total'] ?? 0)),
            'details' => array_map(function ($row) {
                return [
                    'name' => $row['name'] ?? $row['product_name'] ?? '',
                    'quantity' => (float) ($row['quantity'] ?? $row['Qty'] ?? $row['qte'] ?? 0),
                    'Net_price' => (float) ($row['Net_price'] ?? $row['price'] ?? 0),
                    'unit_price' => (float) ($row['unit_price'] ?? ($row['Net_price'] ?? $row['price'] ?? 0)),
                    'line_total' => (float) ($row['line_total'] ?? $row['total'] ?? $row['subtotal'] ?? 0),
                    'subtotal' => (float) ($row['total'] ?? $row['subtotal'] ?? 0),
                ];
            }, is_array($cart['details'] ?? null) ? $cart['details'] : ($cart['items'] ?? [])),
        ];

        $key = 'customer_display:last_cart:'.$screenId;
        Cache::put($key, [
            'cart' => $payload,
            'completed' => $completed,
            'ts' => now()->timestamp,
        ], now()->addMinutes(2));

        event(new CartUpdated($payload, $completed, $screenId));

        return response()->json(['ok' => true]);
    }

    /** Return the last cart for polling clients. ?screen=1|2|... */
    public function lastCart(Request $request)
    {
        $screenId = (string) ($request->query('screen', '1'));
        $data = Cache::get('customer_display:last_cart:'.$screenId);
        if (! $data) {
            return response()->json(['cart' => null, 'completed' => false]);
        }

        return response()->json($data);
    }
}
