<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <link rel="stylesheet" href="/css/master.css">
    
    <link rel="icon" href="{{ asset('images/' . ($app_settings->favicon ?? 'favicon.ico')) }}">
    <title>{{ $app_settings->app_name ?? 'Stocky | Ultimate Inventory With POS' }}</title>
    <style>
      .auth-wrapper{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f5f7ff,#eef9ff);padding:24px}
      .auth-card{width:100%;max-width:520px;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:28px}
      .auth-brand{display:flex;justify-content:center;margin-bottom:12px}
      .auth-brand img{max-height:56px}
      .auth-title{margin:10px 0 2px 0;font-size:22px;line-height:1.2;text-align:center}
      .auth-subtitle{margin:0 0 18px 0;color:#6b7280;text-align:center;font-size:14px}
      .auth-btn{width:100%;padding:12px 14px;border:none;border-radius:10px;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer;transition:background .15s}
      .auth-btn:hover{background:#4338ca}
      .auth-alert{padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:12px}
      .auth-alert.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
      .auth-alert.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
      .auth-actions{display:flex;gap:8px;margin-top:12px}
    </style>
  </head>
  <body class="text-left">
    <noscript>
      <strong>
        We're sorry but Stocky doesn't work properly without JavaScript
        enabled. Please enable it to continue.</strong>
    </noscript>
    <div class="auth-wrapper">
      <div class="auth-card">
        <div class="auth-brand">
          <img src="{{ asset('images/' . ($app_settings->logo ?? 'logo.png')) }}" alt="logo" />
        </div>
        @if (session('resent'))
        <div class="auth-alert success" role="alert">
          {{ __('A fresh verification link has been sent to your email address.') }}
        </div>
        @endif
        <h1 class="auth-title">{{ __('Verify Your Email') }}</h1>
        <p class="auth-subtitle">{{ __('Before proceeding, please check your email for a verification link.') }}</p>
        <form method="POST" action="{{ route('verification.resend') }}">
          @csrf
          <button type="submit" class="auth-btn">{{ __('Resend verification email') }}</button>
        </form>
      </div>
    </div>
  </body>
</html>
