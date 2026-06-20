@extends('layouts.store')

@section('content')
@php
  $redirect = $redirect ?? request('redirect', route('checkout'));
  $registrationEnabled = $registrationEnabled ?? true;
  $requireInviteCode = $requireInviteCode ?? false;
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <h1 class="section-title">{{ __('messages.CreateAccount') }}</h1>
  </div>
</section>

<div class="container py-10">
  <div class="max-w-lg mx-auto space-y-3">

    @if (! $registrationEnabled)
      <div class="card">
        <div class="card-body p-8 text-center">
          <div class="inline-flex w-14 h-14 rounded-full items-center justify-center text-warning mb-3"
               style="background: rgb(var(--color-warning) / .12);">
            <x-store.icon name="shield-check" class="w-7 h-7" />
          </div>
          <h5 class="font-bold text-lg">{{ __('messages.RegistrationDisabled') }}</h5>
          <p class="text-fg-muted mt-2 mb-4">{{ __('messages.RegistrationDisabledHelp') }}</p>
          <a class="btn btn-outline" href="{{ route('store.login.show', ['redirect' => $redirect]) }}">
            <x-store.icon name="log-in" class="w-4 h-4" />{{ __('messages.SignIn') }}
          </a>
        </div>
      </div>

      <div class="text-center mt-3">
        <a class="btn btn-ghost" href="{{ route('store.shop') }}">
          <x-store.icon name="arrow-left" class="w-4 h-4" />{{ __('messages.BackToShop') }}
        </a>
      </div>
      @return
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="m-0 list-disc ps-5">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-body p-6 md:p-8">
        <div class="text-center mb-6">
          <div class="inline-flex w-14 h-14 rounded-full items-center justify-center text-accent-500 mb-3"
               style="background: rgb(var(--color-accent-500) / .1);">
            <x-store.icon name="user-plus" class="w-7 h-7" />
          </div>
          <h5 class="text-xl font-bold m-0">{{ __('messages.JoinUs') }}</h5>
          <div class="text-fg-muted text-sm mt-1">{{ __('messages.CreateStoreAccountFaster') }}</div>
        </div>

        <form method="POST" action="{{ route('store.register') }}" novalidate class="space-y-4">
          @csrf
          <input type="hidden" name="redirect" value="{{ $redirect }}"/>

          <div>
            <label class="form-label">{{ __('messages.FullName') }}</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="input @error('name') is-invalid @enderror" autocomplete="name" required>
            @error('name') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
          </div>

          <div>
            <label class="form-label">{{ __('messages.Email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="input @error('email') is-invalid @enderror" autocomplete="email" required>
            @error('email') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="form-label">{{ __('messages.Phone') }}</label>
              <input type="tel" name="phone" value="{{ old('phone') }}"
                     class="input @error('phone') is-invalid @enderror" autocomplete="tel" required>
              @error('phone') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="form-label">{{ __('messages.Address') }}</label>
              <input type="text" name="address" value="{{ old('address') }}"
                     class="input @error('address') is-invalid @enderror" autocomplete="street-address" required>
              @error('address') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>
          </div>

          @if ($requireInviteCode)
          <div>
            <label class="form-label">{{ __('messages.InviteCode') }}</label>
            <div class="relative">
              <x-store.icon name="ticket" class="w-4 h-4 absolute top-1/2 -translate-y-1/2" style="inset-inline-start: 12px; color: rgb(var(--color-fg-muted));" />
              <input type="text" name="invite_code" value="{{ old('invite_code') }}"
                     class="input @error('invite_code') is-invalid @enderror" style="padding-inline-start: 36px;"
                     placeholder="{{ __('messages.EnterInviteCode') }}" required>
            </div>
            @error('invite_code') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            <small class="text-fg-muted text-xs mt-1 block">{{ __('messages.InviteCodeRequired') }}</small>
          </div>
          @endif

          <div>
            <label class="form-label">{{ __('messages.Password') }}</label>
            <div class="flex gap-2">
              <input type="password" name="password" id="regPass"
                     class="input flex-1 @error('password') is-invalid @enderror" autocomplete="new-password" required>
              <button class="btn btn-outline btn-icon" type="button" onclick="togglePass('regPass', this)" aria-label="Show password">
                <span data-eye="show"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
              </button>
            </div>
            <small class="text-fg-muted text-xs mt-1 block">{{ __('messages.Minimum6Chars') }}</small>
            @error('password') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
          </div>

          <div>
            <label class="form-label">{{ __('messages.ConfirmPassword') }}</label>
            <input type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <x-store.icon name="user-plus" class="w-5 h-5" />{{ __('messages.CreateAccount') }}
          </button>
        </form>

        <div class="text-center mt-5 text-sm text-fg-muted">
          {{ __('messages.AlreadyHaveAccountQ') }}
          <a href="{{ route('store.login.show', ['redirect' => $redirect]) }}" class="text-accent-500 hover:underline font-medium">{{ __('messages.SignIn') }}</a>
        </div>
      </div>
    </div>

    <div class="text-center mt-3">
      <a class="btn btn-ghost" href="{{ route('store.shop') }}">
        <x-store.icon name="arrow-left" class="w-4 h-4" />{{ __('messages.BackToShop') }}
      </a>
    </div>
  </div>
</div>

<script>
  function togglePass(id, btn){
    const el = document.getElementById(id);
    if (!el) return;
    const isText = el.type === 'text';
    el.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
      ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';
  }
</script>
@endsection
