<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\ControlAuditLog;
use App\Models\ControlPlane\ManualPayment;
use App\Models\ControlPlane\Subscription;
use App\Models\ControlPlane\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('control.dashboard', [
            'tenantCount' => Tenant::query()->count(),
            'activeCount' => Tenant::query()->where('status', 'active')->count(),
            'suspendedCount' => Tenant::query()->where('status', 'suspended')->count(),
            'expiringCount' => Subscription::query()->whereIn('status', ['active', 'grace'])
                ->whereBetween('ends_at', [now(), now()->addDays(30)])->count(),
            'paymentTotal' => ManualPayment::query()->sum('amount'),
            'recentTenants' => Tenant::query()->with('primaryDomain')->latest()->limit(8)->get(),
            'recentAudit' => ControlAuditLog::query()->latest('created_at')->limit(10)->get(),
        ]);
    }
}
