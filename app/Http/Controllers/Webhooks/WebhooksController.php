<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\BaseController;
use App\Jobs\Webhooks\WebhookDeliveryJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookIncomingLog;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookService;
use Illuminate\Http\Request;

class WebhooksController extends BaseController
{
    public function __construct(protected WebhookService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);

        $perPage = (int) ($request->limit ?: 10);
        $page = (int) \Request::get('page', 1);
        $offset = ($page * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = $request->SortType ?: 'desc';

        $query = Webhook::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('url', 'LIKE', "%{$request->search}%");
                });
            });

        $totalRows = $query->count();
        if ($perPage === -1) {
            $perPage = $totalRows ?: 1;
        }

        $webhooks = $query->orderBy($order, $dir)->offset($offset)->limit($perPage)->get();

        return response()->json([
            'webhooks'  => $webhooks,
            'totalRows' => $totalRows,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Webhook::class);

        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'url'             => 'required|url|max:2048',
            'events'          => 'required|array|min:1',
            'events.*'        => 'string|max:100',
            'headers'         => 'nullable|array',
            'is_active'       => 'boolean',
            'timeout_seconds' => 'nullable|integer|min:1|max:60',
        ]);

        $data['user_id'] = optional($request->user('api'))->id;

        $webhook = $this->service->create($data);

        return response()->json([
            'success' => true,
            'webhook' => $webhook,
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Webhook::class);
        $webhook = Webhook::findOrFail($id);
        return response()->json(['webhook' => $webhook]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Webhook::class);

        $webhook = Webhook::findOrFail($id);

        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:150',
            'url'             => 'sometimes|required|url|max:2048',
            'events'          => 'sometimes|required|array|min:1',
            'events.*'        => 'string|max:100',
            'headers'         => 'nullable|array',
            'is_active'       => 'sometimes|boolean',
            'timeout_seconds' => 'nullable|integer|min:1|max:60',
        ]);

        $webhook = $this->service->update($webhook, $data);

        return response()->json(['success' => true, 'webhook' => $webhook]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Webhook::class);
        $webhook = Webhook::findOrFail($id);
        $webhook->delete();
        return response()->json(['success' => true]);
    }

    public function toggle(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Webhook::class);
        $webhook = Webhook::findOrFail($id);
        $this->service->toggle($webhook);
        return response()->json(['success' => true, 'is_active' => $webhook->is_active]);
    }

    public function regenerateSecret(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Webhook::class);
        $webhook = Webhook::findOrFail($id);
        $this->service->regenerateSecret($webhook);
        return response()->json(['success' => true, 'secret' => $webhook->secret]);
    }

    public function test(Request $request, $id, WebhookDispatcher $dispatcher)
    {
        $this->authorizeForUser($request->user('api'), 'update', Webhook::class);
        $webhook = Webhook::findOrFail($id);

        $payload = [
            'test' => true,
            'message' => 'This is a test event from Stocky.',
            'timestamp' => now()->toIso8601String(),
            'user_id' => optional($request->user('api'))->id,
        ];

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event'      => 'webhook.test',
            'status'     => WebhookDelivery::STATUS_PENDING,
            'attempt'    => 0,
            'payload'    => json_encode([
                'id'         => (string) \Illuminate\Support\Str::uuid(),
                'event'      => 'webhook.test',
                'created_at' => now()->toIso8601String(),
                'data'       => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        WebhookDeliveryJob::dispatch($delivery->id)->onQueue('webhooks');

        return response()->json(['success' => true, 'delivery_id' => $delivery->id]);
    }

    public function availableEvents(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);
        return response()->json(['events' => WebhookService::availableEvents()]);
    }

    public function deliveries(Request $request, $id = null)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);

        $perPage = (int) ($request->limit ?: 15);
        $page = (int) \Request::get('page', 1);
        $offset = ($page * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = $request->SortType ?: 'desc';

        $query = WebhookDelivery::query()->with(['webhook:id,name,url']);
        if ($id) {
            $query->where('webhook_id', $id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event', 'LIKE', "%{$search}%")
                    ->orWhere('error_message', 'LIKE', "%{$search}%");
            });
        }

        $totalRows = $query->count();
        if ($perPage === -1) {
            $perPage = $totalRows ?: 1;
        }

        $rows = $query->orderBy($order, $dir)->offset($offset)->limit($perPage)->get();

        return response()->json(['deliveries' => $rows, 'totalRows' => $totalRows]);
    }

    public function deliveryShow(Request $request, $deliveryId)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);
        $row = WebhookDelivery::with('webhook:id,name,url')->findOrFail($deliveryId);
        return response()->json(['delivery' => $row]);
    }

    public function incomingLogs(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);

        $perPage = (int) ($request->limit ?: 15);
        $page = (int) \Request::get('page', 1);
        $offset = ($page * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = $request->SortType ?: 'desc';

        $query = WebhookIncomingLog::query();
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('event', 'LIKE', "%{$request->search}%");
        }

        $totalRows = $query->count();
        if ($perPage === -1) {
            $perPage = $totalRows ?: 1;
        }

        $rows = $query->orderBy($order, $dir)->offset($offset)->limit($perPage)->get();

        return response()->json(['logs' => $rows, 'totalRows' => $totalRows]);
    }

    public function incomingLogShow(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', Webhook::class);
        $log = WebhookIncomingLog::findOrFail($id);
        return response()->json(['log' => $log]);
    }
}
