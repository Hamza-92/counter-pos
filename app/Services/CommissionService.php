<?php

namespace App\Services;

use App\Models\CommissionProgram;
use App\Models\CommissionReceipt;
use App\Models\CommissionRule;
use App\Models\Sale;
use App\Models\SaleCommission;
use App\Models\SalesAgent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calculate and create commission records for a sale.
     * Called when a sale is completed (e.g. statut = completed).
     */
    public function calculateForSale(Sale $sale): array
    {
        $agent = $this->resolveAgentForSale($sale);
        if (! $agent) {
            return [];
        }

        $date = $sale->date ? Carbon::parse($sale->date) : now();
        $programs = CommissionProgram::active()
            ->validAt($date)
            ->with(['commissionRules' => function ($q) use ($agent) {
                $q->active()->forAgent($agent->id)->orderBy('priority', 'desc');
            }])
            ->get();

        $created = [];
        foreach ($programs as $program) {
            foreach ($program->commissionRules as $rule) {
                $baseAmount = $this->getBaseAmount($sale, $rule->source);
                if ($baseAmount <= 0) {
                    continue;
                }
                if ($rule->min_threshold !== null && (float) $rule->min_threshold > $baseAmount) {
                    continue;
                }
                $commissionAmount = $this->computeCommission($baseAmount, $rule);
                if ($commissionAmount <= 0) {
                    continue;
                }
                $existing = SaleCommission::where('sale_id', $sale->id)
                    ->where('commission_rule_id', $rule->id)
                    ->where('sales_agent_id', $agent->id)
                    ->exists();
                if ($existing) {
                    continue;
                }
                $sc = SaleCommission::create([
                    'sale_id' => $sale->id,
                    'sales_agent_id' => $agent->id,
                    'commission_program_id' => $program->id,
                    'commission_rule_id' => $rule->id,
                    'base_amount' => $baseAmount,
                    'commission_amount' => $commissionAmount,
                    'status' => 'pending',
                    'calculated_at' => now(),
                ]);
                $created[] = $sc;
            }
        }

        return $created;
    }

    protected function resolveAgentForSale(Sale $sale): ?SalesAgent
    {
        if ($sale->sales_agent_id) {
            return SalesAgent::where('id', $sale->sales_agent_id)->where('is_active', true)->first();
        }
        if ($sale->user_id) {
            return SalesAgent::where('user_id', $sale->user_id)->where('is_active', true)->first();
        }
        return null;
    }

    protected function getBaseAmount(Sale $sale, string $source): float
    {
        switch ($source) {
            case 'paid_amount':
                return (float) $sale->paid_amount;
            case 'sale_total':
            default:
                return (float) $sale->GrandTotal;
        }
    }

    protected function computeCommission(float $baseAmount, CommissionRule $rule): float
    {
        $value = (float) $rule->value;
        if ($rule->type === 'percentage') {
            $amount = round($baseAmount * ($value / 100), 4);
        } else {
            $amount = $value;
        }
        if ($rule->max_cap !== null && $amount > (float) $rule->max_cap) {
            $amount = (float) $rule->max_cap;
        }
        return max(0, $amount);
    }

    /**
     * Generate next Ref for commission receipt.
     */
    public function generateReceiptRef(): string
    {
        $prefix = 'CR-';
        $last = CommissionReceipt::withTrashed()->orderBy('id', 'desc')->first();
        $num = $last ? ((int) preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $last->Ref) + 1) : 1;
        return $prefix . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a commission receipt and mark selected commissions as paid.
     */
    public function createReceiptAndMarkPaid(
        int $salesAgentId,
        array $commissionIds,
        string $ref,
        float $amount,
        $paidAt,
        ?int $paymentMethodId = null,
        ?string $notes = null
    ): CommissionReceipt {
        return DB::transaction(function () use ($salesAgentId, $commissionIds, $ref, $amount, $paidAt, $paymentMethodId, $notes) {
            $receipt = CommissionReceipt::create([
                'sales_agent_id' => $salesAgentId,
                'Ref' => $ref,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'payment_method_id' => $paymentMethodId,
                'notes' => $notes,
            ]);
            SaleCommission::whereIn('id', $commissionIds)
                ->where('sales_agent_id', $salesAgentId)
                ->where('status', 'approved')
                ->update(['status' => 'paid', 'commission_receipt_id' => $receipt->id]);
            return $receipt->fresh();
        });
    }
}
