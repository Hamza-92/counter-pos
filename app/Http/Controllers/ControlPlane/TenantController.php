<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\Domain;
use App\Models\ControlPlane\ManualPayment;
use App\Models\ControlPlane\Plan;
use App\Models\ControlPlane\Subscription;
use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use App\Services\ControlPlane\AuditService;
use App\Tenancy\Exceptions\TenantDatabaseException;
use App\Tenancy\TenantDatabaseManager;
use App\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::query()->with(['primaryDomain', 'databaseConfiguration', 'subscriptions.plan']);
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(static function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('domains', fn ($domain) => $domain->where('normalized_host', 'like', '%'.$search.'%'));
            });
        }

        return view('control.tenants.index', ['tenants' => $query->latest()->paginate(25)]);
    }

    public function create(): View
    {
        return view('control.tenants.create');
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('control.tenants', 'slug')],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $tenant = Tenant::query()->create($data + ['status' => 'provisioning']);
        $audit->record('tenant.created', $tenant, null, $tenant->only(['name', 'slug', 'status']));

        return redirect()->route('control.tenants.show', $tenant)->with('status', 'Tenant registered. Add its domain and database next.');
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['domains', 'databaseConfiguration', 'subscriptions.plan', 'subscriptions.payments']);

        return view('control.tenants.show', [
            'tenant' => $tenant,
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function addDomain(Request $request, Tenant $tenant, AuditService $audit, TenantResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'is_primary' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
        ]);

        $domain = DB::connection('control')->transaction(function () use ($tenant, $data) {
            if (! empty($data['is_primary'])) {
                Domain::query()->where('tenant_id', $tenant->id)->update(['is_primary' => false]);
            }

            return $tenant->domains()->create([
                'host' => $data['host'],
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'verified_at' => ! empty($data['verified']) ? now() : null,
            ]);
        });

        $resolver->forget($domain->normalized_host);
        $audit->record('domain.created', $domain, null, $domain->only(['normalized_host', 'is_primary', 'verified_at']));

        return back()->with('status', 'Domain added.');
    }

    public function verifyDomain(Domain $domain, AuditService $audit, TenantResolver $resolver): RedirectResponse
    {
        $before = $domain->only(['verified_at']);
        $domain->forceFill(['verified_at' => now()])->save();
        $resolver->forget($domain->normalized_host);
        $audit->record('domain.verified', $domain, $before, $domain->only(['verified_at']));

        return back()->with('status', 'Domain marked verified.');
    }

    public function saveDatabase(Request $request, Tenant $tenant, AuditService $audit, TenantDatabaseManager $manager): RedirectResponse
    {
        $existing = $tenant->databaseConfiguration;
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database_name' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:191'],
            'username' => ['required', 'string', 'max:191'],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'max:1000'],
            'migration_username' => ['nullable', 'string', 'max:191'],
            'migration_password' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($existing && empty($data['password'])) {
            unset($data['password']);
        }
        if ($existing && empty($data['migration_password'])) {
            unset($data['migration_password']);
        }

        $database = $existing ?: new TenantDatabase(['tenant_id' => $tenant->id, 'driver' => 'mysql']);
        $before = $existing?->only(['host', 'port', 'database_name', 'username', 'migration_username', 'credential_version']);
        $database->fill($data);
        if ($existing) {
            $database->credential_version++;
        }
        $manager->assertCredentialPolicy($database);
        $database->save();
        $audit->record('tenant_database.saved', $database, $before, $database->only(['host', 'port', 'database_name', 'username', 'migration_username', 'credential_version']));

        return back()->with('status', 'Database credentials saved securely.');
    }

    public function testDatabase(Tenant $tenant, TenantDatabaseManager $manager, AuditService $audit): RedirectResponse
    {
        $database = $tenant->databaseConfiguration;
        if (! $database) {
            return back()->withErrors(['database' => 'No database is configured.']);
        }

        try {
            $manager->initializeDatabase($database);
            $database->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_succeeded' => true,
                'last_connection_error' => null,
            ])->save();
            $audit->record('tenant_database.test_succeeded', $database);

            return back()->with('status', 'Database connection and exact database name verified.');
        } catch (TenantDatabaseException) {
            $database->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_succeeded' => false,
                'last_connection_error' => 'Connection or database identity verification failed.',
            ])->save();
            $audit->record('tenant_database.test_failed', $database);

            return back()->withErrors(['database' => 'Connection failed. Check the allowlist and credentials.']);
        } finally {
            $manager->reset();
        }
    }

    public function addSubscription(Request $request, Tenant $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('control.plans', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'grace_ends_at' => ['nullable', 'date', 'after_or_equal:ends_at'],
            'agreed_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subscription = $tenant->subscriptions()->create($data + [
            'status' => now()->between($data['starts_at'], $data['ends_at']) ? 'active' : 'pending',
            'currency' => strtoupper($data['currency']),
            'created_by' => auth('control')->id(),
        ]);
        $audit->record('subscription.created', $subscription, null, $subscription->only(['plan_id', 'status', 'starts_at', 'ends_at', 'agreed_amount', 'currency']));

        return back()->with('status', 'Subscription recorded.');
    }

    public function addPayment(Request $request, Tenant $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', Rule::exists('control.subscriptions', 'id')->where('tenant_id', $tenant->id)],
            'reference' => ['nullable', 'string', 'max:191', Rule::unique('control.manual_payments', 'reference')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = ManualPayment::query()->create($data + [
            'tenant_id' => $tenant->id,
            'currency' => strtoupper($data['currency']),
            'recorded_by' => auth('control')->id(),
            'recorded_at' => now(),
        ]);
        $audit->record('payment.recorded', $payment, null, $payment->only(['subscription_id', 'reference', 'amount', 'currency', 'paid_at', 'method']));

        return back()->with('status', 'Payment recorded in the append-only ledger.');
    }

    public function reversePayment(Request $request, ManualPayment $payment, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        if (ManualPayment::query()->where('reversal_of_id', $payment->id)->exists() || $payment->reversal_of_id) {
            return back()->withErrors(['payment' => 'This payment cannot be reversed again.']);
        }

        $reversal = ManualPayment::query()->create([
            'tenant_id' => $payment->tenant_id,
            'subscription_id' => $payment->subscription_id,
            'reversal_of_id' => $payment->id,
            'reference' => 'REV-'.Str::upper(Str::random(12)),
            'amount' => -abs((float) $payment->amount),
            'currency' => $payment->currency,
            'paid_at' => now(),
            'method' => 'reversal',
            'notes' => $data['notes'],
            'recorded_by' => auth('control')->id(),
            'recorded_at' => now(),
        ]);
        $audit->record('payment.reversed', $reversal, null, ['reversal_of_id' => $payment->id, 'amount' => $reversal->amount]);

        return back()->with('status', 'A reversal entry was appended.');
    }

    public function changeStatus(Request $request, Tenant $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'archived'])],
            'reason' => ['nullable', 'required_if:status,suspended,archived', 'string', 'max:2000'],
            'version' => ['required', 'integer'],
        ]);

        if ((int) $data['version'] !== (int) $tenant->version) {
            return back()->withErrors(['status' => 'This tenant was changed in another tab. Refresh and retry.']);
        }

        if ($data['status'] === 'active') {
            $ready = $tenant->databaseConfiguration()->exists()
                && $tenant->domains()->where('is_primary', true)->whereNotNull('verified_at')->exists()
                && $tenant->subscriptions()->whereIn('status', ['active', 'grace'])->where('ends_at', '>', now())->exists();
            if (! $ready) {
                return back()->withErrors(['status' => 'Activation requires a database, verified primary domain, and current subscription.']);
            }
        }

        $before = $tenant->only(['status', 'manual_suspension_reason', 'version']);
        $tenant->forceFill([
            'status' => $data['status'],
            'manual_suspension_reason' => $data['status'] === 'active' ? null : $data['reason'],
            'activated_at' => $data['status'] === 'active' ? now() : $tenant->activated_at,
            'suspended_at' => $data['status'] === 'suspended' ? now() : null,
            'version' => $tenant->version + 1,
        ])->save();
        $audit->record('tenant.status_changed', $tenant, $before, $tenant->only(['status', 'manual_suspension_reason', 'version']));

        return back()->with('status', 'Tenant status updated.');
    }
}
