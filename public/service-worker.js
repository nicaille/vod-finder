const CACHE_NAME = "vodfinder-v1";

// URLs à pré-cacher - adapte si tu as d'autres routes statiques
const PRECACHE_URLS = [
  "/",            // page de recherche
];

// Install - pre-cache
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_URLS);
    })
  );
});

// Activate - nettoyage des anciens caches
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
});

// Strategy:
// - HTML: network-first avec fallback cache
// - autres (images, JS, CSS): cache-first avec fallback réseau
self.addEventListener("fetch", (event) => {
  const request = event.request;

  // On ignore les requêtes non GET (POST, etc.)
  if (request.method !== "GET") {
    return;
  }

  const acceptHeader = request.headers.get("Accept") || "";

  // Pour les pages HTML: network first
  if (acceptHeader.includes("text/html")) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match("/")))
    );
    return;
  }

  // Pour les assets (images, CSS, JS...): cache first
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => {
          // pas de fallback particulier pour les assets
          return new Response("", { status: 504, statusText: "Offline" });
        });
    })
  );
});
