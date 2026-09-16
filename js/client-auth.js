/**
 * KORTZEN - Client Authentication State Manager
 * Maneja el estado de autenticación del cliente en el frontend
 * 
 * El login con Google es SOLO para CLIENTES que quieren reservar citas.
 * Los barberos usan login.php con contraseña.
 */

(function () {
    'use strict';

    const AUTH_CHECK_ENDPOINT = '/api/auth-status.php';
    const CLIENT_LOGIN_URL = '/cliente-login.php';

    /**
     * Verifica el estado de autenticación del cliente
     */
    async function checkAuthState() {
        try {
            const response = await fetch(AUTH_CHECK_ENDPOINT, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Auth check failed');
            }

            const data = await response.json();
            updateUIForAuthState(data);
            return data;

        } catch (error) {
            console.warn('Error checking auth state:', error);
            return { isLoggedIn: false, user: null };
        }
    }

    /**
     * Actualiza la UI según el estado de autenticación
     */
    function updateUIForAuthState(authState) {
        const { isLoggedIn, user } = authState;

        if (isLoggedIn && user) {
            // Usuario logueado - actualizar enlaces en navegación y footer
            addAccountLinkToFooter();
            updateNavigationAuth(user);
        }

        // Disparar evento personalizado
        window.dispatchEvent(new CustomEvent('kortzen:authStateChanged', { detail: authState }));
    }

    /**
     * Agrega enlaces de Mi Cuenta y Cerrar Sesión en menús de navegación
     */
    function updateNavigationAuth(user) {
        // Actualizar CTA principal
        document.querySelectorAll('.header__cta, .mobile-nav__cta').forEach(btn => {
            btn.href = '/reservar.php';
        });

        // Actualizar nav de escritorio si no existe
        const nav = document.querySelector('.nav');
        if (nav && !nav.querySelector('.nav__link--account')) {
            const accLink = document.createElement('a');
            accLink.href = '/cliente-dashboard.php';
            accLink.className = 'nav__link nav__link--account';
            accLink.textContent = 'Mi Cuenta';
            nav.appendChild(accLink);

            const logoutLink = document.createElement('a');
            logoutLink.href = '/logout.php';
            logoutLink.className = 'nav__link nav__link--logout';
            logoutLink.style.color = '#ff6b6b';
            logoutLink.textContent = 'Cerrar Sesión';
            nav.appendChild(logoutLink);
        }

        // Actualizar nav móvil si no existe
        const mobileNavLinks = document.querySelector('.mobile-nav__links');
        if (mobileNavLinks && !mobileNavLinks.querySelector('.mobile-nav__link--logout')) {
            const accLink = document.createElement('a');
            accLink.href = '/cliente-dashboard.php';
            accLink.className = 'mobile-nav__link mobile-nav__link--account';
            accLink.textContent = 'Mi Cuenta';
            mobileNavLinks.appendChild(accLink);

            const logoutLink = document.createElement('a');
            logoutLink.href = '/logout.php';
            logoutLink.className = 'mobile-nav__link mobile-nav__link--logout';
            logoutLink.style.color = '#ff6b6b';
            logoutLink.textContent = 'Cerrar Sesión';
            mobileNavLinks.appendChild(logoutLink);
        }
    }

    /**
     * Agrega link a Mi Cuenta en el footer
     */
    function addAccountLinkToFooter() {
        const footerLinks = document.querySelector('.footer__links');
        if (!footerLinks) return;

        // Verificar si ya existe
        if (footerLinks.querySelector('[href="/cliente-dashboard.php"]')) return;

        const li = document.createElement('li');
        li.innerHTML = '<a href="/cliente-dashboard.php" class="footer__link">Mi Cuenta</a>';
        footerLinks.appendChild(li);
    }

    /**
     * Redirige al login de cliente para reservar
     * Guarda la URL de retorno para después del login
     */
    function requireLoginForBooking(returnUrl = null) {
        const url = returnUrl || window.location.href;
        sessionStorage.setItem('kortzen_booking_return', url);
        window.location.href = CLIENT_LOGIN_URL;
    }

    /**
     * Cerrar sesión directamente
     */
    function logoutClient() {
        window.location.href = '/logout.php';
    }

    // Exponer globalmente
    window.KortzenAuth = {
        checkState: checkAuthState,
        requireLogin: requireLoginForBooking,
        logout: logoutClient,
        loginUrl: CLIENT_LOGIN_URL,
        isLoggedIn: () => {
            return fetch(AUTH_CHECK_ENDPOINT, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => d.isLoggedIn)
                .catch(() => false);
        }
    };

    // Auto-inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', () => {
        // Solo en páginas públicas (no dashboard)
        if (!window.location.pathname.includes('dashboard') &&
            !window.location.pathname.includes('login')) {
            checkAuthState();
        }
    });

})();
