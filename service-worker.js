/* ==========================================================================
   Service worker de M-Core.
   Filosofía: lo mínimo necesario para que Chrome considere la app
   "instalable" (requisito para TWA) y para no dejar una pantalla en
   blanco sin conexión — nada de cachear agresivamente.

   - Páginas PHP (dashboard.php, tareas.php, etc.) y todo /api/: SIEMPRE
     van a la red. Son dinámicas y dependen de la sesión de cada quien;
     cachearlas podría mostrar datos viejos, o peor, de otra cuenta en un
     dispositivo compartido.
   - /assets/ (css, js, íconos): cache-first con actualización en segundo
     plano ("stale-while-revalidate") — son estáticos versionados por
     despliegue, no hay riesgo en servirlos desde caché mientras se
     actualizan solos para la próxima vez.
   - Si la red falla al navegar a una página, se muestra offline.html en
     vez de un error del navegador.
   ========================================================================== */

const CACHE_ESTATICOS = 'mosta-estaticos-v1';
const CACHE_OFFLINE = 'mosta-offline-v1';

const ESTATICOS_PRECARGA = [
  'assets/css/theme.css',
  'assets/js/app.js',
  'manifest.json',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    Promise.all([
      caches.open(CACHE_ESTATICOS).then((cache) => cache.addAll(ESTATICOS_PRECARGA)),
      caches.open(CACHE_OFFLINE).then((cache) => cache.add('offline.html')),
    ])
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((nombres) =>
      Promise.all(
        nombres
          .filter((n) => n !== CACHE_ESTATICOS && n !== CACHE_OFFLINE)
          .map((n) => caches.delete(n))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Nunca interceptar peticiones a otro origen (CDN de Bootstrap, Google
  // Fonts, etc.) — se dejan pasar tal cual, el navegador ya las cachea
  // razonablemente solo.
  if (url.origin !== self.location.origin) {
    return;
  }

  // Nunca cachear /api/ — cada respuesta es específica de la sesión y del
  // momento (saldos, listas, permisos...). Siempre a la red.
  if (url.pathname.includes('/api/')) {
    return;
  }

  // Navegación (abrir una página completa): red primero, con respaldo a
  // caché/offline.html si no hay conexión. Así nunca se sirve una versión
  // vieja de dashboard.php/tareas.php por accidente.
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() =>
        caches.match('offline.html', { cacheName: CACHE_OFFLINE })
      )
    );
    return;
  }

  // Estáticos propios (css/js/íconos/fuentes locales): stale-while-revalidate.
  if (url.pathname.includes('/assets/')) {
    event.respondWith(
      caches.open(CACHE_ESTATICOS).then((cache) =>
        cache.match(event.request).then((respuestaCache) => {
          const enRed = fetch(event.request)
            .then((respuestaRed) => {
              cache.put(event.request, respuestaRed.clone());
              return respuestaRed;
            })
            .catch(() => respuestaCache);
          return respuestaCache || enRed;
        })
      )
    );
  }
  // Cualquier otra cosa (ej. exportar-proyecto.php, ticket-*.php): a la
  // red directo, sin interceptar — son descargas/documentos puntuales.
});
