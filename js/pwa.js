/**
 * KORTZEN - PWA Core Manager
 * Handles Service Worker registration, custom install prompts for Android/iOS, and notifications
 */

// Register Service Worker & Auto-Update
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js?v=101')
      .then(registration => {
        registration.update();
        console.log('Service Worker registrado con éxito:', registration.scope);
      })
      .catch(error => {
        console.log('Fallo al registrar el Service Worker:', error);
      });
  });
}

// Global PWA State
let deferredPrompt;

document.addEventListener('DOMContentLoaded', () => {
  // PWA Persistent Login Sync (LocalStorage Backup for iOS/Android Standalone WebViews)
  const pathname = window.location.pathname;
  
  if (pathname.includes('cliente-login.php') || pathname.includes('pwa-entry.php')) {
    const savedToken = localStorage.getItem('kortzen_pwa_token');
    const savedClientId = localStorage.getItem('kortzen_pwa_client_id');
    if (savedToken) {
      fetch('/api/auto_login_pwa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'token=' + encodeURIComponent(savedToken) + '&client_id=' + encodeURIComponent(savedClientId || '')
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        }
      })
      .catch(e => {});
    }
  }

  // Inject CSS for PWA banners
  const style = document.createElement('style');
  style.textContent = `
    .pwa-banner {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 450px;
      background: #111111;
      border: 1px solid #333333;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      z-index: 99999;
      display: flex;
      flex-direction: column;
      gap: 12px;
      color: #ffffff;
      font-family: sans-serif;
      animation: slideUp 0.4s ease-out;
    }
    .pwa-banner__header {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .pwa-banner__icon {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: #222;
      object-fit: cover;
    }
    .pwa-banner__info {
      flex: 1;
    }
    .pwa-banner__title {
      font-size: 15px;
      font-weight: 600;
      margin: 0 0 2px 0;
    }
    .pwa-banner__desc {
      font-size: 12px;
      color: #aaaaaa;
      margin: 0;
    }
    .pwa-banner__actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 4px;
    }
    .pwa-banner__btn {
      padding: 8px 16px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      transition: all 0.2s ease;
    }
    .pwa-banner__btn--install {
      background: #ffffff;
      color: #111111;
      border: 1px solid #ffffff;
    }
    .pwa-banner__btn--install:hover {
      background: #dddddd;
    }
    .pwa-banner__btn--dismiss {
      background: transparent;
      color: #aaaaaa;
      border: 1px solid transparent;
    }
    .pwa-banner__btn--dismiss:hover {
      color: #ffffff;
    }
    @keyframes slideUp {
      from { transform: translate(-50%, 100px); opacity: 0; }
      to { transform: translate(-50%, 0); opacity: 1; }
    }

    /* Pull-to-Refresh Native Indicator */
    .pwa-ptr {
      position: fixed;
      top: 0;
      left: 50%;
      transform: translate3d(-50%, -100%, 0);
      z-index: 999999;
      pointer-events: none;
      user-select: none;
      will-change: transform;
      padding-top: calc(env(safe-area-inset-top, 12px) + 8px);
    }
    .pwa-ptr__pill {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      background: #111111;
      color: #FFFFFF;
      padding: 7px 16px 7px 9px;
      border-radius: 40px;
      border: 1px solid rgba(192, 160, 98, 0.35);
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.3), 0 2px 6px rgba(0, 0, 0, 0.2);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .pwa-ptr.pwa-ptr--ready .pwa-ptr__pill {
      border-color: #C0A062;
      background: #181818;
      box-shadow: 0 12px 30px rgba(192, 160, 98, 0.25), 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    .pwa-ptr__icon-wrap {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #C0A062;
      position: relative;
      flex-shrink: 0;
    }
    .pwa-ptr.pwa-ptr--ready .pwa-ptr__icon-wrap {
      background: rgba(192, 160, 98, 0.25);
      color: #DFC085;
    }
    .pwa-ptr__arrow {
      display: block;
      transition: transform 0.08s linear;
    }
    .pwa-ptr__spinner {
      display: none;
    }
    .pwa-ptr--loading .pwa-ptr__arrow {
      display: none;
    }
    .pwa-ptr--loading .pwa-ptr__spinner {
      display: block;
      animation: pwa-ptr-spin 0.75s linear infinite;
      color: #C0A062;
    }
    @keyframes pwa-ptr-spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  `;
  document.head.appendChild(style);

  // Initialize Native Pull-to-Refresh
  initPullToRefresh();

  // Check device & standalone mode
  const userAgent = window.navigator.userAgent.toLowerCase();
  const isIos = /iphone|ipad|ipod/.test(userAgent);
  const isInStandaloneMode = window.matchMedia('(display-mode: standalone)').matches || (('standalone' in window.navigator) && (window.navigator.standalone));

  if (isInStandaloneMode) {
    document.body.classList.add('is-pwa-standalone');
  } else {
    document.body.classList.add('is-desktop-web');
  }

  // Show iOS-specific install prompt if inside Safari but not added to Home Screen
  if (isIos && !isInStandaloneMode) {
    // Only show if they haven't dismissed it in this session
    if (!sessionStorage.getItem('pwa-ios-dismissed')) {
      showIosInstallPrompt();
    }
  }

  // Handle Chrome/Android install prompt
  window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent default browser banner
    e.preventDefault();
    deferredPrompt = e;
    
    // Only show if not already in standalone mode and not dismissed
    if (!isInStandaloneMode && !sessionStorage.getItem('pwa-android-dismissed')) {
      showAndroidInstallPrompt();
    }
  });

  // Automatically request notifications if user is on dashboard and hasn't granted yet
  if (window.location.pathname.includes('cliente-dashboard.php')) {
    setTimeout(() => {
      checkAndPromptNotifications();
    }, 2000);
  }
});

// Show Android install banner
function showAndroidInstallPrompt() {
  const banner = document.createElement('div');
  banner.className = 'pwa-banner';
  banner.innerHTML = `
    <div class="pwa-banner__header">
      <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="KORTZEN">
      <div class="pwa-banner__info">
        <h4 class="pwa-banner__title">KORTZEN Barbería</h4>
        <p class="pwa-banner__desc">Instala nuestra aplicación para reservar y ver tu historial al instante.</p>
      </div>
    </div>
    <div class="pwa-banner__actions">
      <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="pwa-btn-dismiss">Más tarde</button>
      <button class="pwa-banner__btn pwa-banner__btn--install" id="pwa-btn-install">Instalar App</button>
    </div>
  `;

  document.body.appendChild(banner);

  document.getElementById('pwa-btn-install').addEventListener('click', async () => {
    banner.remove();
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      console.log(\`User response to install prompt: \${outcome}\`);
      deferredPrompt = null;
    }
  });

  document.getElementById('pwa-btn-dismiss').addEventListener('click', () => {
    banner.remove();
    sessionStorage.setItem('pwa-android-dismissed', 'true');
  });
}

// Show iOS manual install guide
function showIosInstallPrompt() {
  const banner = document.createElement('div');
  banner.className = 'pwa-banner';
  banner.innerHTML = `
    <div class="pwa-banner__header">
      <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="KORTZEN">
      <div class="pwa-banner__info">
        <h4 class="pwa-banner__title">Instalar en tu iPhone</h4>
        <p class="pwa-banner__desc">Pulsa el botón de compartir de Safari <strong style="color:#fff;">(Compartir)</strong> y luego selecciona <strong style="color:#fff;">"Añadir a pantalla de inicio"</strong>.</p>
      </div>
    </div>
    <div class="pwa-banner__actions">
      <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="pwa-ios-dismiss" style="width:100%;">Entendido</button>
    </div>
  `;

  document.body.appendChild(banner);

  document.getElementById('pwa-ios-dismiss').addEventListener('click', () => {
    banner.remove();
    sessionStorage.setItem('pwa-ios-dismissed', 'true');
  });
}

// Check and request notifications permission
function checkAndPromptNotifications() {
  if (!('Notification' in window)) return;

  if (Notification.permission === 'default') {
    const banner = document.createElement('div');
    banner.className = 'pwa-banner';
    banner.style.bottom = '80px'; // Sit slightly above the installation banner if both are present
    banner.innerHTML = `
      <div class="pwa-banner__header">
        <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="Notificaciones">
        <div class="pwa-banner__info">
          <h4 class="pwa-banner__title">Activar Recordatorios</h4>
          <p class="pwa-banner__desc">Activa las notificaciones para no perder tus citas y recibir actualizaciones en tiempo real.</p>
        </div>
      </div>
      <div class="pwa-banner__actions">
        <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="notif-btn-dismiss">Omitir</button>
        <button class="pwa-banner__btn pwa-banner__btn--install" id="notif-btn-allow">Permitir</button>
      </div>
    `;

    document.body.appendChild(banner);

    document.getElementById('notif-btn-allow').addEventListener('click', () => {
      banner.remove();
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          try {
            new Notification("KORTZEN Barbería", {
              body: "¡Excelente! Te notificaremos sobre tus citas confirmadas y reagendaciones.",
              icon: "/assets/icons/favicon.png"
            });
          } catch (e) {
            console.log("Desktop notifications not fully supported, but permission granted.");
          }
        }
      });
    });

    document.getElementById('notif-btn-dismiss').addEventListener('click', () => {
      banner.remove();
    });
  }
}

// Native Pull-To-Refresh Implementation for PWA (Deslizar hacia abajo para actualizar)
function initPullToRefresh() {
  if (document.getElementById('pwa-ptr')) return;

  const ptr = document.createElement('div');
  ptr.id = 'pwa-ptr';
  ptr.className = 'pwa-ptr';
  ptr.innerHTML = `
    <div class="pwa-ptr__pill">
      <div class="pwa-ptr__icon-wrap">
        <svg class="pwa-ptr__arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <polyline points="19 12 12 19 5 12"></polyline>
        </svg>
        <svg class="pwa-ptr__spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
        </svg>
      </div>
      <span class="pwa-ptr__text">Desliza para actualizar</span>
    </div>
  `;
  document.body.appendChild(ptr);

  const ptrText = ptr.querySelector('.pwa-ptr__text');
  const ptrArrow = ptr.querySelector('.pwa-ptr__arrow');

  let startY = 0;
  let startX = 0;
  let currentY = 0;
  let isTracking = false;
  let canPull = false;
  let isRefreshing = false;
  let pullDistance = 0;
  let hasTriggeredHaptic = false;

  const THRESHOLD = 80; // Distancia para activar la recarga
  const MAX_PULL = 135;  // Límite de desplazamiento visual

  function isScrolledToTop(target) {
    let el = target;
    while (el && el !== document.body && el !== document.documentElement) {
      if (el.scrollHeight > el.clientHeight) {
        const overflowY = window.getComputedStyle(el).overflowY;
        if ((overflowY === 'auto' || overflowY === 'scroll') && el.scrollTop > 0) {
          return false;
        }
      }
      el = el.parentElement;
    }
    const docTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    return docTop <= 2;
  }

  window.addEventListener('touchstart', (e) => {
    if (isRefreshing) return;
    if (e.touches.length !== 1) return;

    if (isScrolledToTop(e.target)) {
      startY = e.touches[0].pageY;
      startX = e.touches[0].pageX;
      isTracking = true;
      canPull = true;
      pullDistance = 0;
      hasTriggeredHaptic = false;
      ptr.style.transition = 'none';
    } else {
      isTracking = false;
      canPull = false;
    }
  }, { passive: true });

  window.addEventListener('touchmove', (e) => {
    if (!isTracking || !canPull || isRefreshing) return;

    currentY = e.touches[0].pageY;
    const currentX = e.touches[0].pageX;
    const diffY = currentY - startY;
    const diffX = currentX - startX;

    // Si el usuario desliza horizontalmente, cancelar gesto de recarga
    if (Math.abs(diffX) > Math.abs(diffY)) {
      canPull = false;
      resetPtr();
      return;
    }

    // Solo actuar si el usuario arrastra hacia abajo y sigue arriba
    if (diffY > 0 && isScrolledToTop(e.target)) {
      // Resistencia elástica progresiva tipo iOS / Android
      pullDistance = Math.min(MAX_PULL, Math.pow(diffY, 0.83) * 1.5);

      if (pullDistance > 10) {
        if (e.cancelable) e.preventDefault();

        const translateY = Math.min(pullDistance, MAX_PULL);
        ptr.style.transform = `translate3d(-50%, ${translateY}px, 0)`;

        // Rotar flecha según progreso
        const progress = Math.min(1, pullDistance / THRESHOLD);
        const deg = progress * 180;
        ptrArrow.style.transform = `rotate(${deg}deg)`;

        if (pullDistance >= THRESHOLD) {
          ptr.classList.add('pwa-ptr--ready');
          ptrText.textContent = 'Suelta para actualizar';
          if (!hasTriggeredHaptic) {
            hasTriggeredHaptic = true;
            if ('vibrate' in navigator) {
              try { navigator.vibrate(15); } catch (err) {}
            }
          }
        } else {
          ptr.classList.remove('pwa-ptr--ready');
          ptrText.textContent = 'Desliza para actualizar';
          hasTriggeredHaptic = false;
        }
      }
    } else {
      resetPtr();
    }
  }, { passive: false });

  window.addEventListener('touchend', () => {
    if (!isTracking || isRefreshing) return;
    isTracking = false;
    canPull = false;

    if (pullDistance >= THRESHOLD) {
      isRefreshing = true;
      ptr.style.transition = 'transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
      ptr.style.transform = 'translate3d(-50%, 65px, 0)';
      ptr.classList.remove('pwa-ptr--ready');
      ptr.classList.add('pwa-ptr--loading');
      ptrText.textContent = 'Actualizando...';

      if ('vibrate' in navigator) {
        try { navigator.vibrate([20, 30, 20]); } catch (err) {}
      }

      // Limpiar SW y recargar la página fresca
      if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.getRegistrations().then(regs => {
          regs.forEach(r => r.update());
        }).catch(() => {});
      }

      setTimeout(() => {
        window.location.reload(true);
      }, 450);
    } else {
      resetPtr();
    }
  }, { passive: true });

  window.addEventListener('touchcancel', () => {
    isTracking = false;
    canPull = false;
    if (!isRefreshing) resetPtr();
  }, { passive: true });

  function resetPtr() {
    ptr.style.transition = 'transform 0.3s cubic-bezier(0.25, 1, 0.5, 1)';
    ptr.style.transform = 'translate3d(-50%, -100%, 0)';
    ptr.classList.remove('pwa-ptr--ready', 'pwa-ptr--loading');
    ptrArrow.style.transform = 'rotate(0deg)';
    ptrText.textContent = 'Desliza para actualizar';
    pullDistance = 0;
    hasTriggeredHaptic = false;
  }
}

