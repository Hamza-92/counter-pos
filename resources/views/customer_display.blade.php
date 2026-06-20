<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Customer Display</title>
    <link rel="icon" href="{{ asset('images/' . (($app_settings->favicon ?? null) ?: 'favicon.ico')) }}">

    {{-- PWA --}}
    <link rel="manifest" href="/manifest-customer-display.webmanifest">
    <meta name="theme-color" content="#0b0c10">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Customer Display">
    <link rel="apple-touch-icon" href="/pwa_images/pwa-icon-192.png">

    <link rel="stylesheet" href="/css/master.css">
    <style>
      html, body { margin:0; padding:0; height:100%; background:#0b0c10; color:#fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Fira Sans', 'Droid Sans', 'Helvetica Neue', Arial, sans-serif; }
      .cd-root { height:100%; display:flex; flex-direction:column; }
      .cd-header { display:flex; align-items:center; justify-content:center; padding:16px; border-bottom:1px solid rgba(255,255,255,.08); }
      .cd-header img { max-height:54px; }
      .cd-content { flex:1; display:flex; flex-direction:column; padding:16px; overflow:hidden; }
      .cd-list { flex:1; overflow:auto; }
      .cd-item { display:grid; grid-template-columns: 1fr 80px 120px; gap:12px; padding:12px 0; border-bottom:1px dashed rgba(255,255,255,.06); font-size:22px; }
      .cd-item .qty { text-align:center; }
      .cd-item .subtotal { text-align:right; }
      .cd-totals { margin-top:12px; font-size:24px; }
      .cd-row { display:flex; justify-content:space-between; padding:6px 0; }
      .cd-grand { font-size:34px; font-weight:700; padding-top:10px; border-top:1px solid rgba(255,255,255,.12); margin-top:6px; }
      .cd-footer { padding:18px; text-align:center; font-size:20px; border-top:1px solid rgba(255,255,255,.08); }
      .cd-empty { flex:1; display:flex; align-items:center; justify-content:center; font-size:28px; opacity:.8; }
      .light .cd-root { background:#f8fafc; color:#111827; }
      .light .cd-item { border-color: rgba(0,0,0,.06); }
      .light .cd-header, .light .cd-footer { border-color: rgba(0,0,0,.08); }
    </style>
</head>
<body>
  <div id="customer-display" class="cd-root">
    <!-- Vue app mounts here -->
  </div>
  <script>
    window.__APP_LOGO__ = '{{ asset('images/' . (($app_settings->logo ?? null) ?: 'logo.png')) }}';
  </script>
  <script src="/js/customer-display.min.js"></script>

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
      } catch (e) {}
    })();
  </script>
</body>
</html>


