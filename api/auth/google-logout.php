<?php
/**
 * Logout de Cliente (Google)
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
setcookie('kortzen_pwa_client_id', '', time() - 3600, '/');
setcookie('kortzen_pwa_user_id', '', time() - 3600, '/');

// Si no hay más datos de sesión de staff, destruir la sesión completa
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

// Redirigir al inicio de la página web
header('Location: /');
exit;
