<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\IncomingWebhookService;
use Illuminate\Http\Request;

class IncomingWebhooksController extends Controller
{
    public function __construct(protected IncomingWebhookService $service)
    {
    }

    public function handle(Request $request, string $source)
    {
        $log = $this->service->receive($request, $source);

        if (!$log->signature_valid) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        return response()->json([
            'success' => true,
            'log_id'  => $log->id,
            'status'  => $log->status,
        ]);
    }
}
