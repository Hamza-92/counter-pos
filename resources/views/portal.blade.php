<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="X-CSRF-TOKEN" content="{{ csrf_token() }}">
    <title>Client Portal | {{ optional($app_settings ?? null)->app_name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/' . (optional($app_settings ?? null)->favicon ?? 'favicon.ico')) }}">

    {{-- PWA --}}
    <link rel="manifest" href="/manifest-portal.webmanifest">
    <meta name="theme-color" content="#2f3640">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Portal">
    <link rel="apple-touch-icon" href="/pwa_images/pwa-icon-192.png">

    <link rel="stylesheet" href="{{ asset('css/master.css') }}">
    <style>
        :root {
            /* Portal design tokens */
            --pc-bg: #f6f8fb;
            --pc-surface: #ffffff;
            --pc-surface-alt: #f8fafc;
            --pc-border: #e6ebf2;
            --pc-border-strong: #cfd6e1;
            --pc-text: #0f172a;
            --pc-text-muted: #64748b;
            --pc-text-soft: #94a3b8;
            --pc-primary: #4f46e5;
            --pc-primary-600: #4338ca;
            --pc-primary-50: #eef2ff;
            --pc-accent: #06b6d4;
            --pc-success: #059669;
            --pc-success-bg: #ecfdf5;
            --pc-warning: #b45309;
            --pc-warning-bg: #fffbeb;
            --pc-danger: #dc2626;
            --pc-danger-bg: #fef2f2;
            --pc-header-bg: rgba(255, 255, 255, 0.85);
            --pc-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
            --pc-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            --pc-shadow-lg: 0 10px 30px -12px rgba(15, 23, 42, 0.18), 0 4px 10px -4px rgba(15, 23, 42, 0.08);
            --pc-radius-sm: 8px;
            --pc-radius: 12px;
            --pc-radius-lg: 16px;
        }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--pc-bg);
            color: var(--pc-text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        #portal-app { min-height: 100vh; }
        .portal-loading {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; flex-direction: column; gap: 1rem;
            color: var(--pc-text-muted);
        }
        .portal-loading .pc-loader-ring {
            width: 36px; height: 36px; border-radius: 50%;
            border: 3px solid rgba(79, 70, 229, 0.15);
            border-top-color: var(--pc-primary);
            animation: pc-spin 0.7s linear infinite;
        }
        @keyframes pc-spin { to { transform: rotate(360deg); } }
        .portal-loading .portal-fallback { display: none; font-size: 0.9rem; color: var(--pc-text-muted); max-width: 360px; text-align: center; }
    </style>
</head>
<body>
  <div id="portal-app">
    <div class="portal-loading">
      <div class="pc-loader-ring"></div>
      <span>Loading...</span>
      <p class="portal-fallback" id="portal-fallback">If the portal does not load, run <code>npm run dev</code> or <code>npm run production</code> to build the portal assets.</p>
    </div>
  </div>
  <script>
    window.__PORTAL_CSRF__ = '{{ csrf_token() }}';
    setTimeout(function() {
      var app = document.getElementById('portal-app');
      if (app && app.querySelector('.portal-loading') && app.querySelector('.portal-loading').textContent.indexOf('Loading') !== -1) {
        var fallback = document.getElementById('portal-fallback');
        if (fallback) fallback.style.display = 'block';
      }
    }, 4000);
  </script>
  <script src="{{ asset('js/portal.min.js') }}"></script>

  {{-- PWA: register service worker --}}
  <script>
    (function () {
      try {
        if (!('serviceWorker' in navigator)) return;
        var isSecure = window.isSecureContext === true
          || location.protocol === 'https:'
          || location.hostname === 'localhost'
          || location.hostname === '127.0.0.1';
        if (!isSecure) return;
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
      } catch (e) { /* never break the app */ }
    })();
  </script>
</body>
</html>
