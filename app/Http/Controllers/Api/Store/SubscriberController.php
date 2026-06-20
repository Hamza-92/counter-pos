<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Subscriber::class);

        $per = (int) $request->query('per_page', 10);
        $sort = $request->query('sort', 'created_at');
        $dir = $request->query('dir', 'desc');

        $q = Subscriber::query()
            ->orderBy($sort, $dir)
            ->paginate($per);

        $rows = collect($q->items())->map(function ($s) {
            return [
                'id' => $s->id,
                'email' => $s->email,
                'created_at' => $s->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $q->total()],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Subscriber::class);

        $s = Subscriber::findOrFail($id);
        $s->delete();

        return response()->json(['ok' => true]);
    }
}
