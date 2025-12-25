const CACHE_NAME = "abcb-monteur-static-v1";

const STATIC_ASSETS = [
    "/template/style.css",
    "/monteur/template/monteur_base.css",
    "/monteur/template/monteur_mobile.css",
    "/icons/icon-48.png",
    "/icons/icon-72.png",
    "/icons/icon-96.png",
    "/icons/icon-144.png",
    "/icons/icon-192.png",
    "/icons/icon-256.png",
    "/icons/icon-384.png",
    "/icons/icon-512.png"
];

// Install: alleen statische assets cachen
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: oude caches opruimen
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch: GEEN navigatie-intercept meer, alleen statische assets
self.addEventListener("fetch", (event) => {
    // Alleen GET-requests
    if (event.request.method !== "GET") return;

    const url = new URL(event.request.url);

    // Alleen zelfde origin
    if (url.origin !== location.origin) return;

    // Navigaties (hele pagina) NIET afhandelen → browser doet het zelf
    if (event.request.mode === "navigate") return;

    // Alleen onze bekende static assets cachen
    if (!STATIC_ASSETS.includes(url.pathname)) return;

    event.respondWith(
        caches.match(event.request).then((cached) => {
            return (
                cached ||
                fetch(event.request).then((response) => {
                    // Response in cache stoppen voor volgende keer
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, copy);
                    });
                    return response;
                })
            );
        })
    );
});
