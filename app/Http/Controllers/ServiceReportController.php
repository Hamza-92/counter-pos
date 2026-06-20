<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\ServiceJobChecklistItem;
use App\Models\ServiceTechnician;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceReportController extends BaseController
{
    // -------- Service Job Report (by type, technician, status) --------\\
    public function serviceJobs(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'service_jobs_report', ServiceJob::class);

        $perPage = $request->limit ?: 10;
        $pageStart = (int) $request->get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?: 'scheduled_date';
        $dir = strtolower($request->SortType ?: 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $sortableMap = [
            'id' => 'service_jobs.id',
            'scheduled_date' => 'service_jobs.scheduled_date',
            'status' => 'service_jobs.status',
            'job_type' => 'service_jobs.job_type',
        ];
        $order = $sortableMap[$order] ?? 'service_jobs.scheduled_date';

        $query = ServiceJob::leftJoin('clients', 'clients.id', '=', 'service_jobs.client_id')
            ->leftJoin('service_technicians', 'service_technicians.id', '=', 'service_jobs.technician_id')
            ->whereNull('service_jobs.deleted_at')
            ->select(
                'service_jobs.*',
                'clients.name as client_name',
                'service_technicians.name as technician_name'
            )
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('service_jobs.client_id', (int) $request->client_id);
            })
            ->when($request->filled('technician_id'), function ($q) use ($request) {
                $q->where('service_jobs.technician_id', (int) $request->technician_id);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('service_jobs.status', $request->status);
            })
            ->when($request->filled('job_type'), function ($q) use ($request) {
                $q->where('service_jobs.job_type', $request->job_type);
            })
            ->when($request->filled('from'), function ($q) use ($request) {
                $q->whereDate('service_jobs.scheduled_date', '>=', $request->from);
            })
            ->when($request->filled('to'), function ($q) use ($request) {
                $q->whereDate('service_jobs.scheduled_date', '<=', $request->to);
            });

        $totalRows = $query->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $rows = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($rows as $job) {
            $item['id'] = $job->id;
            $item['client_name'] = $job->client_name ?? null;
            $item['technician_name'] = $job->technician_name ?? null;
            $item['service_item'] = $job->service_item;
            $item['job_type'] = $job->job_type;
            $item['status'] = $job->status;
            $item['scheduled_date'] = $job->scheduled_date;
            $item['started_at'] = $job->started_at;
            $item['completed_at'] = $job->completed_at;
            $data[] = $item;
        }

        $clients = Client::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $technicians = ServiceTechnician::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $jobTypes = ServiceJob::whereNull('deleted_at')
            ->whereNotNull('job_type')
            ->distinct()
            ->orderBy('job_type')
            ->pluck('job_type')
            ->values();

        return response()->json([
            'rows' => $data,
            'totalRows' => $totalRows,
            'clients' => $clients,
            'technicians' => $technicians,
            'job_types' => $jobTypes,
            'statuses' => ['pending', 'in_progress', 'completed', 'cancelled'],
        ]);
    }

    // -------- Checklist Completion Report --------\\
    public function checklistCompletion(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'checklist_completion_report', ServiceJob::class);

        $query = DB::table('service_job_checklist_items as jci')
            ->join('service_jobs as sj', 'sj.id', '=', 'jci.service_job_id')
            ->leftJoin('service_checklist_categories as cat', 'cat.id', '=', 'jci.category_id')
            ->whereNull('jci.deleted_at')
            ->whereNull('sj.deleted_at');

        if ($request->filled('category_id')) {
            $query->where('jci.category_id', (int) $request->category_id);
        }

        if ($request->filled('job_type')) {
            $query->where('sj.job_type', $request->job_type);
        }

        if ($request->filled('technician_id')) {
            $query->where('sj.technician_id', (int) $request->technician_id);
        }

        if ($request->filled('client_id')) {
            $query->where('sj.client_id', (int) $request->client_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('sj.scheduled_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('sj.scheduled_date', '<=', $request->to);
        }

        $rows = $query
            ->selectRaw(
                "COALESCE(cat.name, jci.category_name, 'Uncategorized') as category_name,
                COUNT(*) as total_items,
                SUM(CASE WHEN jci.is_completed = 1 THEN 1 ELSE 0 END) as completed_items"
            )
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->get();

        $clients = Client::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $technicians = ServiceTechnician::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $categories = DB::table('service_checklist_categories')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $jobTypes = ServiceJob::whereNull('deleted_at')
            ->whereNotNull('job_type')
            ->distinct()
            ->orderBy('job_type')
            ->pluck('job_type')
            ->values();

        return response()->json([
            'rows' => $rows,
            'clients' => $clients,
            'technicians' => $technicians,
            'categories' => $categories,
            'job_types' => $jobTypes,
        ]);
    }

    // -------- Customer Maintenance History Report --------\\
    public function customerMaintenanceHistory(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'customer_maintenance_history_report', ServiceJob::class);

        $perPage = $request->limit ?: 10;
        $pageStart = (int) $request->get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;

        $query = ServiceJob::leftJoin('clients', 'clients.id', '=', 'service_jobs.client_id')
            ->leftJoin('service_technicians', 'service_technicians.id', '=', 'service_jobs.technician_id')
            ->whereNull('service_jobs.deleted_at')
            ->select(
                'service_jobs.*',
                'clients.name as client_name',
                'service_technicians.name as technician_name'
            );

        if ($request->filled('client_id')) {
            $query->where('service_jobs.client_id', (int) $request->client_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('service_jobs.scheduled_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('service_jobs.scheduled_date', '<=', $request->to);
        }

        if ($request->filled('status')) {
            $query->where('service_jobs.status', $request->status);
        }

        $totalRows = $query->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $rows = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy('service_jobs.scheduled_date', 'desc')
            ->orderBy('service_jobs.id', 'desc')
            ->get();

        $jobs = [];
        foreach ($rows as $job) {
            $jobs[] = [
                'id' => $job->id,
                'client_name' => $job->client_name ?? null,
                'technician_name' => $job->technician_name ?? null,
                'service_item' => $job->service_item,
                'job_type' => $job->job_type,
                'status' => $job->status,
                'scheduled_date' => $job->scheduled_date,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'notes' => $job->notes,
            ];
        }

        $clients = Client::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'jobs' => $jobs,
            'totalRows' => $totalRows,
            'clients' => $clients,
        ]);
    }
}


