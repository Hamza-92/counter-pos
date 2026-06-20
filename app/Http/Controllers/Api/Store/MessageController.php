<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string',
            'company' => 'nullable|string', // honeypot if you want
        ]);

        // Optional honeypot: if filled, pretend success
        if ($request->filled('company')) {
            return response()->json(['ok' => true, 'message' => __('Thanks! If needed, we will get back to you.')]);
        }

        Message::create($data);

        // Always JSON for this endpoint when used via fetch
        return response()->json(['ok' => true, 'message' => __('Your message has been sent successfully!')]);
    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Message::class);

        $per = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $sort = $request->query('sort', 'created_at');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $q = trim((string) $request->query('q', ''));
        $unread = $request->boolean('unread', false);

        $query = Message::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        if ($unread) {
            $query->where('is_read', false);
        }

        // Whitelist sortable cols (avoid SQL injection on column)
        $sortable = ['created_at', 'updated_at', 'name', 'email', 'is_read'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $p = $query->orderBy($sort, $dir)->paginate($per, ['*'], 'page', $page);

        // Return compact rows for the table
        $rows = collect($p->items())->map(function (Message $m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'subject' => $m->subject,
                'is_read' => (bool) $m->is_read,
                'created_at' => optional($m->created_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current' => $p->currentPage(),
                'last_page' => $p->lastPage(),
            ],
        ]);
    }

    // GET /admin/messages/{id}  (show + mark as read)
    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Message::class);

        $msg = Message::findOrFail($id);

        if (! $msg->is_read) {
            $msg->is_read = true;
            $msg->save();
        }

        return response()->json([
            'id' => $msg->id,
            'name' => $msg->name,
            'email' => $msg->email,
            'phone' => $msg->phone,
            'subject' => $msg->subject,
            'message' => $msg->message,
            'is_read' => (bool) $msg->is_read,
            'created_at' => optional($msg->created_at)->toDateTimeString(),
        ]);
    }

    // PATCH /admin/messages/{id}/toggle-read  (optional helper)
    public function toggleRead(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Message::class);

        $msg = Message::findOrFail($id);
        $msg->is_read = ! $msg->is_read;
        $msg->save();

        return response()->json(['ok' => true, 'is_read' => (bool) $msg->is_read]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Message::class);
        Message::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
