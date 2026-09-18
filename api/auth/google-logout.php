<?php
/**
 * Logout de Cliente (Google y Directo)
 * Cierra la sesión del cliente y redirige al login/inicio limpiando almacenamiento
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
    'kortzen_auth',
    'kortzen_pwa_admin_active_tab'
];

$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$host = $_SERVER['HTTP_HOST'] ?? '';

foreach ($cookiesToDelete as $cName) {
    setcookie($cName, '', time() - 31536000, '/');
    setcookie($cName, '', [
        'expires' => time() - 31536000,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    setcookie($cName, '', [
        'expires' => time() - 31536000,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    if (!empty($host)) {
        setcookie($cName, '', time() - 31536000, '/', $host);
        setcookie($cName, '', time() - 31536000, '/', '.' . $host);
    }
    unset($_COOKIE[$cName]);
}

// Si no hay más datos de sesión de staff, destruir la sesión completa
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 31536000, '/');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

$targetRedirect = !empty($_GET['redirect']) ? $_GET['redirect'] : '/cliente-login.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cerrando sesión...</title>
    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($targetRedirect, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body style="background: #111; color: #fff; font-family: sans-serif; text-align: center; padding-top: 50px;">
    <p>Cerrando sesión...</p>
    <script>
        try {
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('kortzen_pwa_token');
                localStorage.removeItem('kortzen_pwa_client_id');
                localStorage.removeItem('kortzen_pwa_user_id');
            }
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.clear();
            }
        } catch(e) {}
        window.location.replace(<?php echo json_encode($targetRedirect); ?>);
    </script>
</body>
</html>
