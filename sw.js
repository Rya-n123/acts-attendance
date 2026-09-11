// sw.js — Service Worker para sa ACTS Attendance Scanner
const CACHE_NAME = 'acts-scanner-v2';

// Mga files na i-cache para offline
const urlsToCache = [
    '/acts-attendance/assets/css/style.css',
    '/acts-attendance/assets/img/acts-logo.png'
];

// Install event — cache the core files
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
    self.skipWaiting();
});

// Activate event — clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Fetch event — serve from network first, fallback to cache
// IMPORTANT: Skip POST requests and PHP files (dynamic data)!
self.addEventListener('fetch', event => {
    // Skip POST requests — hindi pwedeng i-cache ang POST!
    if (event.request.method !== 'GET') return;
    
    // Skip PHP files — dynamic data, huwag i-cache
    if (event.request.url.includes('.php')) return;
    
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Cache successful GET responses para sa static files lang
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Kapag walang internet, hanapin sa cache (static files lang)
                return caches.match(event.request);
            })
    );
});