<?php

namespace App\Listeners\AccountingV2\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

trait SkipsWhenManual
{
    protected function shouldSkipAutomation(): bool
    {
        return ! Config::get('accounting_v2.auto_generate_journals', false);
    }

    protected function shouldSkipForModel($model): bool
    {
        if ($this->shouldSkipAutomation()) {
            return true;
        }

        $baseline = Config::get('accounting_v2.baseline_date');
        if (! $baseline || ! $model) {
            return false;
        }

        try {
            $createdAt = $model->created_at ?? null;
            if (! $createdAt) {
                return false;
            }

            return Carbon::parse($createdAt)->lt(Carbon::parse($baseline)->startOfDay());
        } catch (\Throwable $e) {
            return false;
        }
    }
}
