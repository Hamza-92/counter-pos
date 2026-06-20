<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\BaseController;
use App\Models\CommissionProgram;
use App\Models\CommissionReceipt;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionReceiptController extends BaseController
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'paid_at';
        $dir = $request->SortType ?? 'desc';

        $query = CommissionReceipt::with(['salesAgent:id,name,code', 'paymentMethod:id,title'])
            ->where('deleted_at', null);

        if ($request->filled('sales_agent_id')) {
            $query->where('sales_agent_id', $request->sales_agent_id);
        }
        if ($request->filled('search')) {
            $query->where('Ref', 'like', "%{$request->search}%");
        }
        if ($request->filled('date_from')) {
            $query->where('paid_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('paid_at', '<=', $request->date_to);
        }

        $totalRows = $query->count();
        if ($perPage === '-1') {
            $perPage = $totalRows;
        }
        $receipts = $query->offset($offSet)->limit($perPage)->orderBy($order, $dir)->get();

        return $this->sendResponse([
            'receipts' => $receipts,
            'totalRows' => $totalRows,
        ], '');
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', CommissionProgram::class);
        $request->validate([
            'sales_agent_id' => 'required|integer|exists:sales_agents,id',
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer|exists:sale_commissions,id',
            'Ref' => 'nullable|string|max:192',
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'notes' => 'nullable|string',
        ]);

        $ref = $request->filled('Ref')
            ? $request->Ref
            : $this->commissionService->generateReceiptRef();

        $receipt = $this->commissionService->createReceiptAndMarkPaid(
            (int) $request->sales_agent_id,
            $request->commission_ids,
            $ref,
            (float) $request->amount,
            $request->paid_at,
            $request->payment_method_id,
            $request->notes
        );

        return $this->sendResponse($receipt->load(['salesAgent', 'paymentMethod']), __('Created successfully'));
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', CommissionProgram::class);
        $receipt = CommissionReceipt::with(['salesAgent', 'paymentMethod', 'saleCommissions.sale', 'saleCommissions.commissionRule'])
            ->findOrFail($id);
        return $this->sendResponse($receipt, '');
    }

    /** Next receipt Ref for forms (support endpoint). */
    public function getNewRef(Request $request)
    {
        $ref = $this->commissionService->generateReceiptRef();
        return $this->sendResponse(['Ref' => $ref], '');
    }
}
