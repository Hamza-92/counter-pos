<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\product_warehouse;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\ServiceJob;
use App\Models\ServiceJobChecklistItem;
use App\Models\ServiceJobItem;
use App\Models\ServiceJobPayment;
use App\Models\ServiceJobPhoto;
use App\Models\ServiceTechnician;
use App\Models\Setting;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use App\utils\helpers;
use ArPHP\I18N\Arabic;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class ServiceJobController extends BaseController
{
    // -------------- Get All Service Jobs ---------------\\
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $perPage = $request->limit ?: 10;
        $pageStart = (int) $request->get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?: 'id';
        $dir = strtolower($request->SortType ?: 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $sortableMap = [
            'id' => 'service_jobs.id',
            'Ref' => 'service_jobs.Ref',
            'scheduled_date' => 'service_jobs.scheduled_date',
            'status' => 'service_jobs.status',
            'job_type' => 'service_jobs.job_type',
            'payment_status' => 'service_jobs.payment_status',
            'total_amount' => 'service_jobs.total_amount',
        ];
        $order = $sortableMap[$order] ?? 'service_jobs.Ref';

        $query = ServiceJob::leftJoin('clients', 'clients.id', '=', 'service_jobs.client_id')
            ->leftJoin('service_technicians', 'service_technicians.id', '=', 'service_jobs.technician_id')
            ->whereNull('service_jobs.deleted_at')
            ->select(
                'service_jobs.*',
                'clients.name as client_name',
                'service_technicians.name as technician_name'
            )
            ->where(function ($q) use ($request) {
                return $q->when($request->filled('search'), function ($q) use ($request) {
                    $s = $request->search;

                    return $q->where('service_item', 'LIKE', "%{$s}%")
                        ->orWhere('job_type', 'LIKE', "%{$s}%")
                        ->orWhere('notes', 'LIKE', "%{$s}%")
                        ->orWhere('device_brand', 'LIKE', "%{$s}%")
                        ->orWhere('device_model', 'LIKE', "%{$s}%")
                        ->orWhere('device_serial', 'LIKE', "%{$s}%")
                        ->orWhere('device_imei', 'LIKE', "%{$s}%")
                        ->orWhere('Ref', 'LIKE', "%{$s}%");
                });
            })
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('service_jobs.client_id', (int) $request->client_id);
            })
            ->when($request->filled('technician_id'), function ($q) use ($request) {
                $q->where('service_jobs.technician_id', (int) $request->technician_id);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('service_jobs.status', $request->status);
            })
            ->when($request->filled('payment_status'), function ($q) use ($request) {
                $q->where('service_jobs.payment_status', $request->payment_status);
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

        $jobs = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($jobs as $job) {
            $item['id'] = $job->id;
            $item['Ref'] = $job->Ref;
            $item['client_id'] = $job->client_id;
            $item['client_name'] = $job->client_name ?? null;
            $item['technician_id'] = $job->technician_id;
            $item['technician_name'] = $job->technician_name ?? null;
            $item['service_item'] = $job->service_item;
            $item['device_brand'] = $job->device_brand;
            $item['device_model'] = $job->device_model;
            $item['job_type'] = $job->job_type;
            $item['status'] = $job->status;
            $item['payment_status'] = $job->payment_status;
            $item['total_amount'] = (float) $job->total_amount;
            $item['paid_amount'] = (float) $job->paid_amount;
            $item['balance_due'] = (float) $job->total_amount - (float) $job->paid_amount;
            $item['scheduled_date'] = $job->scheduled_date;
            $item['started_at'] = $job->started_at;
            $item['completed_at'] = $job->completed_at;
            $item['delivered_at'] = $job->delivered_at;
            $item['warranty_expires_at'] = $job->warranty_expires_at;
            $data[] = $item;
        }

        return response()->json([
            'jobs' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    // -------------- Meta for create form ---------------\\
    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $clients = Client::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'phone']);

        $technicians = ServiceTechnician::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $warehouses = Warehouse::where('deleted_at', '=', null)
            ->orderBy('name')
            ->get(['id', 'name']);

        $payment_methods = PaymentMethod::where('deleted_at', '=', null)
            ->get(['id', 'name']);

        return response()->json([
            'clients' => $clients,
            'technicians' => $technicians,
            'warehouses' => $warehouses,
            'payment_methods' => $payment_methods,
            'statuses' => [
                'pending', 'intake', 'diagnostic', 'quoted', 'approved',
                'in_progress', 'ready', 'delivered', 'declined', 'cancelled', 'completed',
            ],
            'payment_statuses' => ['unpaid', 'partial', 'paid'],
        ]);
    }

    // -------------- Store New Service Job ---------------\\
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', ServiceJob::class);

        $validated = $this->validatePayload($request);

        $validated['Ref'] = $this->getNumberOrder();

        $job = DB::transaction(function () use ($validated) {
            $job = ServiceJob::create($this->jobAttributes($validated));
            $this->syncChecklistItems($job, $validated['checklist'] ?? []);
            $this->syncItems($job, $validated['items'] ?? []);
            $this->recalcTotals($job);

            return $job;
        });

        return response()->json(['success' => true, 'id' => $job->id], 201);
    }

    // ------------ function show (job + checklist + items + payments + photos) -----------\\
    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->with(['client', 'technician'])->findOrFail($id);

        $checklist = $job->checklistItems()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobChecklistItem $item) {
                return [
                    'id' => $item->id,
                    'category_id' => $item->category_id,
                    'item_id' => $item->item_id,
                    'category_name' => $item->category_name,
                    'item_name' => $item->item_name,
                    'is_completed' => (bool) $item->is_completed,
                    'completed_at' => $item->completed_at,
                ];
            })
            ->all();

        $items = $job->items()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobItem $row) {
                return [
                    'id' => $row->id,
                    'type' => $row->type,
                    'product_id' => $row->product_id,
                    'product_variant_id' => $row->product_variant_id,
                    'warehouse_id' => $row->warehouse_id,
                    'description' => $row->description,
                    'quantity' => (float) $row->quantity,
                    'unit_price' => (float) $row->unit_price,
                    'discount' => (float) $row->discount,
                    'discount_method' => $row->discount_method,
                    'tax_rate' => (float) $row->tax_rate,
                    'tax_method' => $row->tax_method,
                    'total' => (float) $row->total,
                    'stock_deducted' => (bool) $row->stock_deducted,
                    'notes' => $row->notes,
                ];
            })
            ->all();

        $payments = $job->payments()
            ->whereNull('deleted_at')
            ->with('payment_method:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobPayment $row) {
                return [
                    'id' => $row->id,
                    'Ref' => $row->Ref,
                    'date' => $row->date ? $row->date->format('Y-m-d') : null,
                    'montant' => (float) $row->montant,
                    'change' => (float) $row->change,
                    'payment_method_id' => $row->payment_method_id,
                    'payment_method' => $row->payment_method ? $row->payment_method->name : null,
                    'account_id' => $row->account_id,
                    'payment_kind' => $row->payment_kind,
                    'notes' => $row->notes,
                ];
            })
            ->all();

        $photos = $job->photos()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobPhoto $row) {
                return [
                    'id' => $row->id,
                    'stage' => $row->stage,
                    'path' => $row->path,
                    'url' => asset($row->path),
                    'original_name' => $row->original_name,
                    'mime_type' => $row->mime_type,
                    'size' => $row->size,
                    'caption' => $row->caption,
                    'created_at' => $row->created_at,
                ];
            })
            ->all();

        return response()->json([
            'job' => [
                'id' => $job->id,
                'Ref' => $job->Ref,
                'client_id' => $job->client_id,
                'client_name' => $job->client ? $job->client->name : null,
                'technician_id' => $job->technician_id,
                'technician_name' => $job->technician ? $job->technician->name : null,
                'service_item' => $job->service_item,
                'job_type' => $job->job_type,
                'status' => $job->status,
                'scheduled_date' => $job->scheduled_date,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'notes' => $job->notes,

                'device_brand' => $job->device_brand,
                'device_model' => $job->device_model,
                'device_serial' => $job->device_serial,
                'device_imei' => $job->device_imei,
                'device_color' => $job->device_color,
                'device_password' => $job->device_password,
                'accessories' => $job->accessories ?? [],

                'condition_on_arrival' => $job->condition_on_arrival,
                'reported_issue' => $job->reported_issue,
                'diagnosis' => $job->diagnosis,
                'diagnostic_fee' => (float) $job->diagnostic_fee,

                'quote_amount' => (float) $job->quote_amount,
                'quote_valid_until' => $job->quote_valid_until ? Carbon::parse($job->quote_valid_until)->format('Y-m-d') : null,
                'quote_approved_at' => $job->quote_approved_at,
                'quote_approved_by' => $job->quote_approved_by,
                'quotation_id' => $job->quotation_id,
                'quotation_ref' => $job->quotation_id ? optional(Quotation::find($job->quotation_id))->Ref : null,

                'total_amount' => (float) $job->total_amount,
                'paid_amount' => (float) $job->paid_amount,
                'balance_due' => (float) $job->total_amount - (float) $job->paid_amount,
                'payment_status' => $job->payment_status,

                'warranty_days' => (int) $job->warranty_days,
                'warranty_expires_at' => $job->warranty_expires_at ? Carbon::parse($job->warranty_expires_at)->format('Y-m-d') : null,
                'parent_job_id' => $job->parent_job_id,

                'delivered_at' => $job->delivered_at,
                'pickup_signature' => $job->pickup_signature,
            ],
            'checklist' => $checklist,
            'items' => $items,
            'payments' => $payments,
            'photos' => $photos,
        ]);
    }

    // -------------- Update Service Job ---------------\\
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($id);

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($job, $validated) {
            $job->update($this->jobAttributes($validated, $job));

            if (array_key_exists('checklist', $validated)) {
                $this->syncChecklistItems($job, $validated['checklist'] ?? []);
            }
            if (array_key_exists('items', $validated)) {
                $this->syncItems($job, $validated['items'] ?? []);
            }
            $this->recalcTotals($job);
        });

        return response()->json(['success' => true]);
    }

    // -------------- Delete Service Job ---------------\\
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ServiceJob::class);

        $job = ServiceJob::findOrFail($id);
        $job->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }

    // -------------- Approve Quote ---------------\\
    public function approveQuote(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'approved_by' => 'nullable|string|max:191',
        ]);

        $u = $request->user('api');
        $approverFallback = $u
            ? ($u->firstname ?: ($u->username ?: ($u->email ?: 'staff')))
            : 'customer';

        $job->update([
            'status' => 'approved',
            'quote_approved_at' => now(),
            'quote_approved_by' => $validated['approved_by'] ?? $approverFallback,
        ]);

        return response()->json(['success' => true]);
    }

    // -------------- Decline Quote ---------------\\
    public function declineQuote(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($id);

        $job->update(['status' => 'declined']);

        return response()->json(['success' => true]);
    }

    // -------------- Mark Delivered (decrements stock + sets warranty) ---------------\\
    public function markDelivered(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'pickup_signature' => 'nullable|string|max:191',
        ]);

        // Use the same 0.0001 threshold as recalcTotals() to keep payment_status and
        // delivery-eligibility checks consistent under float-precision drift.
        if (((float) $job->total_amount - (float) $job->paid_amount) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deliver: outstanding balance must be paid first.',
            ], 422);
        }

        DB::transaction(function () use ($job, $validated) {
            $this->deductStockForJob($job);

            $warrantyDays = (int) ($job->warranty_days ?? 30);
            $job->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'completed_at' => $job->completed_at ?: now(),
                'pickup_signature' => $validated['pickup_signature'] ?? null,
                'warranty_expires_at' => $warrantyDays > 0 ? now()->addDays($warrantyDays)->toDateString() : null,
            ]);
        });

        return response()->json(['success' => true]);
    }

    // -------------- Helper: validation rules (shared by store + update) ---------------\\
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'technician_id' => 'nullable|integer|exists:service_technicians,id',
            'service_item' => 'required|string|max:191',
            'job_type' => 'nullable|string|max:191',
            'status' => 'nullable|string|max:50',
            'scheduled_date' => 'nullable|date',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'notes' => 'nullable|string',

            'device_brand' => 'nullable|string|max:191',
            'device_model' => 'nullable|string|max:191',
            'device_serial' => 'nullable|string|max:191',
            'device_imei' => 'nullable|string|max:191',
            'device_color' => 'nullable|string|max:100',
            'device_password' => 'nullable|string|max:191',
            'accessories' => 'nullable|array',
            'accessories.*' => 'string|max:100',

            'condition_on_arrival' => 'nullable|string',
            'reported_issue' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'diagnostic_fee' => 'nullable|numeric',

            'quote_amount' => 'nullable|numeric',
            'quote_valid_until' => 'nullable|date',

            'warranty_days' => 'nullable|integer|min:0|max:3650',
            'parent_job_id' => 'nullable|integer|exists:service_jobs,id',

            'checklist' => 'nullable|array',
            'checklist.*.category_id' => 'nullable|integer',
            'checklist.*.category_name' => 'nullable|string|max:191',
            'checklist.*.item_id' => 'nullable|integer',
            'checklist.*.item_name' => 'required_with:checklist|string|max:191',
            'checklist.*.is_completed' => 'nullable|boolean',

            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.type' => 'nullable|string|in:part,labor,other',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_variant_id' => 'nullable|integer',
            'items.*.warehouse_id' => 'nullable|integer',
            'items.*.description' => 'required_with:items|string|max:191',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_method' => 'nullable|string|in:1,2',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_method' => 'nullable|string|in:1,2',
            'items.*.notes' => 'nullable|string',
        ]);
    }

    // -------------- Helper: build attributes for job create/update ---------------\\
    protected function jobAttributes(array $validated, ?ServiceJob $existing = null): array
    {
        $attrs = [
            'client_id' => $validated['client_id'],
            'technician_id' => $validated['technician_id'] ?? null,
            'service_item' => $validated['service_item'],
            'job_type' => $validated['job_type'] ?? null,
            'status' => $validated['status'] ?? ($existing->status ?? 'pending'),
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'notes' => $validated['notes'] ?? null,

            'device_brand' => $validated['device_brand'] ?? null,
            'device_model' => $validated['device_model'] ?? null,
            'device_serial' => $validated['device_serial'] ?? null,
            'device_imei' => $validated['device_imei'] ?? null,
            'device_color' => $validated['device_color'] ?? null,
            'device_password' => $validated['device_password'] ?? null,
            'accessories' => $validated['accessories'] ?? null,

            'condition_on_arrival' => $validated['condition_on_arrival'] ?? null,
            'reported_issue' => $validated['reported_issue'] ?? null,
            'diagnosis' => $validated['diagnosis'] ?? null,
            'diagnostic_fee' => $validated['diagnostic_fee'] ?? 0,

            'quote_amount' => $validated['quote_amount'] ?? 0,
            'quote_valid_until' => $validated['quote_valid_until'] ?? null,

            'warranty_days' => $validated['warranty_days'] ?? ($existing->warranty_days ?? 30),
            'parent_job_id' => $validated['parent_job_id'] ?? null,
        ];

        if (isset($validated['Ref'])) {
            $attrs['Ref'] = $validated['Ref'];
        }

        return $attrs;
    }

    // -------------- Helper: sync checklist items ---------------\\
    protected function syncChecklistItems(ServiceJob $job, array $items): void
    {
        ServiceJobChecklistItem::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        foreach ($items as $item) {
            if (! isset($item['item_name']) || $item['item_name'] === null || $item['item_name'] === '') {
                continue;
            }

            ServiceJobChecklistItem::create([
                'service_job_id' => $job->id,
                'category_id' => $item['category_id'] ?? null,
                'item_id' => $item['item_id'] ?? null,
                'category_name' => $item['category_name'] ?? null,
                'item_name' => $item['item_name'],
                'is_completed' => isset($item['is_completed']) ? (bool) $item['is_completed'] : false,
                'completed_at' => ! empty($item['is_completed']) ? now() : null,
            ]);
        }
    }

    // -------------- Helper: sync line items (parts + labor) ---------------\\
    protected function syncItems(ServiceJob $job, array $items): void
    {
        // Soft-delete previous lines that haven't deducted stock yet.
        // Lines already deducted (delivered jobs) are preserved as audit history.
        ServiceJobItem::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->where('stock_deducted', false)
            ->update(['deleted_at' => now()]);

        foreach ($items as $item) {
            if (! isset($item['description']) || $item['description'] === '') {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $discountMethod = $item['discount_method'] ?? '1';
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $taxMethod = $item['tax_method'] ?? '1';

            $subtotal = $qty * $price;
            $discountValue = $discountMethod === '2' ? ($subtotal * $discount / 100) : $discount;
            $afterDiscount = max(0, $subtotal - $discountValue);
            $taxValue = $taxMethod === '2' ? 0 : ($afterDiscount * $taxRate / 100);
            $total = $afterDiscount + $taxValue;

            ServiceJobItem::create([
                'service_job_id' => $job->id,
                'type' => $item['type'] ?? 'part',
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'warehouse_id' => $item['warehouse_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_price' => $price,
                'discount' => $discount,
                'discount_method' => $discountMethod,
                'tax_rate' => $taxRate,
                'tax_method' => $taxMethod,
                'total' => $total,
                'stock_deducted' => false,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    // -------------- Helper: recompute totals + payment_status ---------------\\
    public function recalcTotals(ServiceJob $job): void
    {
        $itemsTotal = (float) ServiceJobItem::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->sum('total');

        $diagnostic = (float) ($job->diagnostic_fee ?? 0);

        // If quote_amount was set manually and there are no line items, fall back to it.
        if ($itemsTotal <= 0 && (float) $job->quote_amount > 0) {
            $totalAmount = (float) $job->quote_amount + $diagnostic;
        } else {
            $totalAmount = $itemsTotal + $diagnostic;
        }

        $paid = (float) ServiceJobPayment::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->sum('montant');

        $balance = $totalAmount - $paid;

        if ($paid <= 0) {
            $paymentStatus = 'unpaid';
        } elseif ($balance > 0.0001) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'paid';
        }

        $job->update([
            'total_amount' => $totalAmount,
            'paid_amount' => $paid,
            'payment_status' => $paymentStatus,
        ]);
    }

    // -------------- Helper: deduct stock for parts on this job ---------------\\
    protected function deductStockForJob(ServiceJob $job): void
    {
        $items = ServiceJobItem::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->where('type', 'part')
            ->where('stock_deducted', false)
            ->whereNotNull('product_id')
            ->whereNotNull('warehouse_id')
            ->get();

        foreach ($items as $row) {
            $query = product_warehouse::where('warehouse_id', $row->warehouse_id)
                ->where('product_id', $row->product_id);

            if ($row->product_variant_id !== null) {
                $query->where('product_variant_id', $row->product_variant_id);
            }

            $pw = $query->first();
            if ($pw) {
                $pw->qte = (float) $pw->qte - (float) $row->quantity;
                $pw->save();
            }

            $row->stock_deducted = true;
            $row->save();
        }
    }

    /**
     * Generate reference number for service jobs.
     */
    public function getNumberOrder()
    {
        $last = DB::table('service_jobs')->latest('id')->first();

        if ($last && $last->Ref) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            $inMsg = isset($nwMsg[1]) ? ($nwMsg[1] + 1) : 1112;
            $code = 'SJ_'.$inMsg;
        } else {
            $code = 'SJ_1111';
        }

        return $code;
    }

    /**
     * Build the array passed to the service job / quote PDF blades.
     */
    protected function buildPdfJobData(ServiceJob $job): array
    {
        $checklist = $job->checklistItems()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobChecklistItem $item) {
                return [
                    'category_name' => $item->category_name,
                    'item_name' => $item->item_name,
                    'is_completed' => (bool) $item->is_completed,
                ];
            })
            ->all();

        $items = $job->items()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobItem $row) {
                return [
                    'type' => $row->type,
                    'description' => $row->description,
                    'quantity' => (float) $row->quantity,
                    'unit_price' => (float) $row->unit_price,
                    'discount' => (float) $row->discount,
                    'tax_rate' => (float) $row->tax_rate,
                    'total' => (float) $row->total,
                ];
            })
            ->all();

        $payments = $job->payments()
            ->whereNull('deleted_at')
            ->with('payment_method:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobPayment $row) {
                return [
                    'Ref' => $row->Ref,
                    'date' => $row->date ? $row->date->format('Y-m-d') : null,
                    'montant' => (float) $row->montant,
                    'payment_kind' => $row->payment_kind,
                    'payment_method' => $row->payment_method ? $row->payment_method->name : null,
                ];
            })
            ->all();

        return [
            'id' => $job->id,
            'Ref' => $job->Ref,
            'client_name' => optional($job->client)->name ?? '-',
            'client_email' => optional($job->client)->email ?? '-',
            'client_phone' => optional($job->client)->phone ?? '-',
            'client_adr' => optional($job->client)->adresse ?? '-',
            'technician_name' => optional($job->technician)->name ?? '-',
            'service_item' => $job->service_item,
            'job_type' => $job->job_type,
            'status' => $job->status,
            'scheduled_date' => $job->scheduled_date,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
            'delivered_at' => $job->delivered_at,
            'notes' => $job->notes,

            'device_brand' => $job->device_brand,
            'device_model' => $job->device_model,
            'device_color' => $job->device_color,
            'device_serial' => $job->device_serial,
            'device_imei' => $job->device_imei,
            'accessories' => is_array($job->accessories) ? $job->accessories : [],

            'condition_on_arrival' => $job->condition_on_arrival,
            'reported_issue' => $job->reported_issue,
            'diagnosis' => $job->diagnosis,
            'diagnostic_fee' => (float) $job->diagnostic_fee,

            'quote_amount' => (float) $job->quote_amount,
            'quote_valid_until' => $job->quote_valid_until,
            'quote_approved_at' => $job->quote_approved_at,
            'quote_approved_by' => $job->quote_approved_by,

            'total_amount' => (float) $job->total_amount,
            'paid_amount' => (float) $job->paid_amount,
            'payment_status' => $job->payment_status,

            'warranty_days' => (int) $job->warranty_days,
            'warranty_expires_at' => $job->warranty_expires_at,
            'pickup_signature' => $job->pickup_signature,

            'checklist' => $checklist,
            'items' => $items,
            'payments' => $payments,
        ];
    }

    /**
     * Render an Arabic-aware PDF and return a download response.
     */
    protected function renderPdf(string $viewName, array $payload, string $filename)
    {
        $Html = view($viewName, $payload)->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        return PDF::loadHTML($Html)->download($filename);
    }

    /**
     * Generate work-order / invoice PDF for a service job.
     */
    public function service_job_pdf(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);
        $helpers = new helpers;
        $job = ServiceJob::with(['client', 'technician'])
            ->whereNull('deleted_at')
            ->findOrFail($id);

        return $this->renderPdf('pdf.service_job_pdf', [
            'symbol' => $helpers->Get_Currency_Code(),
            'setting' => Setting::whereNull('deleted_at')->first(),
            'job' => $this->buildPdfJobData($job),
        ], 'Service_Job_'.$job->id.'.pdf');
    }

    /**
     * Generate the customer-facing repair quote PDF.
     */
    public function service_quote_pdf(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);
        $helpers = new helpers;
        $job = ServiceJob::with(['client', 'technician'])
            ->whereNull('deleted_at')
            ->findOrFail($id);

        return $this->renderPdf('pdf.service_quote_pdf', [
            'symbol' => $helpers->Get_Currency_Code(),
            'setting' => Setting::whereNull('deleted_at')->first(),
            'job' => $this->buildPdfJobData($job),
        ], 'Service_Quote_'.$job->id.'.pdf');
    }

    /**
     * Convert a service job's quote into a record in the main quotations list.
     * Idempotent: if already linked, returns the existing quotation_id.
     */
    public function createQuotation(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);
        $this->authorizeForUser($request->user('api'), 'create', Quotation::class);

        $job = ServiceJob::with('items')->whereNull('deleted_at')->findOrFail($id);

        if ($job->quotation_id) {
            $existing = Quotation::find($job->quotation_id);
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'quotation_id' => $existing->id,
                    'Ref' => $existing->Ref,
                ]);
            }
        }

        if (! $job->client_id) {
            return response()->json(['message' => 'This service job has no customer; assign one before creating a quotation.'], 422);
        }

        $user = Auth::user();

        // Pick a warehouse the current user can access. Prefer one referenced by the job's items.
        $warehouseId = optional($job->items->firstWhere('warehouse_id', '!=', null))->warehouse_id;
        if (! $warehouseId) {
            if ($user->is_all_warehouses) {
                $warehouseId = Setting::value('warehouse_id')
                    ?? Warehouse::whereNull('deleted_at')->orderBy('id')->value('id');
            } else {
                $warehouseId = UserWarehouse::where('user_id', $user->id)->value('warehouse_id');
            }
        }
        if (! $warehouseId) {
            return response()->json(['message' => 'No warehouse is accessible to your account; cannot create a quotation.'], 422);
        }

        $itemsTotal = (float) $job->items->sum('total');
        $diagnostic = (float) ($job->diagnostic_fee ?? 0);
        $grandTotal = $itemsTotal > 0 ? $itemsTotal + $diagnostic : (float) ($job->total_amount ?: $job->quote_amount ?: 0);

        $quotation = DB::transaction(function () use ($job, $user, $warehouseId, $grandTotal) {
            $year = date('Y');
            $prefix = "QT_SVC_{$year}_";
            $lastRef = Quotation::where('Ref', 'LIKE', $prefix . '%')
                ->orderByDesc('id')->value('Ref');
            $next = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;
            $ref = sprintf('QT_SVC_%s_%05d', $year, $next);

            $notesParts = [];
            if ($job->Ref) {
                $notesParts[] = 'From service job: ' . $job->Ref;
            }
            if ($job->reported_issue) {
                $notesParts[] = 'Reported issue: ' . $job->reported_issue;
            }
            if ($job->diagnosis) {
                $notesParts[] = 'Diagnosis: ' . $job->diagnosis;
            }
            // Labor / non-product lines have no product_id and can't go into quotation_details.
            $laborLines = $job->items->filter(fn ($i) => empty($i->product_id))
                ->map(fn ($i) => '- ' . ($i->description ?: 'Item') . ' (' . $i->quantity . ' x ' . number_format($i->unit_price, 2) . ' = ' . number_format($i->total, 2) . ')')
                ->values();
            if ($laborLines->count()) {
                $notesParts[] = "Labor / non-product items:\n" . $laborLines->implode("\n");
            }
            if ($diagnostic > 0) {
                $notesParts[] = 'Diagnostic fee: ' . number_format($diagnostic, 2);
            }

            $quotation = Quotation::create([
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'Ref' => $ref,
                'client_id' => $job->client_id,
                'warehouse_id' => $warehouseId,
                'user_id' => $user->id,
                'GrandTotal' => $grandTotal,
                'discount' => 0,
                'shipping' => 0,
                'TaxNet' => 0,
                'tax_rate' => 0,
                'statut' => 'pending',
                'notes' => implode("\n\n", $notesParts),
            ]);

            foreach ($job->items as $item) {
                if (empty($item->product_id)) {
                    continue; // captured in notes
                }
                QuotationDetail::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                    'total' => $item->total,
                    'discount' => $item->discount ?? 0,
                    'discount_method' => $item->discount_method ?? '2',
                    'TaxNet' => $item->tax_rate ?? 0,
                    'tax_method' => $item->tax_method ?? '1',
                ]);
            }

            $job->update(['quotation_id' => $quotation->id]);

            return $quotation;
        });

        return response()->json([
            'success' => true,
            'quotation_id' => $quotation->id,
            'Ref' => $quotation->Ref,
        ], 201);
    }
}
