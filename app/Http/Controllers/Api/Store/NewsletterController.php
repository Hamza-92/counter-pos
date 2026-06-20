<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:190|unique:subscribers,email',
        ]);

        $sub = Subscriber::create($data);

        return response()->json([
            'success' => true,
            'message' => __('Subscribed successfully'),
            'subscriber' => $sub,
        ]);
    }
}
