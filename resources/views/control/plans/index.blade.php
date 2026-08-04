@extends('control.layout')
@section('title', 'Plans')
@section('heading', 'Plans and pricing')
@section('content')
<div class="card"><h2 class="section-title">Create plan</h2><form method="post" action="{{ route('control.plans.store') }}">@csrf<div class="fields">
<div><label>Name</label><input name="name" required></div><div><label>Interval</label><select name="billing_interval"><option>monthly</option><option>quarterly</option><option>yearly</option><option>custom</option></select></div>
<div><label>Price</label><input type="number" step="0.01" min="0" name="price" required></div><div><label>Currency</label><input name="currency" value="PKR" maxlength="3" required></div>
<div class="field full"><label>Features (one per line)</label><textarea name="features"></textarea></div></div><p><button class="btn">Create plan</button></p></form></div>
<div class="card"><table><thead><tr><th>Name</th><th>Price</th><th>Interval</th><th>Subscriptions</th><th>Status</th><th></th></tr></thead><tbody>@forelse($plans as $plan)<tr><td>{{ $plan->name }}</td><td>{{ $plan->currency }} {{ $plan->price }}</td><td>{{ $plan->billing_interval }}</td><td>{{ $plan->subscriptions_count }}</td><td><span class="badge {{ $plan->is_active ? 'active' : '' }}">{{ $plan->is_active ? 'active' : 'inactive' }}</span></td><td><form method="post" action="{{ route('control.plans.toggle', $plan) }}">@csrf<button class="btn secondary">{{ $plan->is_active ? 'Deactivate' : 'Activate' }}</button></form></td></tr>@empty<tr><td colspan="6">No plans.</td></tr>@endforelse</tbody></table></div>
@endsection
