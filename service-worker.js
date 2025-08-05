self.addEventListener("install", e => {
  e.waitUntil(
    caches.open("p2p-admin-cache").then(cache => {
      return cache.addAll(["./admin.html", "./manifest.json"]);
    })
  );
});

self.addEventListener("fetch", e => {
  e.respondWith(
    caches.match(e.request).then(response => response || fetch(e.request))
  );
});
