self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open("abcb-monteur-cache-v2").then((cache) => {
            return cache.addAll([
                "/monteur/monteur_dashboard.php",
                "/template/style.css",
                "/template/ABCBFAV.png"
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
