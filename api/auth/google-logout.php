<?php
/**
 * Logout de Cliente (Google y Directo)
 * Cierra la sesión del cliente y redirige al inicio
 */

session_start();

// Destruir todas las variables de sesión del cliente
unset($_SESSION['cliente_id']);
unset($_SESSION['cliente_nombre']);
unset($_SESSION['cliente_email']);
unset($_SESSION['cliente_foto']);
unset($_SESSION['cliente_google_id']);
unset($_SESSION['cliente_logged_in']);

// Destruir cookies persistentes
$cookiesToDelete = [
    'kortzen_pwa_token',
    'kortzen_pwa_client_id',
    'kortzen_pwa_user_id',
    'kortzen_client_id',
    'kortzen_auth'
];

foreach ($cookiesToDelete as $cName) {
    setcookie($cName, '', time() - 86400, '/');
    setcookie($cName, '', time() - 86400, '/', '', false, true);
    setcookie($cName, '', time() - 86400, '/', '', true, true);
    unset($_COOKIE[$cName]);
}

// Si no hay más datos de sesión de staff, destruir la sesión completa
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 86400, '/');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Redirigir al inicio de la página web
header('Location: /');
exit;
