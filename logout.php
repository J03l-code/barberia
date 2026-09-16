<?php
require_once 'config.php';

$isStaff = isset($_SESSION['user_id']);
$isCliente = isset($_SESSION['cliente_id']);

if ($isStaff) {
    registrarLog('LOGOUT', 'usuarios', $_SESSION['user_id'], 'Cierre de sesión de usuario');
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 86400, '/');
}

// Destruir cookies persistentes (Token de cliente PWA, usuario, etc.)
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

// Destruir la sesión
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Si viene parámetro redirect explícito
if (!empty($_GET['redirect'])) {
    header('Location: ' . $_GET['redirect']);
    exit;
}

// Si era staff (barbero/admin) y NO cliente, redirigir a login.php
if ($isStaff && !$isCliente) {
    header('Location: /login.php');
} else {
    // Si era cliente o venía desde la PWA / web, redirigir a la página web principal
    header('Location: /');
}
exit;
