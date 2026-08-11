<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
    
        $response = $next($request);
    
        // Safe for ALL response types (including images/files)
        if (method_exists($response, 'header')) {
            return $response->header('X-Request-Id', $requestId);
        }
    
        // Fallback for BinaryFileResponse
        $response->headers->set('X-Request-Id', $requestId);
    
        return $response;
    }
}
