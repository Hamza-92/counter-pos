<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="{{ asset('images/' . ($app_settings->favicon ?? 'favicon.ico')) }}">

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2f3640">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $app_settings->app_name ?? 'Stocky' }}">
    <link rel="apple-touch-icon" href="/pwa_images/pwa-icon-192.png">

    <title>{{ $app_settings->app_name ?? 'Stocky | Ultimate Inventory With POS' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
      :root {
        --surface: #ffffff;
        --primary: #6366f1;
        --primary-hover: #4f46e5;
        --primary-light: #e0e7ff;
        --primary-glow: rgba(99, 102, 241, 0.4);
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --border: #e2e8f0;
        --border-focus: #6366f1;
        --bg-input: #f8fafc;
        --bg-input-focus: #ffffff;
        --danger: #ef4444;
        --danger-soft: rgba(239, 68, 68, 0.08);
        --danger-border: rgba(239, 68, 68, 0.25);
        --success: #10b981;
        --success-soft: rgba(16, 185, 129, 0.08);
        --success-border: rgba(16, 185, 129, 0.25);
        --radius-sm: 10px;
        --radius-md: 14px;
        --radius-lg: 24px;
        --radius-full: 999px;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
        --shadow-lg: 0 20px 50px -12px rgba(0,0,0,0.12);
        --shadow-xl: 0 25px 60px -15px rgba(0,0,0,0.15);
        --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
      }

      *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

      body {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--text);
        background: #f0f0ff;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }

      /* ─── LAYOUT ─── */
      .auth-page {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 100dvh;
      }

      /* ─── HERO ─── */
      .auth-hero {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem clamp(2.5rem, 6vw, 5rem);
        background: linear-gradient(160deg, #4f46e5 0%, #6366f1 30%, #818cf8 70%, #a78bfa 100%);
        overflow: hidden;
      }

      .hero-bg-pattern {
        position: absolute;
        inset: 0;
        opacity: 0.07;
        background-image:
          radial-gradient(circle at 20% 50%, #fff 1px, transparent 1px),
          radial-gradient(circle at 80% 20%, #fff 1px, transparent 1px),
          radial-gradient(circle at 60% 80%, #fff 1px, transparent 1px);
        background-size: 60px 60px, 80px 80px, 40px 40px;
      }

      .hero-shape {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        opacity: 0.3;
        animation: float 20s ease-in-out infinite;
      }

      .hero-shape-1 {
        width: 400px; height: 400px;
        background: #c4b5fd;
        top: -100px; right: -80px;
        animation-delay: 0s;
      }

      .hero-shape-2 {
        width: 300px; height: 300px;
        background: #7c3aed;
        bottom: -60px; left: -60px;
        animation-delay: -7s;
      }

      .hero-shape-3 {
        width: 200px; height: 200px;
        background: #e0e7ff;
        top: 40%; left: 20%;
        animation-delay: -14s;
      }

      @keyframes float {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(30px, -20px) scale(1.05); }
        66% { transform: translate(-20px, 20px) scale(0.95); }
      }

      .hero-content {
        position: relative;
        z-index: 1;
        max-width: 440px;
        color: #fff;
      }

      .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: var(--radius-full);
        padding: 0.4rem 1rem;
        font-size: 0.8rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        margin-bottom: 1.5rem;
      }

      .hero-badge-dot {
        width: 6px; height: 6px;
        background: #34d399;
        border-radius: 50%;
        animation: pulse-dot 2s ease-in-out infinite;
      }

      @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.4); }
      }

      .hero-title {
        font-size: clamp(2rem, 4vw, 2.75rem);
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -0.03em;
        margin-bottom: 1rem;
      }

      .hero-subtitle {
        font-size: clamp(0.95rem, 1.5vw, 1.05rem);
        color: rgba(255,255,255,0.8);
        line-height: 1.7;
        max-width: 380px;
      }

      .hero-features {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-top: 2.5rem;
      }

      .hero-feature {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
      }

      .hero-feature-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px; height: 28px;
        background: rgba(255,255,255,0.15);
        border-radius: 8px;
        flex-shrink: 0;
      }

      .hero-feature-icon svg {
        width: 14px; height: 14px;
        stroke: #fff;
        fill: none;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
      }

      /* ─── FORM PANEL ─── */
      .auth-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: clamp(1.5rem, 5vw, 4rem);
        background: linear-gradient(180deg, #f8f9ff 0%, #f0f0ff 100%);
      }

      .auth-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 420px;
        padding: clamp(1.75rem, 4vw, 2.5rem);
        box-shadow: var(--shadow-xl);
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
      }

      .auth-card-header {
        text-align: center;
      }

      .auth-logo {
        display: flex;
        justify-content: center;
        margin-bottom: 1.25rem;
      }

      .auth-logo img {
        max-height: 46px;
        object-fit: contain;
      }

      .auth-card-title {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text);
      }

      .auth-card-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 0.35rem;
        line-height: 1.5;
      }

      /* ─── FORM ─── */
      .auth-form { display: flex; flex-direction: column; gap: 1.25rem; }

      .form-group { display: flex; flex-direction: column; gap: 0.5rem; }

      .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }

      .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-md);
        background: var(--bg-input);
        transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
      }

      .input-wrapper:focus-within {
        border-color: var(--border-focus);
        background: var(--bg-input-focus);
        box-shadow: 0 0 0 3px var(--primary-light);
      }

      .input-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        padding-left: 0.875rem;
        color: var(--text-muted);
        transition: color var(--transition);
        flex-shrink: 0;
      }

      .input-wrapper:focus-within .input-icon {
        color: var(--primary);
      }

      .input-icon svg {
        width: 18px; height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
      }

      .form-input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 0.8rem 0.875rem;
        font-size: 0.95rem;
        font-family: inherit;
        color: var(--text);
        outline: none;
        min-width: 0;
      }

      .form-input::placeholder {
        color: var(--text-muted);
      }

      .toggle-password {
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: none;
        padding: 0 0.75rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: color var(--transition);
      }

      .toggle-password:hover {
        color: var(--primary);
      }

      .toggle-password svg {
        width: 18px; height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
      }

      .form-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .remember-check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--text-secondary);
        user-select: none;
      }

      .remember-check input[type="checkbox"] {
        width: 16px; height: 16px;
        accent-color: var(--primary);
        border-radius: 4px;
        cursor: pointer;
      }

      .forgot-link {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--primary);
        text-decoration: none;
        transition: color var(--transition);
      }

      .forgot-link:hover { color: var(--primary-hover); }

      /* ─── BUTTON ─── */
      .auth-btn {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        border: none;
        border-radius: var(--radius-md);
        background: var(--primary);
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: background var(--transition), box-shadow var(--transition), transform 100ms ease;
        overflow: hidden;
        margin-top: 0.25rem;
      }

      .auth-btn:hover {
        background: var(--primary-hover);
        box-shadow: 0 4px 16px var(--primary-glow);
      }

      .auth-btn:active {
        transform: scale(0.985);
      }

      .auth-btn:disabled {
        cursor: not-allowed;
        opacity: 0.8;
      }

      .auth-btn .btn-arrow {
        transition: transform var(--transition);
      }

      .auth-btn:hover .btn-arrow {
        transform: translateX(3px);
      }

      .btn-loading {
        display: none;
        align-items: center;
        gap: 0.5rem;
      }

      .spinner {
        width: 18px; height: 18px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
      }

      @keyframes spin { to { transform: rotate(360deg); } }

      /* ─── DIVIDER ─── */
      .auth-divider {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--text-muted);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }

      .auth-divider::before,
      .auth-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
      }

      /* ─── ALERTS ─── */
      .auth-alert {
        padding: 0.75rem 1rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-size: 0.875rem;
        line-height: 1.5;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
      }

      .auth-alert ul {
        margin: 0;
        padding-left: 1rem;
        list-style: none;
      }

      .auth-alert ul li::before {
        content: '\2022';
        color: currentColor;
        opacity: 0.5;
        margin-right: 0.4rem;
      }

      .auth-alert.error {
        background: var(--danger-soft);
        border-color: var(--danger-border);
        color: #991b1b;
      }

      .auth-alert.success {
        background: var(--success-soft);
        border-color: var(--success-border);
        color: #065f46;
      }

      .alert-icon {
        flex-shrink: 0;
        margin-top: 1px;
      }

      .alert-icon svg {
        width: 18px; height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
      }

      /* ─── FOOTER ─── */
      .auth-footer {
        text-align: center;
        font-size: 0.8rem;
        color: var(--text-muted);
        padding-top: 0.5rem;
      }

      /* ─── RESPONSIVE ─── */
      @media (max-width: 1024px) {
        .auth-page { grid-template-columns: 1fr; }
        .auth-hero { display: none; }
        .auth-panel { min-height: 100dvh; }
      }

      @media (max-width: 640px) {
        .auth-panel {
          padding: 1.25rem 1rem;
          padding-top: max(1.25rem, env(safe-area-inset-top));
          padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
          background: #f8f9ff;
          align-items: flex-start;
        }
        .auth-card {
          padding: 1.5rem 1.25rem;
          border-radius: 18px;
          gap: 1.25rem;
          box-shadow: var(--shadow-md);
          margin-top: 1rem;
        }
        .auth-logo { margin-bottom: 1rem; }
        .auth-logo img { max-height: 40px; }
        .auth-card-title { font-size: 1.25rem; }
        .auth-card-subtitle { font-size: 0.85rem; }
        .auth-form { gap: 1rem; }
        .form-label { font-size: 0.75rem; }
        /* iOS: 16px+ prevents auto-zoom on focus */
        .form-input { font-size: 16px; padding: 0.75rem 0.75rem; }
        .input-icon { padding-left: 0.75rem; }
        .input-icon svg { width: 16px; height: 16px; }
        .toggle-password { padding: 0 0.625rem; }
        .auth-btn {
          padding: 0.95rem 1.25rem;
          font-size: 1rem;
          min-height: 48px;
        }
        .form-row {
          flex-wrap: wrap;
          gap: 0.5rem 1rem;
        }
        .remember-check, .forgot-link { font-size: 0.85rem; }
      }

      @media (max-width: 380px) {
        .auth-panel { padding: 1rem 0.75rem; }
        .auth-card {
          padding: 1.25rem 1rem;
          border-radius: 16px;
          gap: 1rem;
        }
        .auth-card-title { font-size: 1.15rem; }
        .form-row {
          flex-direction: column;
          align-items: flex-start;
          gap: 0.625rem;
        }
      }

      @media (max-height: 560px) and (orientation: landscape) {
        .auth-panel { padding: 1rem; align-items: flex-start; }
        .auth-card {
          padding: 1.25rem;
          gap: 1rem;
          margin: 0.5rem 0;
        }
        .auth-logo { margin-bottom: 0.5rem; }
        .auth-logo img { max-height: 36px; }
      }
    </style>
  </head>

  <body>
    <div class="auth-page">
      <!-- HERO -->
      <section class="auth-hero">
        <div class="hero-bg-pattern"></div>
        <div class="hero-shape hero-shape-1"></div>
        <div class="hero-shape hero-shape-2"></div>
        <div class="hero-shape hero-shape-3"></div>

        <div class="hero-content">
          <div class="hero-badge">
            <span class="hero-badge-dot"></span>
            {{ $app_settings->login_hero_badge ?? 'Secure & Reliable' }}
          </div>

          <h1 class="hero-title">{{ $app_settings->login_hero_title ?? 'Manage your business smarter.' }}</h1>
          <p class="hero-subtitle">
            {{ $app_settings->login_hero_subtitle ?? 'Streamline inventory, track sales, and grow your business — all from one powerful dashboard.' }}
          </p>

          <div class="hero-features">
            @php
              $features = [
                $app_settings->login_hero_feature_1 ?? 'Real-time inventory tracking',
                $app_settings->login_hero_feature_2 ?? 'Multi-location POS support',
                $app_settings->login_hero_feature_3 ?? 'Advanced reporting & analytics',
              ];
            @endphp
            @foreach ($features as $feature)
              @if ($feature)
              <div class="hero-feature">
                <span class="hero-feature-icon">
                  <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                {{ $feature }}
              </div>
              @endif
            @endforeach
          </div>
        </div>
      </section>

      <!-- FORM -->
      <section class="auth-panel">
        <div class="auth-card">
          <header class="auth-card-header">
            <div class="auth-logo">
              <img src="{{ asset('images/' . ($app_settings->logo ?? 'logo.png')) }}" alt="{{ $app_settings->app_name ?? 'Stocky' }}">
            </div>
            <h2 class="auth-card-title">{{ $app_settings->login_panel_title ?? 'Welcome back' }}</h2>
            <p class="auth-card-subtitle">
              {{ $app_settings->login_panel_subtitle ?? 'Sign in to your account to continue' }}
            </p>
          </header>

          @if (session('status'))
          <div class="auth-alert success">
            <span class="alert-icon">
              <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </span>
            <span>{{ session('status') }}</span>
          </div>
          @endif

          @if ($errors->any())
          <div class="auth-alert error">
            <span class="alert-icon">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </span>
            <div>
              <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          </div>
          @endif

          <form id="login_form" method="POST" action="{{ route('login') }}" class="auth-form">
            @csrf

            <div class="form-group">
              <label class="form-label" for="email">Email address</label>
              <div class="input-wrapper">
                <span class="input-icon">
                  <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Password</label>
              <div class="input-wrapper">
                <span class="input-icon">
                  <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input id="password" class="form-input" type="password" name="password" placeholder="Enter your password" required />
                <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                  <svg class="eye-open" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="eye-closed" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>

            <div class="form-row">
              <label class="remember-check">
                <input type="checkbox" name="remember">
                Remember me
              </label>
              <a class="forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
            </div>

            <button type="submit" class="auth-btn" id="login_submit_btn">
              <span class="btn-text">{{ $app_settings->login_btn_text ?? 'Sign in' }}</span>
              <svg class="btn-arrow btn-text" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              <span class="btn-loading"><span class="spinner"></span> Signing in...</span>
            </button>
          </form>

          <div class="auth-footer">
            {{ $app_settings->login_footer_text ?? '© ' . date('Y') . ' ' . ($app_settings->app_name ?? 'Stocky') . '. All rights reserved.' }}
          </div>
        </div>
      </section>
    </div>

    <script>
      (function() {
        const form = document.getElementById('login_form');
        const submitBtn = document.getElementById('login_submit_btn');

        document.querySelectorAll('.toggle-password').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var target = document.getElementById(btn.dataset.target);
            var isHidden = target.type === 'password';
            target.type = isHidden ? 'text' : 'password';
            btn.querySelector('.eye-open').style.display = isHidden ? 'none' : 'block';
            btn.querySelector('.eye-closed').style.display = isHidden ? 'block' : 'none';
          });
        });

        if (!form || !submitBtn) return;

        function setBusy() {
          submitBtn.disabled = true;
          submitBtn.setAttribute('aria-busy', 'true');
          submitBtn.querySelectorAll('.btn-text').forEach(function(el) { el.style.display = 'none'; });
          submitBtn.querySelector('.btn-loading').style.display = 'inline-flex';
        }

        function clearBusy() {
          submitBtn.disabled = false;
          submitBtn.removeAttribute('aria-busy');
          submitBtn.querySelectorAll('.btn-text').forEach(function(el) { el.style.display = ''; });
          submitBtn.querySelector('.btn-loading').style.display = 'none';
        }

        var submitted = false;
        form.addEventListener('submit', function(e) {
          if (submitted) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
          }

          // Always refresh the CSRF token right before submit. This protects
          // against stale tokens from cached HTML (bfcache, browser cache,
          // service-worker shell) which were causing 419 Page Expired.
          e.preventDefault();
          submitted = true;
          setBusy();

          fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          })
          .then(function(r) { return r.ok ? r.json() : null; })
          .then(function(data) {
            if (data && data.token) {
              var input = form.querySelector('input[name="_token"]');
              if (input) input.value = data.token;
              var meta = document.querySelector('meta[name="csrf-token"]');
              if (meta) meta.setAttribute('content', data.token);
            }
          })
          .catch(function() {})
          .finally(function() {
            form.submit();
          });
        });

        submitBtn.addEventListener('click', function(e) {
          if (submitted) {
            e.preventDefault();
            e.stopImmediatePropagation();
          }
        });

        // If the browser restores this page from bfcache (back/forward), the
        // CSRF token inside the form may be stale. Reset UI so the submit
        // handler runs again and refreshes the token.
        window.addEventListener('pageshow', function(ev) {
          if (ev.persisted) {
            submitted = false;
            clearBusy();
          }
        });

        // Self-heal: unregister any stale service worker on this page. The
        // login page must always be served fresh — if an older SW is still
        // holding a cached /login shell, drop it and clear its caches.
        try {
          if ('serviceWorker' in navigator && navigator.serviceWorker.getRegistrations) {
            navigator.serviceWorker.getRegistrations().then(function(regs) {
              regs.forEach(function(reg) { reg.unregister().catch(function() {}); });
            }).catch(function() {});
            if (window.caches && caches.keys) {
              caches.keys().then(function(keys) {
                keys.forEach(function(k) {
                  if (k && k.indexOf('shell') !== -1) caches.delete(k).catch(function() {});
                });
              }).catch(function() {});
            }
          }
        } catch (e) {}
      })();
    </script>

    {{-- Login page is intentionally NOT a PWA surface: no service worker is
         registered here. The SW is registered on the authenticated app shell
         instead, so the login HTML (which carries a session-specific CSRF
         token) is always served fresh from the network. --}}
  </body>
</html>
