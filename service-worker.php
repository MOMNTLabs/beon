<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function pwaAssetVersion(string $relativePath): string
{
    $absolutePath = __DIR__ . '/' . ltrim($relativePath, '/\\');
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
}

function pwaVersionedPath(string $relativePath): string
{
    return $relativePath . '?v=' . rawurlencode(pwaAssetVersion($relativePath));
}

$trackedFiles = [
    'manifest.webmanifest',
    'offline.html',
    'assets/styles.css',
    'assets/theme-bexon.css',
    'assets/app.js',
    'assets/loading.js',
    'assets/compliance.js',
    'assets/pwa.js',
    'assets/Bexon---Perfil.png',
    'assets/pwa-icon-180.png',
    'assets/pwa-icon-192.png',
    'assets/pwa-icon-512.png',
];

$versionFingerprint = [];
foreach ($trackedFiles as $trackedFile) {
    $versionFingerprint[] = $trackedFile . ':' . pwaAssetVersion($trackedFile);
}

$cacheVersion = substr(hash('sha256', implode('|', $versionFingerprint)), 0, 16);
$precachePaths = [
    pwaVersionedPath('manifest.webmanifest'),
    'offline.html',
    pwaVersionedPath('assets/styles.css'),
    pwaVersionedPath('assets/theme-bexon.css'),
    pwaVersionedPath('assets/app.js'),
    pwaVersionedPath('assets/loading.js'),
    pwaVersionedPath('assets/compliance.js'),
    pwaVersionedPath('assets/pwa.js'),
    pwaVersionedPath('assets/Bexon---Perfil.png'),
    pwaVersionedPath('assets/pwa-icon-180.png'),
    pwaVersionedPath('assets/pwa-icon-192.png'),
    pwaVersionedPath('assets/pwa-icon-512.png'),
];
?>
const STATIC_CACHE = "bexon-static-<?= $cacheVersion ?>";
const PRECACHE_URLS = <?= json_encode($precachePaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const OFFLINE_URL = new URL("offline.html", self.location.href).toString();
const IMAGE_FALLBACK_URL = new URL("assets/pwa-icon-192.png?v=<?= rawurlencode(pwaAssetVersion('assets/pwa-icon-192.png')) ?>", self.location.href).toString();

const isNavigationRequest = (request) =>
  request.mode === "navigate" ||
  request.destination === "document" ||
  (request.headers.get("accept") || "").includes("text/html");

const shouldHandleStaticRequest = (request, url) => {
  if (url.origin !== self.location.origin) return false;
  if (url.pathname.endsWith("/service-worker.php")) return false;

  if (request.destination === "script") return true;
  if (request.destination === "style") return true;
  if (request.destination === "image") return true;
  if (request.destination === "font") return true;
  if (url.pathname.includes("/assets/")) return true;
  if (url.pathname.endsWith("/manifest.webmanifest")) return true;
  if (url.pathname.endsWith("/offline.html")) return true;

  return false;
};

self.addEventListener("install", (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) =>
      cache.addAll(PRECACHE_URLS.map((path) => new URL(path, self.location.href).toString()))
    )
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const cacheKeys = await caches.keys();
      await Promise.all(
        cacheKeys
          .filter((cacheKey) => cacheKey.startsWith("bexon-static-") && cacheKey !== STATIC_CACHE)
          .map((cacheKey) => caches.delete(cacheKey))
      );
      await self.clients.claim();
    })()
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const requestUrl = new URL(request.url);

  if (isNavigationRequest(request)) {
    event.respondWith(
      (async () => {
        try {
          return await fetch(request);
        } catch (_error) {
          const offlineResponse = await caches.match(OFFLINE_URL);
          return offlineResponse || Response.error();
        }
      })()
    );
    return;
  }

  if (!shouldHandleStaticRequest(request, requestUrl)) {
    return;
  }

  event.respondWith(
    (async () => {
      const cache = await caches.open(STATIC_CACHE);
      const cachedResponse = await cache.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }

      try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
          await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
      } catch (_error) {
        if (request.destination === "image") {
          const fallbackImage = await caches.match(IMAGE_FALLBACK_URL);
          if (fallbackImage) {
            return fallbackImage;
          }
        }

        throw _error;
      }
    })()
  );
});
