<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\Plan;
use App\Services\ControlPlane\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('control.plans.index', ['plans' => Plan::query()->withCount('subscriptions')->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'billing_interval' => ['required', Rule::in(['monthly', 'quarterly', 'yearly', 'custom'])],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'features' => ['nullable', 'string', 'max:4000'],
        ]);

        $plan = Plan::query()->create([
            'name' => $data['name'],
            'billing_interval' => $data['billing_interval'],
            'price' => $data['price'],
            'currency' => strtoupper($data['currency']),
            'features' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['features'] ?? '')))),
            'is_active' => true,
        ]);
        $audit->record('plan.created', $plan, null, $plan->only(['name', 'billing_interval', 'price', 'currency', 'features']));

        return back()->with('status', 'Plan created.');
    }

    public function toggle(Plan $plan, AuditService $audit): RedirectResponse
    {
        $before = ['is_active' => $plan->is_active];
        $plan->forceFill(['is_active' => ! $plan->is_active])->save();
        $audit->record('plan.status_changed', $plan, $before, ['is_active' => $plan->is_active]);

        return back()->with('status', 'Plan status updated.');
    }
}
