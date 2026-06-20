<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    public function index(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', ErrorLog::class);

        $perPage = $request->per_page ?? 10;

        $logs = ErrorLog::orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'logs' => $logs->items(),
            'total' => $logs->total(),
        ]);
    }
}
