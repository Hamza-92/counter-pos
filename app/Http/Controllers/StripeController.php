<?php

namespace App\Http\Controllers;

use App\Models\PaymentWithCreditCard;
use Illuminate\Http\Request;
use Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
    }

    /**
     * Saved-card retrieval is no longer supported for payments.
     * This endpoint is retained for backward compatibility but always returns an empty list.
     */
    public function retrieveCustomer(Request $request)
    {
        return response()->json([
            'data' => [],
            'customer_default_source' => null,
        ], 200);
    }
    

    /**
     * Updating default saved cards is no longer supported.
     * This method is kept to avoid breaking routes but performs no Stripe mutations.
     */
    public function updateCustomer(Request $request)
    {
        return response()->json(['success' => true], 200);
    }
}
