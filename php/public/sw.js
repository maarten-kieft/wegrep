// Offline support: the app itself opens without network (pages and assets are cached).
// API calls always go to the network; game data lives in the browser's localStorage.
const CACHE = 'wegrep-v1'
const scope = new URL(self.registration.scope)

self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()))
})

self.addEventListener('fetch', (e) => {
  const req = e.request
  const url = new URL(req.url)
  if (req.method !== 'GET' || url.origin !== scope.origin || url.pathname.startsWith(scope.pathname + 'api')) return

  if (req.mode === 'navigate') {
    // Network first so updates arrive, cached app shell when offline.
    e.respondWith(fetch(req).then((res) => {
      const copy = res.clone()
      caches.open(CACHE).then((c) => c.put(scope.pathname, copy))
      return res
    }).catch(() => caches.match(scope.pathname)))
    return
  }

  // Assets have hashed names, so cache first is safe.
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
    if (res.ok) {
      const copy = res.clone()
      caches.open(CACHE).then((c) => c.put(req, copy))
    }
    return res
  })))
})
