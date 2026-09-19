<?php

namespace App\Http\Controllers\ControlPlane\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'service' => 'counterpos-control',
                'api_version' => 'v1',
                'status' => 'ok',
                'time' => now()->toISOString(),
            ],
        ]);
    }
}
