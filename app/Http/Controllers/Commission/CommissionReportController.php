<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use App\Models\SaleCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionReportController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'calculated_at';
        $dir = $request->SortType ?? 'desc';

        $query = SaleCommission::with([
            'sale:id,Ref,date,GrandTotal,paid_amount',
            'salesAgent:id,name,code',
            'commissionProgram:id,name',
            'commissionRule:id,name,type,value',
            'commissionReceipt:id,Ref,paid_at',
        ])->where('sale_commissions.deleted_at', null);

        if ($request->filled('sales_agent_id')) {
            $query->where('sales_agent_id', $request->sales_agent_id);
        }
        if ($request->filled('commission_program_id')) {
            $query->where('commission_program_id', $request->commission_program_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('calculated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('calculated_at', '<=', $request->date_to);
        }
        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        $totalRows = $query->count();
        if ($perPage === '-1') {
            $perPage = $totalRows;
        }
        $commissions = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        return $this->sendResponse([
            'commissions' => $commissions,
            'totalRows' => $totalRows,
        ], '');
    }

    public function summary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $query = SaleCommission::where('deleted_at', null);

        if ($request->filled('sales_agent_id')) {
            $query->where('sales_agent_id', $request->sales_agent_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('calculated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('calculated_at', '<=', $request->date_to);
        }

        $byStatus = (clone $query)->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(commission_amount) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $totals = (clone $query)->selectRaw('
            SUM(CASE WHEN status = "pending" THEN commission_amount ELSE 0 END) as pending_total,
            SUM(CASE WHEN status = "approved" THEN commission_amount ELSE 0 END) as approved_total,
            SUM(CASE WHEN status = "paid" THEN commission_amount ELSE 0 END) as paid_total,
            SUM(commission_amount) as grand_total,
            COUNT(*) as total_count
        ')->first();

        return $this->sendResponse([
            'by_status' => $byStatus,
            'totals' => $totals,
        ], '');
    }

    public function byAgent(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $query = SaleCommission::where('sale_commissions.deleted_at', null)
            ->join('sales_agents', 'sale_commissions.sales_agent_id', '=', 'sales_agents.id')
            ->select('sales_agents.id', 'sales_agents.name', 'sales_agents.code')
            ->selectRaw('SUM(sale_commissions.commission_amount) as total_commission')
            ->selectRaw('SUM(CASE WHEN sale_commissions.status = "pending" THEN sale_commissions.commission_amount ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN sale_commissions.status = "approved" THEN sale_commissions.commission_amount ELSE 0 END) as approved')
            ->selectRaw('SUM(CASE WHEN sale_commissions.status = "paid" THEN sale_commissions.commission_amount ELSE 0 END) as paid')
            ->groupBy('sales_agents.id', 'sales_agents.name', 'sales_agents.code');

        if ($request->filled('date_from')) {
            $query->whereDate('sale_commissions.calculated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_commissions.calculated_at', '<=', $request->date_to);
        }

        $byAgent = $query->get();
        return $this->sendResponse(['by_agent' => $byAgent], '');
    }
}
