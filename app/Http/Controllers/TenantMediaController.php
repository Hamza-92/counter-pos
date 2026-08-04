<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantMediaController extends Controller
{
    public function show(Request $request, string $area, string $path): BinaryFileResponse
    {
        abort_unless(config('tenancy.enabled', false), 404);
        abort_unless(in_array($area, ['images', 'flags'], true), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, '://'), 404);
        abort_if(preg_match('/\.(php\d*|phtml|phar|cgi|pl|sh|exe|com|bat|cmd|htaccess)$/i', $path), 404);

        $file = tenant_public_path($area.'/'.$path);
        abort_unless(is_file($file), 404);

        return response()->file($file, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.basename($file).'"',
        ]);
    }
}
