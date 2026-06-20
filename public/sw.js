/*
 * Stocky POS Service Worker
 *
 * Goals:
 *   - Make the POS installable and bootable offline (app shell).
 *   - NEVER interfere with the existing offline-sync logic in
 *     resources/src/utils/globalOfflineSync.js or resources/src/utils/index.js.
 *
 * Non-negotiable rules:
 *   1. Only GET requests are intercepted. All POST/PUT/PATCH/DELETE pass
 *      straight through to the network so axios errors still fire and the
 *      Vue-side offline queue (pos_offline_sales_v1) still works.
 *   2. /api/* and /sanctum/* are always network-only. No caching, no
 *      fallback. A stale /api/ping response would corrupt offline sync.
 *   3. Login/logout/password/setup/update/portal routes pass through.
 *   4. If anything goes wrong, flip KILL_SWITCH to true and redeploy.
 *      Existing clients will self-unregister on next load.
 */

// Bump this when deploying changes so old caches are purged.
const VERSION = 'stocky-pwa-v5';
const STATIC_CACHE = `${VERSION}-static`;
const SHELL_CACHE = `${VERSION}-shell`;

// Emergency kill switch — set to true and redeploy to disable PWA globally.
const KILL_SWITCH = false;

// Offline fallback for navigation requests when network + cache both fail.
const OFFLINE_URL = '/offline.html';

// Precache only tiny, stable assets. Everything else is cached lazily on
// first successful GET.
const PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/pwa_images/pwa-icon-192.png',
  '/pwa_images/pwa-icon-512.png',
];

// URL prefixes / patterns that must NEVER be cached or served from cache.
// These are the genuinely dynamic/sensitive paths: APIs, auth mutations,
// installation/update flows, realtime transports.
// Navigational shells for /portal, /customer-display, /online_store, etc. are
// intentionally NOT here so each surface can boot offline.
// /login, /logout, /password are network-only so a cached HTML shell cannot
// serve a stale CSRF token (was causing 419 on login submit).
const NETWORK_ONLY_PREFIXES = [
  '/api/',
  '/sanctum/',
  '/login',
  '/logout',
  '/password',
  '/setup',
  '/update',
  '/broadcasting/',
  '/livewire/',
];

// Static asset patterns that are safe to cache (cache-first).
function isStaticAsset(url) {
  const p = url.pathname;
  return (
    p.startsWith('/js/') ||
    p.startsWith('/css/') ||
    p.startsWith('/fonts/') ||
    p.startsWith('/images/') ||
    p.startsWith('/flags/') ||
    p.startsWith('/audio/') ||
    p.startsWith('/vendor/') ||
    p.startsWith('/assets_setup/') ||
    p === '/favicon.ico' ||
    p === '/robots.txt'
  );
}

function isNetworkOnly(url) {
  const p = url.pathname;
  for (let i = 0; i < NETWORK_ONLY_PREFIXES.length; i++) {
    if (p === NETWORK_ONLY_PREFIXES[i] || p.startsWith(NETWORK_ONLY_PREFIXES[i])) {
      return true;
    }
  }
  return false;
}

self.addEventListener('install', (event) => {
  if (KILL_SWITCH) {
    self.skipWaiting();
    return;
  }
  event.waitUntil(
    (async () => {
      const cache = await caches.open(STATIC_CACHE);
      // Use individual adds so a single 404 doesn't abort the whole install.
      await Promise.all(
        PRECACHE_URLS.map((u) =>
          cache.add(new Request(u, { cache: 'reload' })).catch(() => null)
        )
      );
      await self.skipWaiting();
    })()
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      if (KILL_SWITCH) {
        // Self-destruct: remove caches and unregister.
        const keys = await caches.keys();
        await Promise.all(keys.map((k) => caches.delete(k)));
        await self.registration.unregister();
        const clientsList = await self.clients.matchAll({ type: 'window' });
        clientsList.forEach((c) => c.navigate(c.url));
        return;
      }
      // Purge old versioned caches.
      const keys = await caches.keys();
      await Promise.all(
        keys.map((k) => {
          if (k !== STATIC_CACHE && k !== SHELL_CACHE) {
            return caches.delete(k);
          }
          return null;
        })
      );
      await self.clients.claim();
    })()
  );
});

// Allow page code to ask the SW to skip waiting (used for update prompts).
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  if (KILL_SWITCH) return;

  const request = event.request;

  // Rule 1: Only handle GET. Everything else bypasses the SW entirely.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Rule 2: Same-origin only. Let the browser handle cross-origin requests
  // (CDNs, analytics, etc.) normally.
  if (url.origin !== self.location.origin) return;

  // Rule 3: Never touch API / auth / dynamic routes.
  if (isNetworkOnly(url)) return;

  // Navigation requests: network-first with SPA-shell + offline fallback.
  const isNavigation =
    request.mode === 'navigate' ||
    (request.destination === 'document') ||
    (request.headers.get('accept') || '').includes('text/html');

  if (isNavigation) {
    event.respondWith(handleNavigation(request));
    return;
  }

  // Static assets: cache-first with background refresh.
  if (isStaticAsset(url)) {
    event.respondWith(handleStatic(request));
    return;
  }

  // Anything else: default to network, no caching.
});

// Map a navigation URL to a "surface" shell key so each PWA surface
// (POS/admin, portal, customer-display, store, login) is cached independently
// and a user landing on one surface offline doesn't accidentally see another
// surface's HTML shell.
function shellKeyFor(url) {
  const p = url.pathname;
  if (p === '/portal' || p.startsWith('/portal/')) return '/__shell_portal__';
  if (p === '/customer-display' || p.startsWith('/customer-display/')) return '/__shell_customer_display__';
  if (p === '/online_store' || p.startsWith('/online_store/')) return '/__shell_store__';
  return '/__shell_app__';
}

async function handleNavigation(request) {
  const url = new URL(request.url);
  const shellKey = shellKeyFor(url);
  try {
    const networkResponse = await fetch(request);
    if (
      networkResponse &&
      networkResponse.ok &&
      networkResponse.status === 200 &&
      networkResponse.type === 'basic'
    ) {
      const cache = await caches.open(SHELL_CACHE);
      cache.put(shellKey, networkResponse.clone()).catch(() => {});
    }
    return networkResponse;
  } catch (e) {
    const cache = await caches.open(SHELL_CACHE);
    // Try the surface-specific shell first, then fall back to the generic
    // app shell, then to the offline page.
    const shell =
      (await cache.match(shellKey)) ||
      (await cache.match('/__shell_app__'));
    if (shell) return shell;
    const staticCache = await caches.open(STATIC_CACHE);
    const offline = await staticCache.match(OFFLINE_URL);
    if (offline) return offline;
    return new Response(
      '<h1>Offline</h1><p>Application is offline and no cached shell is available yet.</p>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

async function handleStatic(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  if (cached) {
    // Revalidate in background without blocking the response.
    fetch(request)
      .then((resp) => {
        if (resp && resp.ok && resp.status === 200 && resp.type === 'basic') {
          cache.put(request, resp.clone()).catch(() => {});
        }
      })
      .catch(() => {});
    return cached;
  }
  try {
    const resp = await fetch(request);
    if (resp && resp.ok && resp.status === 200 && resp.type === 'basic') {
      cache.put(request, resp.clone()).catch(() => {});
    }
    return resp;
  } catch (e) {
    // No cache, no network — let the request fail naturally.
    return Response.error();
  }
}
