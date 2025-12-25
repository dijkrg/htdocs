self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open("abcb-monteur-cache-v1").then((cache) => {
            return cache.addAll([
                "/monteur/monteur_dashboard.php",
                "/monteur/template/monteur_base.css",
                "/monteur/template/monteur_mobile.css",
                "/template/style.css",
                "/icons/icon-192.png",
                "/icons/icon-512.png"
            ]);
        })
    );
});

self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);

    // Alleen /monteur/ wordt door deze PWA beheerd
    if (!url.pathname.startsWith("/monteur/")) return;

    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
