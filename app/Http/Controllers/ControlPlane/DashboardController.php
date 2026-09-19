<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\ControlAuditLog;
use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('control.dashboard', [
            'tenantCount' => Tenant::query()->count(),
            'activeCount' => Tenant::query()->where('status', 'active')->count(),
            'verifiedDomainCount' => Domain::query()->whereNotNull('verified_at')->count(),
            'databaseCount' => TenantDatabase::query()->count(),
            'recentTenants' => Tenant::query()->with('primaryDomain')->latest()->limit(8)->get(),
            'recentAudit' => ControlAuditLog::query()->latest('created_at')->limit(10)->get(),
        ]);
    }
}
