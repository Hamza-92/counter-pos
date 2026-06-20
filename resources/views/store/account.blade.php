@extends('layouts.store')

@section('content')
@php
  /** @var \App\Models\EcommerceClient|null $me */
  $me = Auth::guard('store')->user();
  $updateUrl = url('/online_store/account');
  $ordersUrl = url('/online_store/account/orders');
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6 flex items-center justify-between flex-wrap gap-3">
    <div>
      <span class="section-kicker">{{ __('messages.Account') }}</span>
      <h1 class="section-title mt-1">{{ __('messages.MyAccount') }}</h1>
      <div class="text-fg-muted text-sm mt-1">{{ __('messages.ManageProfileAndOrders') }}</div>
    </div>
    <a href="{{ $ordersUrl }}" class="btn btn-outline">
      <x-store.icon name="receipt" class="w-4 h-4" />{{ __('messages.MyOrders') }}
    </a>
  </div>
</section>

<div class="container py-8">
  <div class="max-w-3xl mx-auto space-y-4">

    @if (session('status'))
      <div class="alert alert-success flex items-start gap-2">
        <x-store.icon name="check-circle" class="w-5 h-5 mt-0.5 shrink-0" />
        <span>{{ session('status') }}</span>
      </div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="m-0 ps-5 list-disc">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    {{-- Profile --}}
    <div class="card">
      <div class="card-body">
        <h5 class="font-semibold mb-4 flex items-center gap-2">
          <x-store.icon name="user" class="w-5 h-5 text-accent-500" />{{ __('messages.Profile') }}
        </h5>

        @if(!$me)
          <div class="alert alert-warning m-0">
            {{ __('messages.MustBeSignedIn') }}
          </div>
        @else
          <form method="POST" action="{{ $updateUrl }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">{{ __('messages.Username') }}</label>
                <input name="username" type="text" class="input" value="{{ old('username', $me->username) }}">
              </div>
              <div>
                <label class="form-label">{{ __('messages.Email') }}</label>
                <input name="email" type="email" class="input" value="{{ old('email', $me->email) }}">
              </div>
              <div>
                <label class="form-label">{{ __('messages.NewPassword') }}</label>
                <input name="password" type="password" class="input" autocomplete="new-password" placeholder="••••••••">
              </div>
              <div>
                <label class="form-label">{{ __('messages.ConfirmPassword') }}</label>
                <input name="password_confirmation" type="password" class="input" autocomplete="new-password" placeholder="••••••••">
              </div>
            </div>

            <div class="flex gap-2 flex-wrap pt-2">
              <button class="btn btn-primary" type="submit">
                <x-store.icon name="check" class="w-4 h-4" />{{ __('messages.SaveChanges') }}
              </button>
              <a href="{{ $ordersUrl }}" class="btn btn-outline">
                <x-store.icon name="receipt" class="w-4 h-4" />{{ __('messages.ViewOrders') }}
              </a>
            </div>
          </form>
        @endif
      </div>
    </div>

    {{-- Account meta --}}
    @if($me)
      <div class="card">
        <div class="card-body">
          <h6 class="font-semibold mb-4 flex items-center gap-2">
            <x-store.icon name="info" class="w-4 h-4 text-accent-500" />{{ __('messages.AccountDetails') }}
          </h6>
          <div class="grid md:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-fg-muted text-xs">{{ __('messages.ClientID') }}</div>
              <div class="font-semibold mt-0.5">{{ $me->client_id ?? '—' }}</div>
            </div>
            <div>
              <div class="text-fg-muted text-xs">{{ __('messages.Status') }}</div>
              <div class="mt-0.5">
                @if((int)$me->status === 1)
                  <span class="chip chip-success">{{ __('messages.Active') }}</span>
                @else
                  <span class="chip">{{ __('messages.Inactive') }}</span>
                @endif
              </div>
            </div>
            <div>
              <div class="text-fg-muted text-xs">{{ __('messages.CreatedAt') }}</div>
              <div class="font-semibold mt-0.5">{{ optional($me->created_at)->toDateTimeString() ?? '—' }}</div>
            </div>
            <div>
              <div class="text-fg-muted text-xs">{{ __('messages.UpdatedAt') }}</div>
              <div class="font-semibold mt-0.5">{{ optional($me->updated_at)->toDateTimeString() ?? '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    @endif

  </div>
</div>
@endsection
