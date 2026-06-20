<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PortalContractsController extends Controller
{
    /**
     * GET /api/portal/contracts - list this client's contracts (excluding hide_from_customer).
     */
    public function index(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $perPage = (int) $request->input('limit', 10);
        $page = max(1, (int) $request->input('page', 1));
        $search = $request->input('search');

        $q = Contract::query()
            ->whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where(function ($qr) {
                $qr->whereNull('hide_from_customer')->orWhere('hide_from_customer', false);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qr) use ($search) {
                    $qr->where('contract_number', 'LIKE', "%{$search}%")
                        ->orWhere('subject', 'LIKE', "%{$search}%")
                        ->orWhere('type', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $totalRows = (clone $q)->count();
        $rows = $perPage > 0
            ? $q->offset(($page - 1) * $perPage)->limit($perPage)->get()
            : $q->get();

        $data = $rows->map(function ($c) {
            return [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'subject' => $c->subject,
                'type' => $c->type,
                'value' => (float) $c->value,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'status' => $c->status,
                'signed_at' => optional($c->signed_at)->toDateString(),
            ];
        });

        return response()->json([
            'totalRows' => $totalRows,
            'contracts' => $data,
        ]);
    }

    /**
     * GET /api/portal/contracts/{id} - contract detail with attachments.
     */
    public function show(Request $request, $id)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $contract = $this->findVisibleContract($portalClient->client_id, $id);
        $contract->load('attachments:id,contract_id,file_name,file_path');

        return response()->json([
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'subject' => $contract->subject,
            'description' => $contract->description,
            'type' => $contract->type,
            'value' => (float) $contract->value,
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'status' => $contract->status,
            'signer_name' => $contract->signer_name,
            'signed_at' => optional($contract->signed_at)->toIso8601String(),
            'attachments' => $contract->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
            ]),
        ]);
    }

    /**
     * GET /api/portal/contracts/{id}/attachments/{attachmentId}/download
     */
    public function downloadAttachment(Request $request, $id, $attachmentId)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $contract = $this->findVisibleContract($portalClient->client_id, $id);
        $att = ContractAttachment::where('contract_id', $contract->id)->findOrFail($attachmentId);

        $path = Storage::disk('public')->path($att->file_path);
        if (! file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->download($path, $att->file_name);
    }

    private function findVisibleContract(int $clientId, $id): Contract
    {
        return Contract::whereNull('deleted_at')
            ->where('client_id', $clientId)
            ->where('id', $id)
            ->where(function ($qr) {
                $qr->whereNull('hide_from_customer')->orWhere('hide_from_customer', false);
            })
            ->firstOrFail();
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
