<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <link rel="stylesheet" href="/css/master.css">
    <link rel="icon" href="{{ asset('images/' . ($app_settings->favicon ?? 'favicon.ico')) }}">

    {{-- PWA: manifest + theme + apple touch icon. Pure addition, no effect on existing behavior. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2f3640">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $app_settings->app_name ?? 'Stocky' }}">
    <link rel="apple-touch-icon" href="/pwa_images/pwa-icon-192.png">

    <title>{{ $app_settings->app_name ?? 'Stocky | Ultimate Inventory With POS' }}</title>

  </head>

  <body class="text-left">
    <noscript>
      <strong>
        We're sorry but Stocky doesn't work properly without JavaScript
        enabled. Please enable it to continue.</strong
      >
    </noscript>

    <script>
      (function () {
        try {
          var c = localStorage.getItem('primaryColor');
          if (!c || !/^#([0-9a-f]{3}){1,2}$/i.test(c)) return;
          var hex = c.replace('#', '');
          if (hex.length === 3) hex = hex.split('').map(function (x) { return x + x; }).join('');
          var r = parseInt(hex.substring(0, 2), 16);
          var g = parseInt(hex.substring(2, 4), 16);
          var b = parseInt(hex.substring(4, 6), 16);
          var rgba = 'rgba(' + r + ',' + g + ',' + b + ',0.45)';
          var style = document.createElement('style');
          style.textContent =
            '.loading span{background:' + c + ' !important;}';
          document.head.appendChild(style);
        } catch (e) {}
      })();
    </script>

    <!-- built files will be auto injected -->
    <div class="loading_wrap" id="loading_wrap">
      <div class="loader_logo">
      <img src="{{ asset('images/' . ($app_settings->logo ?? 'logo.png')) }}" class="" alt="logo" />

      </div>

      <div class="loading">
        <span></span><span></span><span></span>
      </div>
    </div>
    <div id="app">
      <script src="/assets_setup/js/qrcode.js"></script>

    </div>


    <script src="/js/main.min.js?v=5.6&v={{ time() }}"></script>

    {{-- PWA: register service worker. Silently no-ops on unsupported browsers, http (non-localhost),
         or if registration fails. Does not block app boot. --}}
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
        } catch (e) { /* never break the app because of PWA */ }
      })();
    </script>

  </body>
</html>
