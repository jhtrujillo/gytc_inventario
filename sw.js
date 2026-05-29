// Service Worker Minimalista para G&TC Control de Grúas
// Requerido para habilitar el botón "Instalar App" en navegadores modernos.

const CACHE_NAME = 'gytc-cache-v1';
const urlsToCache = [
  '/',
  'dashboard.php',
  'css/style.css',
  'images/icon-192.png'
];

self.addEventListener('install', event => {
    // Forzar activación inmediata
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

// Estrategia Network-First: Siempre intentar cargar la data viva del servidor PHP
// para evitar mostrar reportes obsoletos si hay internet.
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});
