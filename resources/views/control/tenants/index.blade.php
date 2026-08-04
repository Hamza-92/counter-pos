@extends('control.layout')
@section('title', 'Customers')
@section('heading', 'Customers')
@section('content')
<div class="card"><form class="row" method="get"><input style="max-width:420px" name="search" value="{{ request('search') }}" placeholder="Search customer or domain"><button class="btn secondary">Search</button><a class="btn" href="{{ route('control.tenants.create') }}">Register customer</a></form></div>
<div class="card"><table><thead><tr><th>Customer</th><th>Primary domain</th><th>Database</th><th>Subscription</th><th>Status</th></tr></thead><tbody>
@forelse($tenants as $tenant)<tr><td><a href="{{ route('control.tenants.show', $tenant) }}"><strong>{{ $tenant->name }}</strong></a><br><span class="muted">{{ $tenant->slug }}</span></td><td>{{ $tenant->primaryDomain?->normalized_host ?: '—' }}</td><td>{{ $tenant->databaseConfiguration?->database_name ?: 'Not configured' }}</td><td>{{ $tenant->subscriptions->first()?->ends_at?->toDateString() ?: 'None' }}</td><td><span class="badge {{ $tenant->status }}">{{ $tenant->status }}</span></td></tr>@empty<tr><td colspan="5" class="muted">No customers found.</td></tr>@endforelse
</tbody></table><div class="pagination">{{ $tenants->links() }}</div></div>
@endsection
