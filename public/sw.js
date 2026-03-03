// /src/public/sw.js
const CACHE_NAME = 'todo-app-cache-v1';
const APP_SHELL_URLS = [
  '/',                // try to cache root
  '/index.html',
  '/css/style.css',
  '/js/app.js',
  '/manifest.json',
  '/images/icon-192.png',
  '/images/icon-512.png',
  // External URLs (may be opaque / CORS-restricted).
  // We attempt them but tolerate failures.
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://code.jquery.com/jquery-3.7.1.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js'
];

// Offline fallback page (make sure this file exists at /offline.html)
const OFFLINE_PAGE = '/offline.html';

self.addEventListener('install', (event) => {
  console.log('[SW] install');
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    // Use Promise.allSettled so one failing resource won't fail the entire install
    const results = await Promise.allSettled(APP_SHELL_URLS.map(async (url) => {
      try {
        const res = await fetch(url, { mode: 'no-cors' });
        // If response is opaque (status 0), still try to put it into cache.
        await cache.put(url, res.clone());
        return { url, ok: true };
      } catch (err) {
        console.warn('[SW] Failed to cache', url, err);
        return { url, ok: false, err: String(err) };
      }
    }));

    // Ensure offline page is cached (best effort)
    try {
      const offlineResp = await fetch(OFFLINE_PAGE);
      if (offlineResp && offlineResp.ok) {
        await cache.put(OFFLINE_PAGE, offlineResp.clone());
      } else {
        console.warn('[SW] offline page not found or not OK:', offlineResp && offlineResp.status);
      }
    } catch (e) {
      console.warn('[SW] could not fetch offline page', e);
    }

    // Activate immediately after install
    await self.skipWaiting();
    console.log('[SW] install completed. Cache results:', results);
  })());
});

self.addEventListener('activate', (event) => {
  console.log('[SW] activate');
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => {
      if (k !== CACHE_NAME) {
        console.log('[SW] deleting old cache', k);
        return caches.delete(k);
      }
      return Promise.resolve();
    }));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Don't interfere with API calls (network-first)
  if (url.pathname.startsWith('/api/v1/')) {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_PAGE)));
    return;
  }

  // Navigation requests -> try cache first, fallback to network, then offline page
  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        // Try network first for navigation (you can reverse to cache first)
        const networkResp = await fetch(req);
        // Put a copy in cache (optional)
        const cache = await caches.open(CACHE_NAME);
        cache.put(req, networkResp.clone()).catch(() => {});
        return networkResp;
      } catch (err) {
        // If network fails, try cache then offline page
        const cached = await caches.match(req);
        if (cached) return cached;
        const offlineCached = await caches.match(OFFLINE_PAGE);
        if (offlineCached) return offlineCached;
        // last-ditch: create a simple Response
        return new Response('<h1>Offline</h1><p>The app is offline and no cached page is available.</p>', {
          headers: { 'Content-Type': 'text/html' }
        });
      }
    })());
    return;
  }

  // For other requests (static): cache-first then network
  event.respondWith((async () => {
    const cached = await caches.match(req);
    if (cached) return cached;
    try {
      const networkResp = await fetch(req);
      // Optionally put into cache (dynamic caching)
      const cache = await caches.open(CACHE_NAME);
      cache.put(req, networkResp.clone()).catch(() => {});
      return networkResp;
    } catch (err) {
      // If network fails, return offline fallback for images / fonts etc. if desired
      const offlineCached = await caches.match(OFFLINE_PAGE);
      if (offlineCached) return offlineCached;
      return new Response('Offline', { status: 503, statusText: 'Offline' });
    }
  })());
});
