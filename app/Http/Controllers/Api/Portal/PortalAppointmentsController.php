<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\ServiceJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalAppointmentsController extends Controller
{
    /**
     * GET /api/portal/appointments - list this client's service-job appointments.
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $perPage = (int) $request->input('limit', 10);
        $page = max(1, (int) $request->input('page', 1));
        $search = $request->input('search');

        $q = ServiceJob::query()
            ->whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qr) use ($search) {
                    $qr->where('Ref', 'LIKE', "%{$search}%")
                        ->orWhere('service_item', 'LIKE', "%{$search}%")
                        ->orWhere('reported_issue', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id');

        $totalRows = (clone $q)->count();
        $rows = $perPage > 0
            ? $q->offset(($page - 1) * $perPage)->limit($perPage)->get()
            : $q->get();

        $data = $rows->map(function ($job) {
            return [
                'id' => $job->id,
                'Ref' => $job->Ref,
                'service_item' => $job->service_item,
                'job_type' => $job->job_type,
                'status' => $job->status,
                'scheduled_date' => optional($job->scheduled_date)->format('Y-m-d H:i'),
                'reported_issue' => $job->reported_issue,
                'total_amount' => (float) ($job->total_amount ?? 0),
                'paid_amount' => (float) ($job->paid_amount ?? 0),
                'payment_status' => $job->payment_status,
            ];
        });

        return response()->json([
            'totalRows' => $totalRows,
            'appointments' => $data,
        ]);
    }

    /**
     * GET /api/portal/appointments/{id} - appointment detail.
     */
    public function show(Request $request, $id)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $job = ServiceJob::whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where('id', $id)
            ->with(['technician:id,name'])
            ->firstOrFail();

        return response()->json([
            'id' => $job->id,
            'Ref' => $job->Ref,
            'service_item' => $job->service_item,
            'job_type' => $job->job_type,
            'status' => $job->status,
            'scheduled_date' => optional($job->scheduled_date)->format('Y-m-d H:i'),
            'started_at' => optional($job->started_at)->format('Y-m-d H:i'),
            'completed_at' => optional($job->completed_at)->format('Y-m-d H:i'),
            'created_at' => optional($job->created_at)->format('Y-m-d H:i'),
            'reported_issue' => $job->reported_issue,
            'diagnosis' => $job->diagnosis,
            'notes' => $job->notes,
            'device_brand' => $job->device_brand,
            'device_model' => $job->device_model,
            'device_serial' => $job->device_serial,
            'technician_name' => optional($job->technician)->name,
            'quote_amount' => (float) ($job->quote_amount ?? 0),
            'total_amount' => (float) ($job->total_amount ?? 0),
            'paid_amount' => (float) ($job->paid_amount ?? 0),
            'payment_status' => $job->payment_status,
        ]);
    }

    /**
     * POST /api/portal/appointments - client books a new appointment.
     * Creates a ServiceJob with status='pending' for admins to confirm/schedule.
     */
    public function store(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $data = $request->validate([
            'service_item' => ['required', 'string', 'max:190'],
            'job_type' => ['nullable', 'string', 'max:50'],
            'scheduled_date' => ['required', 'date'],
            'reported_issue' => ['nullable', 'string', 'max:5000'],
            'device_brand' => ['nullable', 'string', 'max:120'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'device_serial' => ['nullable', 'string', 'max:120'],
        ]);

        $year = date('Y');
        $prefix = "APPT_{$year}_";
        $lastRef = ServiceJob::where('Ref', 'LIKE', $prefix . '%')
            ->orderByDesc('id')->value('Ref');
        $next = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;
        $ref = sprintf('APPT_%s_%05d', $year, $next);

        // Normalize the datetime-local input ("2026-05-09T10:00") to MySQL DATETIME.
        // Storing the raw ISO string can land as 0000-00-00 in some MySQL strict modes,
        // which then makes the row invisible to whereDate filters and date-sorted lists.
        $scheduled = Carbon::parse($data['scheduled_date'])->format('Y-m-d H:i:s');

        $job = ServiceJob::create([
            'Ref' => $ref,
            'client_id' => (int) $portalClient->client_id,
            'service_item' => $data['service_item'],
            'job_type' => $data['job_type'] ?? 'service',
            'status' => 'pending',
            'scheduled_date' => $scheduled,
            'reported_issue' => $data['reported_issue'] ?? null,
            'device_brand' => $data['device_brand'] ?? null,
            'device_model' => $data['device_model'] ?? null,
            'device_serial' => $data['device_serial'] ?? null,
            'total_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        // Re-read from DB so the response reflects what was actually persisted —
        // gives the front-end something concrete to confirm visibility.
        $job = $job->fresh();

        return response()->json([
            'success' => true,
            'id' => $job->id,
            'Ref' => $job->Ref,
            'service_item' => $job->service_item,
            'status' => $job->status,
            'scheduled_date' => optional($job->scheduled_date)->format('Y-m-d H:i'),
        ], 201);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
