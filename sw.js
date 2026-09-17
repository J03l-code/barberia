const CACHE_NAME = 'kortzen-v101';
const ASSETS_TO_CACHE = [
  '/assets/icons/favicon.png',
  '/manifest.json'
];

// Install Event - Skip waiting immediately
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
      })
  );
});

// Activate Event - Clean all old caches and claim clients immediately
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network First strategy for CSS, JS, and HTML so changes reflect instantly
self.addEventListener('fetch', event => {
  // Exclude page navigations, PHP files, API requests and non-GET requests from SW interception
  if (
    event.request.mode === 'navigate' ||
    event.request.url.includes('.php') ||
    event.request.url.includes('/api/') ||
    event.request.method !== 'GET'
  ) {
    return;
  }

  // Network First for JS, CSS, and dynamic assets
  event.respondWith(
    fetch(event.request)
      .then(networkResponse => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// Push Notification Event
self.addEventListener('push', event => {
  let data = { 
    title: '✂️ KORTZEN Barbería: Recordatorio de Cita', 
    body: 'Tienes un nuevo recordatorio de tu cita. Toca aquí para confirmar asistencia.', 
    icon: '/assets/icons/favicon.png',
    url: '/cliente-dashboard.php'
  };

  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      if (event.data.text()) data.body = event.data.text();
    }
  }

  const promiseFetchAndShow = fetch('/api/check_pending_pwa_notifications.php')
    .then(res => res.json())
    .then(resData => {
      if (resData && resData.pending && resData.notification) {
        const n = resData.notification;
        return self.registration.showNotification(n.title || data.title, {
          body: n.body || data.body,
          icon: n.icon || data.icon,
          badge: '/assets/icons/favicon.png',
          vibrate: [200, 100, 200],
          data: { url: n.url || data.url }
        });
      } else {
        return self.registration.showNotification(data.title, {
          body: data.body,
          icon: data.icon,
          badge: '/assets/icons/favicon.png',
          vibrate: [200, 100, 200],
          data: { url: data.url }
        });
      }
    })
    .catch(() => {
      return self.registration.showNotification(data.title, {
        body: data.body,
        icon: data.icon,
        badge: '/assets/icons/favicon.png',
        vibrate: [200, 100, 200],
        data: { url: data.url }
      });
    });

  event.waitUntil(promiseFetchAndShow);
});

// Notification Click Event
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const urlToOpen = event.notification.data.url || '/cliente-dashboard.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      for (let client of windowClients) {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
