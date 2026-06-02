// =====================
// SERVICE WORKER - อาณาจักรวาจาสิทธิ์
// Provides offline support and caching
// =====================

const CACHE_NAME = 'kow-cache-v2'
const STATIC_CACHE = 'kow-static-v2'
const DYNAMIC_CACHE = 'kow-dynamic-v2'
const TTS_CACHE = 'kow-tts-v2'

// Base path derived from service worker scope, e.g. "/readthai/kingdom-of-words/game/"
const SCOPE_URL = (self && self.registration && self.registration.scope) ? new URL(self.registration.scope) : new URL(self.location.href)
const BASE_PATH = SCOPE_URL.pathname.endsWith('/') ? SCOPE_URL.pathname : `${SCOPE_URL.pathname}/`

// Assets to cache on install
const STATIC_ASSETS = [
  BASE_PATH,
  `${BASE_PATH}index.html`,
  `${BASE_PATH}manifest.json`,
]

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...')
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Caching static assets')
        return cache.addAll(STATIC_ASSETS)
      })
      .then(() => {
        console.log('[SW] Static assets cached')
        return self.skipWaiting()
      })
      .catch((error) => {
        console.error('[SW] Error caching static assets:', error)
      })
  )
})

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...')
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => {
              // Delete old caches
              return name.startsWith('kow-') && 
                     name !== STATIC_CACHE && 
                     name !== DYNAMIC_CACHE &&
                     name !== TTS_CACHE
            })
            .map((name) => {
              console.log('[SW] Deleting old cache:', name)
              return caches.delete(name)
            })
        )
      })
      .then(() => {
        console.log('[SW] Service worker activated')
        return self.clients.claim()
      })
  )
})

// Fetch event - serve from cache or network
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)
  
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return
  }
  
  // Skip external requests (except TTS audio)
  if (!url.origin.includes('localhost') && 
      !url.origin.includes('readthai') &&
      !url.hostname.includes('botnoi')) {
    return
  }

  // Handle TTS audio requests - cache with longer TTL
  if (url.hostname.includes('botnoi') || url.pathname.includes('/api/tts/')) {
    event.respondWith(
      caches.open(TTS_CACHE)
        .then((cache) => {
          return cache.match(event.request)
            .then((cachedResponse) => {
              if (cachedResponse) {
                console.log('[SW] Serving TTS from cache:', url.pathname)
                return cachedResponse
              }
              
              return fetch(event.request)
                .then((networkResponse) => {
                  if (networkResponse.ok) {
                    // Clone response before caching
                    cache.put(event.request, networkResponse.clone())
                    console.log('[SW] Cached TTS audio:', url.pathname)
                  }
                  return networkResponse
                })
            })
        })
        .catch(() => {
          // Return offline fallback for TTS
          return new Response('TTS unavailable offline', { status: 503 })
        })
    )
    return
  }

  // Handle API requests - network first, then cache
  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then((networkResponse) => {
          // Cache successful API responses
          if (networkResponse.ok) {
            const responseClone = networkResponse.clone()
            caches.open(DYNAMIC_CACHE)
              .then((cache) => {
                cache.put(event.request, responseClone)
              })
          }
          return networkResponse
        })
        .catch(() => {
          // Try to serve from cache
          return caches.match(event.request)
            .then((cachedResponse) => {
              if (cachedResponse) {
                console.log('[SW] Serving API from cache:', url.pathname)
                return cachedResponse
              }
              
              // Return offline JSON response
              return new Response(
                JSON.stringify({ 
                  error: 'Offline', 
                  message: 'ไม่สามารถเชื่อมต่อได้ กรุณาตรวจสอบอินเทอร์เน็ต' 
                }),
                { 
                  status: 503, 
                  headers: { 'Content-Type': 'application/json' }
                }
              )
            })
        })
    )
    return
  }

  // Handle static assets - cache first, then network
  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          // Return cached response and update cache in background
          event.waitUntil(
            fetch(event.request)
              .then((networkResponse) => {
                if (networkResponse.ok) {
                  caches.open(STATIC_CACHE)
                    .then((cache) => cache.put(event.request, networkResponse))
                }
              })
              .catch(() => {})
          )
          return cachedResponse
        }

        // Not in cache, fetch from network
        return fetch(event.request)
          .then((networkResponse) => {
            // Cache new static assets
            if (networkResponse.ok && shouldCache(url)) {
              const responseClone = networkResponse.clone()
              caches.open(DYNAMIC_CACHE)
                .then((cache) => cache.put(event.request, responseClone))
            }
            return networkResponse
          })
          .catch(() => {
            // Return offline page for navigation requests
            if (event.request.mode === 'navigate') {
              return caches.match(`${BASE_PATH}index.html`)
            }
            return new Response('Offline', { status: 503 })
          })
      })
  )
})

// Helper: Determine if a URL should be cached
function shouldCache(url) {
  const cacheableExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.svg', '.woff', '.woff2']
  return cacheableExtensions.some(ext => url.pathname.endsWith(ext))
}

// Handle messages from the app
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith('kow-'))
            .map((name) => caches.delete(name))
        )
      })
      .then(() => {
        event.ports[0].postMessage({ success: true })
      })
  }
  
  if (event.data && event.data.type === 'CACHE_TTS') {
    // Pre-cache TTS audio
    const { text, audioUrl } = event.data
    if (audioUrl) {
      caches.open(TTS_CACHE)
        .then((cache) => {
          fetch(audioUrl)
            .then((response) => {
              if (response.ok) {
                cache.put(audioUrl, response)
                console.log('[SW] Pre-cached TTS:', text)
              }
            })
        })
    }
  }
})

// Periodic sync for background updates (if supported)
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'update-cache') {
    event.waitUntil(
      caches.open(STATIC_CACHE)
        .then((cache) => {
          return cache.addAll(STATIC_ASSETS)
        })
    )
  }
})

console.log('[SW] Service worker loaded')

