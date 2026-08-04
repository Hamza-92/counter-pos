@extends('control.layout')
@section('title', 'Overview')
@section('heading', 'Customer overview')
@section('content')
<div class="grid">
    <div class="card"><div class="muted">Customers</div><div class="metric">{{ $tenantCount }}</div></div>
    <div class="card"><div class="muted">Active</div><div class="metric">{{ $activeCount }}</div></div>
    <div class="card"><div class="muted">Suspended</div><div class="metric">{{ $suspendedCount }}</div></div>
    <div class="card"><div class="muted">Expiring in 30 days</div><div class="metric">{{ $expiringCount }}</div></div>
</div>
<div class="card"><div class="row between"><h2 class="section-title">Recent customers</h2><a class="btn" href="{{ route('control.tenants.create') }}">Register customer</a></div>
<table><thead><tr><th>Customer</th><th>Domain</th><th>Status</th><th>Schema</th></tr></thead><tbody>
@forelse($recentTenants as $tenant)<tr><td><a href="{{ route('control.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td><td>{{ $tenant->primaryDomain?->normalized_host ?: '—' }}</td><td><span class="badge {{ $tenant->status }}">{{ $tenant->status }}</span></td><td>{{ $tenant->schema_version }}</td></tr>@empty<tr><td colspan="4" class="muted">No customers registered.</td></tr>@endforelse
</tbody></table></div>
<div class="card"><h2 class="section-title">Recent security audit</h2><table><thead><tr><th>Time</th><th>Action</th><th>Target</th></tr></thead><tbody>@forelse($recentAudit as $item)<tr><td>{{ $item->created_at }}</td><td class="code">{{ $item->action }}</td><td>{{ $item->target_type }} {{ $item->target_id }}</td></tr>@empty<tr><td colspan="3" class="muted">No audit events.</td></tr>@endforelse</tbody></table></div>
@endsection
