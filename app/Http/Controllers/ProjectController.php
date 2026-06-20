<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeProject;
use App\Models\Project;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Project::class);
        $role = Auth::user()->roles()->first();

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        // Filter fields With Params to retrieve
        $param = [
            0 => 'like',
            1 => '=',
            2 => '=',
            3 => '=',
            4 => '=',
        ];
        $columns = [
            0 => 'status',
            1 => 'client_id',
            2 => 'start_date',
            3 => 'end_date',
            4 => 'company_id',
        ];
        $data = [];

        // Check If User Has Permission View  All Records
        $projects = Project::with('company', 'client')
            ->where('deleted_at', '=', null);

        // Multiple Filter
        $Filtred = $helpers->filter($projects, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('title', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('company', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $all_projects = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($all_projects as $project) {

            $item['id'] = $project->id;
            $item['title'] = $project->title;
            $item['start_date'] = $project->start_date;
            $item['end_date'] = $project->end_date;
            $item['client_name'] = $project['client']->name;
            $item['company_name'] = $project['company']->name;
            $item['status'] = $project->status;

            $data[] = $item;
        }

        $companies = Company::where('deleted_at', '=', null)->get(['id', 'name']);
        $clients = Client::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'name']);

        $count_not_started = Project::where('deleted_at', '=', null)
            ->where('status', '=', 'not_started')
            ->count();

        $count_in_progress = Project::where('deleted_at', '=', null)
            ->where('status', '=', 'progress')
            ->count();

        $count_cancelled = Project::where('deleted_at', '=', null)
            ->where('status', '=', 'cancelled')
            ->count();

        $count_completed = Project::where('deleted_at', '=', null)
            ->where('status', '=', 'completed')
            ->count();

        return response()->json([
            'totalRows' => $totalRows,
            'projects' => $data,
            'companies' => $companies,
            'clients' => $clients,
            'count_not_started' => $count_not_started,
            'count_in_progress' => $count_in_progress,
            'count_cancelled' => $count_cancelled,
            'count_completed' => $count_completed,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Project::class);

        $clients = Client::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'name']);
        $companies = Company::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'name']);

        return response()->json([
            'clients' => $clients,
            'companies' => $companies,
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Project::class);

        $request->validate([
            'title' => 'required|string|max:255',
            'client' => 'required',
            'company_id' => 'required',
            'assigned_to' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
        ]);

        $project = Project::create([
            'title' => $request['title'],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'company_id' => $request['company_id'],
            'client_id' => $request['client'],
            'status' => $request['status'],
            'description' => $request['description'],
        ]);

        $project->assignedEmployees()->sync($request['assigned_to']);

        return response()->json(['success' => true]);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Project::class);

        $project = Project::where('deleted_at', '=', null)->findOrFail($id);
        $assigned_employees = EmployeeProject::where('project_id', $id)->pluck('employee_id')->toArray();
        $clients = Client::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'name']);
        $companies = Company::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'name']);
        $employees = Employee::where('company_id', $project->company_id)->where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id', 'username']);

        return response()->json([
            'project' => $project,
            'companies' => $companies,
            'clients' => $clients,
            'employees' => $employees,
            'assigned_employees' => $assigned_employees,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Project::class);

        $request->validate([
            'title' => 'required|string|max:255',
            'client' => 'required',
            'company_id' => 'required',
            'assigned_to' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
        ]);

        Project::whereId($id)->update([
            'title' => $request['title'],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'company_id' => $request['company_id'],
            'client_id' => $request['client'],
            'status' => $request['status'],
            'description' => $request['description'],
        ]);

        $project = Project::where('deleted_at', '=', null)->findOrFail($id);
        $project->assignedEmployees()->sync($request['assigned_to']);

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Project::class);

        Project::whereId($id)->update([
            'deleted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);

    }
}
