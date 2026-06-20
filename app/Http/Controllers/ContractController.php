<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractComment;
use App\Models\ContractNote;
use App\Models\ContractRenewal;
use App\Models\ContractTask;
use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Setting;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';
        $helpers = new helpers;

        $param = [0 => 'like', 1 => '=', 2 => '=', 3 => '=', 4 => '='];
        $columns = [0 => 'contract_number', 1 => 'client_id', 2 => 'status', 3 => 'type', 4 => 'project_id'];

        $query = Contract::with('client', 'employee', 'project')->whereNull('deleted_at');
        $query = $helpers->filter($query, $columns, $param, $request);
        $query->where(function ($q) use ($request) {
            $q->when($request->filled('search'), function ($q) use ($request) {
                $q->where('contract_number', 'LIKE', "%{$request->search}%")
                    ->orWhere('subject', 'LIKE', "%{$request->search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'LIKE', "%{$request->search}%"));
            });
        });

        $totalRows = $query->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $contracts = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        $data = [];
        foreach ($contracts as $c) {
            $partyType = $c->party_type ?: 'customer';
            $data[] = [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'subject' => $c->subject,
                'party_type' => $partyType,
                'client_id' => $c->client_id,
                'client_name' => $c->client ? $c->client->name : '',
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee ? trim($c->employee->firstname . ' ' . $c->employee->lastname) : '',
                'party_name' => $partyType === 'employee'
                    ? ($c->employee ? trim($c->employee->firstname . ' ' . $c->employee->lastname) : '')
                    : ($c->client ? $c->client->name : ''),
                'project_id' => $c->project_id,
                'project_name' => $c->project ? $c->project->title : null,
                'value' => (float) $c->value,
                'type' => $c->type,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'status' => $c->status,
                'hide_from_customer' => (bool) $c->hide_from_customer,
            ];
        }

        $clients = Client::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'name']);
        $employees = Employee::orderBy('id', 'desc')->get(['id', 'firstname', 'lastname']);
        $projects = Project::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'title']);

        return response()->json([
            'totalRows' => $totalRows,
            'contracts' => $data,
            'clients' => $clients,
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Contract::class);

        $clients = Client::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'name']);
        $employees = Employee::orderBy('id', 'desc')->get(['id', 'firstname', 'lastname']);
        $projects = Project::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'title']);

        return response()->json([
            'clients' => $clients,
            'employees' => $employees,
            'projects' => $projects,
            'next_contract_number' => Contract::generateContractNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Contract::class);

        $request->validate([
            'party_type' => 'required|in:customer,employee',
            'client_id' => 'required_if:party_type,customer|nullable|exists:clients,id',
            'employee_id' => 'required_if:party_type,employee|nullable|exists:employees,id',
            'subject' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:draft,active,expired,cancelled',
        ], $this->contractDateMessages($request), $this->contractAttributes());

        $contract_number = $request->contract_number ?: Contract::generateContractNumber();
        $partyType = $request->party_type;

        Contract::create([
            'contract_number' => $contract_number,
            'party_type' => $partyType,
            'client_id' => $partyType === 'customer' ? $request->client_id : null,
            'employee_id' => $partyType === 'employee' ? $request->employee_id : null,
            'project_id' => $request->project_id,
            'subject' => $request->subject,
            'value' => $request->value,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'hide_from_customer' => (bool) $request->hide_from_customer,
            'status' => $request->status,
        ]);

        return response()->json(['success' => true]);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $contract = Contract::with(['client', 'employee', 'project', 'attachments', 'comments.user', 'renewals', 'contractTasks', 'notes.user'])
            ->whereNull('deleted_at')
            ->findOrFail($id);

        $partyType = $contract->party_type ?: 'customer';
        $employeeName = $contract->employee ? trim($contract->employee->firstname . ' ' . $contract->employee->lastname) : '';
        $clientName = $contract->client ? $contract->client->name : '';

        $item = [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'subject' => $contract->subject,
            'party_type' => $partyType,
            'client_id' => $contract->client_id,
            'client_name' => $clientName,
            'employee_id' => $contract->employee_id,
            'employee_name' => $employeeName,
            'party_name' => $partyType === 'employee' ? $employeeName : $clientName,
            'project_id' => $contract->project_id,
            'project_name' => $contract->project ? $contract->project->title : null,
            'value' => (float) $contract->value,
            'type' => $contract->type,
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'description' => $contract->description,
            'hide_from_customer' => (bool) $contract->hide_from_customer,
            'status' => $contract->status,
            'signer_name' => $contract->signer_name,
            'signed_at' => $contract->signed_at ? $contract->signed_at->toIso8601String() : null,
            'signed_ip' => $contract->signed_ip,
            'created_at' => $contract->created_at->toIso8601String(),
            'updated_at' => $contract->updated_at->toIso8601String(),
            'attachments' => $contract->attachments->map(fn ($a) => ['id' => $a->id, 'file_name' => $a->file_name, 'file_path' => $a->file_path]),
            'comments' => $contract->comments->map(fn ($c) => [
                'id' => $c->id, 'body' => $c->body, 'user_id' => $c->user_id,
                'user_name' => $c->user ? $c->user->username : '', 'created_at' => $c->created_at->toIso8601String(),
            ]),
            'renewals' => $contract->renewals->map(fn ($r) => [
                'id' => $r->id, 'renewal_date' => $r->renewal_date, 'new_end_date' => $r->new_end_date, 'notes' => $r->notes,
            ]),
            'tasks' => $contract->contractTasks->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title, 'due_date' => $t->due_date, 'status' => $t->status, 'description' => $t->description,
            ]),
            'notes' => $contract->notes->map(fn ($n) => [
                'id' => $n->id, 'content' => $n->content, 'user_name' => $n->user ? $n->user->username : '', 'created_at' => $n->created_at->toIso8601String(),
            ]),
        ];

        return response()->json(['contract' => $item]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);

        $contract = Contract::with('client', 'employee', 'project')->whereNull('deleted_at')->findOrFail($id);
        if (empty($contract->party_type)) {
            $contract->party_type = $contract->employee_id ? 'employee' : 'customer';
        }
        $clients = Client::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'name']);
        $employees = Employee::orderBy('id', 'desc')->get(['id', 'firstname', 'lastname']);
        $projects = Project::whereNull('deleted_at')->orderBy('id', 'desc')->get(['id', 'title']);

        return response()->json([
            'contract' => $contract,
            'clients' => $clients,
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);

        $request->validate([
            'party_type' => 'required|in:customer,employee',
            'client_id' => 'required_if:party_type,customer|nullable|exists:clients,id',
            'employee_id' => 'required_if:party_type,employee|nullable|exists:employees,id',
            'subject' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:draft,active,expired,cancelled',
        ], $this->contractDateMessages($request), $this->contractAttributes());

        $partyType = $request->party_type;

        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $contract->update([
            'party_type' => $partyType,
            'client_id' => $partyType === 'customer' ? $request->client_id : null,
            'employee_id' => $partyType === 'employee' ? $request->employee_id : null,
            'project_id' => $request->project_id,
            'subject' => $request->subject,
            'value' => $request->value,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'hide_from_customer' => (bool) $request->hide_from_customer,
            'status' => $request->status,
            'signer_name' => $request->signer_name ?? $contract->signer_name,
            'signed_at' => $request->signed_at ?? $contract->signed_at,
            'signed_ip' => $request->signed_ip ?? $contract->signed_ip,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Contract::class);

        Contract::whereId($id)->update(['deleted_at' => Carbon::now()]);

        return response()->json(['success' => true]);
    }

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Contract::class);

        $ids = $request->ids ?? [];
        Contract::whereIn('id', $ids)->update(['deleted_at' => Carbon::now()]);

        return response()->json(['success' => true]);
    }

    public function dashboard(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $now = Carbon::now()->toDateString();
        $aboutToExpireDays = 30;
        $aboutToExpireEnd = Carbon::now()->addDays($aboutToExpireDays)->toDateString();
        $recentlyAddedDays = 7;
        $recentlyAddedStart = Carbon::now()->subDays($recentlyAddedDays)->toDateString();

        $countActive = Contract::whereNull('deleted_at')->where('status', 'active')
            ->where('start_date', '<=', $now)->where('end_date', '>=', $now)->count();
        $countExpired = Contract::whereNull('deleted_at')->where('end_date', '<', $now)->count();
        $countAboutToExpire = Contract::whereNull('deleted_at')
            ->where('status', 'active')
            ->whereBetween('end_date', [$now, $aboutToExpireEnd])->count();
        $countRecentlyAdded = Contract::whereNull('deleted_at')->where('created_at', '>=', $recentlyAddedStart)->count();
        $countTrash = Contract::onlyTrashed()->count();

        $byType = Contract::whereNull('deleted_at')
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($r) => ['type' => $r->type ?: 'Other', 'count' => $r->total]);

        $valueByType = Contract::whereNull('deleted_at')
            ->select('type', DB::raw('SUM(value) as total_value'))
            ->groupBy('type')
            ->get()
            ->map(fn ($r) => ['type' => $r->type ?: 'Other', 'value' => (float) $r->total_value]);

        return response()->json([
            'count_active' => $countActive,
            'count_expired' => $countExpired,
            'count_about_to_expire' => $countAboutToExpire,
            'count_recently_added' => $countRecentlyAdded,
            'count_trash' => $countTrash,
            'charts' => [
                'by_type' => $byType,
                'value_by_type' => $valueByType,
            ],
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $contract = Contract::with('client', 'employee', 'project')->whereNull('deleted_at')->findOrFail($id);

        $partyType = $contract->party_type ?: 'customer';
        $employeeName = $contract->employee ? trim($contract->employee->firstname . ' ' . $contract->employee->lastname) : '-';
        $clientName = $contract->client ? $contract->client->name : '-';
        $partyName = $partyType === 'employee' ? $employeeName : $clientName;
        $partyLabel = $partyType === 'employee' ? 'Employee' : 'Customer';

        $contractData = [
            'contract_number' => $contract->contract_number,
            'subject' => $contract->subject,
            'party_type' => $partyType,
            'party_label' => $partyLabel,
            'party_name' => $partyName,
            'customer_name' => $partyName,
            'project_name' => $contract->project ? $contract->project->title : null,
            'value_formatted' => number_format($contract->value, 2),
            'type' => $contract->type ?? '-',
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'status' => $contract->status,
            'description' => $contract->description,
            'signer_name' => $contract->signer_name,
            'signed_at' => $contract->signed_at ? $contract->signed_at->format('F j, Y H:i') : null,
            'signed_ip' => $contract->signed_ip,
        ];

        $setting = Setting::whereNull('deleted_at')->first();
        $Html = view('pdf.contract_pdf', ['contract' => $contractData, 'setting' => $setting])->render();

        if (class_exists(\ArPHP\I18N\Arabic::class)) {
            try {
                $arabic = new \ArPHP\I18N\Arabic;
                $p = $arabic->arIdentify($Html);
                for ($i = count($p) - 1; $i >= 0; $i -= 2) {
                    $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
                    $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $pdf = PDF::loadHTML($Html);
        $filename = 'Contract_' . $contract->contract_number . '.pdf';
        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /** Render a template merged with a contract's data as a PDF (preview or download). */
    public function templatePdf(Request $request, $contractId, $templateId)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $contract = Contract::with('client', 'project', 'employee')->whereNull('deleted_at')->findOrFail($contractId);
        $template = ContractTemplate::findOrFail($templateId);
        $body = $template->render($contract);

        $title = e($template->name) . ' — ' . e($contract->contract_number);
        $Html = '<!doctype html><html><head><meta charset="UTF-8"><title>' . $title . '</title>'
            . '<style>body{font-family:DejaVu Sans, Arial, sans-serif;font-size:12px;color:#222;line-height:1.5;padding:24px;}'
            . 'h1,h2,h3{margin:1em 0 0.4em;}p{margin:0 0 0.6em;}table{border-collapse:collapse;}'
            . 'table td,table th{border:1px solid #ccc;padding:6px;}</style></head><body>'
            . $body
            . '</body></html>';

        $pdf = PDF::loadHTML($Html);
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $template->name);
        $filename = 'Template_' . $safeName . '_' . $contract->contract_number . '.pdf';
        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    public function mergeFields(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);

        $fields = [
            ['key' => '{contract_number}', 'label' => 'Contract Number'],
            ['key' => '{customer_name}', 'label' => 'Customer Name'],
            ['key' => '{customer_email}', 'label' => 'Customer Email'],
            ['key' => '{customer_phone}', 'label' => 'Customer Phone'],
            ['key' => '{customer_address}', 'label' => 'Customer Address'],
            ['key' => '{project_name}', 'label' => 'Project Name'],
            ['key' => '{subject}', 'label' => 'Subject'],
            ['key' => '{contract_value}', 'label' => 'Contract Value (USD)'],
            ['key' => '{contract_type}', 'label' => 'Contract Type'],
            ['key' => '{start_date}', 'label' => 'Start Date'],
            ['key' => '{end_date}', 'label' => 'End Date'],
            ['key' => '{description}', 'label' => 'Description'],
            ['key' => '{status}', 'label' => 'Status'],
            ['key' => '{signer_name}', 'label' => 'Signer Name'],
            ['key' => '{signed_at}', 'label' => 'Signed Date'],
            ['key' => '{signed_ip}', 'label' => 'IP Address'],
        ];

        return response()->json(['merge_fields' => $fields]);
    }

    // ---------- Attachments ----------
    public function attachmentsIndex(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $list = $contract->attachments->map(fn ($a) => ['id' => $a->id, 'file_name' => $a->file_name, 'file_path' => $a->file_path]);
        return response()->json(['attachments' => $list]);
    }

    public function attachmentsStore(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $request->validate(['file' => 'required|file|max:10240']);
        $file = $request->file('file');
        $path = $file->store('contracts/' . $id, 'public');
        ContractAttachment::create(['contract_id' => $id, 'file_name' => $file->getClientOriginalName(), 'file_path' => $path]);
        return response()->json(['success' => true, 'path' => $path]);
    }

    public function attachmentsDestroy(Request $request, $contractId, $attachmentId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        $att = ContractAttachment::where('contract_id', $contractId)->findOrFail($attachmentId);
        Storage::disk('public')->delete($att->file_path);
        $att->delete();
        return response()->json(['success' => true]);
    }

    public function attachmentsDownload(Request $request, $contractId, $attachmentId)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $att = ContractAttachment::where('contract_id', $contractId)->findOrFail($attachmentId);
        $path = Storage::disk('public')->path($att->file_path);
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->download($path, $att->file_name);
    }

    // ---------- Comments ----------
    public function commentsIndex(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $list = $contract->comments()->with('user')->orderBy('created_at', 'desc')->get()
            ->map(fn ($c) => ['id' => $c->id, 'body' => $c->body, 'user_id' => $c->user_id, 'user_name' => $c->user ? $c->user->username : '', 'created_at' => $c->created_at->toIso8601String()]);
        return response()->json(['comments' => $list]);
    }

    public function commentsStore(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $request->validate(['body' => 'required|string|max:5000']);
        $c = ContractComment::create(['contract_id' => $id, 'user_id' => Auth::id(), 'body' => $request->body]);
        $c->load('user');
        return response()->json(['success' => true, 'comment' => ['id' => $c->id, 'body' => $c->body, 'user_name' => $c->user->username ?? '', 'created_at' => $c->created_at->toIso8601String()]]);
    }

    public function commentsDestroy(Request $request, $contractId, $commentId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        ContractComment::where('contract_id', $contractId)->where('id', $commentId)->delete();
        return response()->json(['success' => true]);
    }

    // ---------- Renewals ----------
    public function renewalsIndex(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $list = $contract->renewals;
        return response()->json(['renewals' => $list]);
    }

    public function renewalsStore(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $request->validate(['renewal_date' => 'required|date', 'new_end_date' => 'required|date', 'notes' => 'nullable|string']);
        ContractRenewal::create([
            'contract_id' => $id,
            'renewal_date' => $request->renewal_date,
            'new_end_date' => $request->new_end_date,
            'notes' => $request->notes,
        ]);
        return response()->json(['success' => true]);
    }

    // ---------- Tasks ----------
    public function tasksIndex(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $list = $contract->contractTasks;
        return response()->json(['tasks' => $list]);
    }

    public function tasksStore(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        Contract::whereNull('deleted_at')->findOrFail($id);
        $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date', 'status' => 'nullable|in:pending,in_progress,completed,cancelled', 'description' => 'nullable|string']);
        $t = ContractTask::create([
            'contract_id' => $id,
            'title' => $request->title,
            'due_date' => $request->due_date,
            'status' => $request->status ?? 'pending',
            'description' => $request->description,
        ]);
        return response()->json(['success' => true, 'task' => $t]);
    }

    public function tasksUpdate(Request $request, $contractId, $taskId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        $task = ContractTask::where('contract_id', $contractId)->findOrFail($taskId);
        $task->update($request->only(['title', 'due_date', 'status', 'description']));
        return response()->json(['success' => true]);
    }

    public function tasksDestroy(Request $request, $contractId, $taskId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        ContractTask::where('contract_id', $contractId)->where('id', $taskId)->delete();
        return response()->json(['success' => true]);
    }

    // ---------- Notes ----------
    public function notesIndex(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $list = $contract->notes()->with('user')->orderBy('created_at', 'desc')->get()
            ->map(fn ($n) => ['id' => $n->id, 'content' => $n->content, 'user_name' => $n->user ? $n->user->username : '', 'created_at' => $n->created_at->toIso8601String()]);
        return response()->json(['notes' => $list]);
    }

    public function notesStore(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::whereNull('deleted_at')->findOrFail($id);
        $request->validate(['content' => 'required|string|max:10000']);
        $n = ContractNote::create(['contract_id' => $id, 'user_id' => Auth::id(), 'content' => $request->content]);
        $n->load('user');
        return response()->json(['success' => true, 'note' => ['id' => $n->id, 'content' => $n->content, 'user_name' => $n->user->username ?? '', 'created_at' => $n->created_at->toIso8601String()]]);
    }

    public function notesDestroy(Request $request, $contractId, $noteId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        ContractNote::where('contract_id', $contractId)->where('id', $noteId)->delete();
        return response()->json(['success' => true]);
    }

    // ---------- Templates (global) ----------
    public function templatesIndex(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $list = ContractTemplate::orderBy('name')->get(['id', 'name', 'content']);
        return response()->json(['templates' => $list]);
    }

    public function templatesShow(Request $request, $templateId)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $t = ContractTemplate::findOrFail($templateId);
        return response()->json(['template' => $t]);
    }

    public function templatesStore(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Contract::class);
        $request->validate(['name' => 'required|string|max:255', 'content' => 'nullable|string']);
        $t = ContractTemplate::create($request->only(['name', 'content']));
        return response()->json(['success' => true, 'template' => $t]);
    }

    public function templatesUpdate(Request $request, $templateId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Contract::class);
        $t = ContractTemplate::findOrFail($templateId);
        $t->update($request->only(['name', 'content']));
        return response()->json(['success' => true]);
    }

    public function templatesDestroy(Request $request, $templateId)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Contract::class);
        ContractTemplate::whereId($templateId)->delete();
        return response()->json(['success' => true]);
    }

    /** Render a template with contract merge fields (preview or export). */
    public function templateRender(Request $request, $contractId, $templateId)
    {
        $this->authorizeForUser($request->user('api'), 'view', Contract::class);
        $contract = Contract::with('client', 'project')->whereNull('deleted_at')->findOrFail($contractId);
        $template = ContractTemplate::findOrFail($templateId);
        $html = $template->render($contract);
        return response()->json(['content' => $html]);
    }

    private function contractDateMessages(Request $request): array
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $endVsStart = $start && $end
            ? "Finish date ({$end}) cannot be earlier than the start date ({$start})."
            : 'Finish date cannot be earlier than the start date.';

        return [
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date is not a valid date.',
            'end_date.required' => 'Finish date is required.',
            'end_date.date' => 'Finish date is not a valid date.',
            'end_date.after_or_equal' => $endVsStart,
        ];
    }

    private function contractAttributes(): array
    {
        return [
            'start_date' => 'start date',
            'end_date' => 'finish date',
        ];
    }
}
