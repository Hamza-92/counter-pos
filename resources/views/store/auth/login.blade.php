@extends('layouts.store')

@section('content')
@php
  $redirect = $redirect ?? route('checkout');
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <h1 class="section-title">{{ __('messages.SignIn') }}</h1>
  </div>
</section>

<div class="container py-10">
  <div class="max-w-md mx-auto space-y-3">

    @if(Auth::guard('store')->check())
      <div class="alert alert-success flex items-start gap-2">
        <x-store.icon name="check-circle" class="w-5 h-5 shrink-0 mt-0.5" />
        <div>{{ __('messages.AlreadySignedIn') }}</div>
      </div>
    @endif

    @if(session('pending_approval'))
      <div class="alert alert-warning flex items-start gap-2">
        <x-store.icon name="clock" class="w-5 h-5 shrink-0 mt-0.5" />
        <div>
          <strong>{{ __('messages.AccountPendingApproval') }}</strong>
          <div class="text-xs mt-1 opacity-90">{{ __('messages.AccountPendingApprovalHelp') }}</div>
        </div>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="m-0 list-disc ps-5">
          @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-body p-6 md:p-8">
        <div class="text-center mb-6">
          <div class="inline-flex w-14 h-14 rounded-full items-center justify-center text-accent-500 mb-3"
               style="background: rgb(var(--color-accent-500) / .1);">
            <x-store.icon name="bag" class="w-7 h-7" />
          </div>
          <h2 class="text-xl font-bold m-0">{{ __('messages.WelcomeBack') }}</h2>
          <div class="text-fg-muted text-sm mt-1">{{ __('messages.SignInContinueCheckout') }}</div>
        </div>

        <form method="POST" action="{{ route('store.login') }}" novalidate class="space-y-4">
          @csrf
          <input type="hidden" name="redirect" value="{{ $redirect }}">

          <div>
            <label class="form-label">{{ __('messages.EmailAddress') }}</label>
            <div class="input-icon-wrap">
              <x-store.icon name="mail" class="w-4 h-4 input-icon" />
              <input type="email" name="email" class="input input-with-icon"
                     value="{{ old('email') }}" required autocomplete="email"
                     placeholder="{{ __('messages.EmailPlaceholder') }}">
            </div>
          </div>

          <div>
            <label class="form-label">{{ __('messages.Password') }}</label>
            <div class="input-icon-wrap">
              <x-store.icon name="shield-check" class="w-4 h-4 input-icon" />
              <input type="password" name="password" class="input input-with-icon"
                     required autocomplete="current-password" placeholder="••••••••">
            </div>
          </div>

          <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" name="remember" id="remember_me" value="1" class="checkbox">
            <span>{{ __('messages.RememberMe') }}</span>
          </label>

          <button class="btn btn-primary btn-block btn-lg" type="submit">
            <x-store.icon name="log-in" class="w-5 h-5" />{{ __('messages.SignIn') }}
          </button>
        </form>

        <div class="text-center mt-5 text-sm text-fg-muted">
          {{ __('messages.DontHaveAccountQ') }}
          <a href="{{ route('store.register.show', ['redirect' => $redirect]) }}" class="text-accent-500 hover:underline font-medium">{{ __('messages.CreateOne') }}</a>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
  .input-icon-wrap { position: relative; }
  .input-icon-wrap .input-icon {
    position: absolute;
    inset-inline-start: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgb(var(--color-fg-muted));
    pointer-events: none;
  }
  .input-icon-wrap .input-with-icon { padding-inline-start: 36px; }
</style>
@endsection
