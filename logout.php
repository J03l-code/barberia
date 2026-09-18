<?php
/**
 * KORTZEN - Cierre de Sesión Integral (Web y PWA)
 * Elimina sesiones activas, cookies persistentes (Staff y Cliente)
 * y limpia el almacenamiento local (localStorage / sessionStorage) del navegador.
 */

require_once 'config.php';

$isStaff = isset($_SESSION['user_id']) || !empty($_COOKIE['kortzen_pwa_user_id']);
$isCliente = isset($_SESSION['cliente_id']) || isset($_SESSION['cliente_logged_in']) || !empty($_COOKIE['kortzen_pwa_token']) || !empty($_COOKIE['kortzen_pwa_client_id']);

if (isset($_SESSION['user_id'])) {
    registrarLog('LOGOUT', 'usuarios', $_SESSION['user_id'], 'Cierre de sesión de usuario');
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 31536000, '/');
}

// Destruir cookies persistentes (Token de cliente PWA, usuario, etc.)
$cookiesToDelete = [
    'kortzen_pwa_token',
    'kortzen_pwa_client_id',
    'kortzen_pwa_user_id',
    'kortzen_client_id',
    'kortzen_auth',
    'kortzen_pwa_admin_active_tab',
    session_name()
];

$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$host = $_SERVER['HTTP_HOST'] ?? '';

foreach ($cookiesToDelete as $cName) {
    // 1. Eliminación básica
    setcookie($cName, '', time() - 31536000, '/');
    
    // 2. Opciones SameSite Lax
    setcookie($cName, '', [
        'expires' => time() - 31536000,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // 3. Opciones SameSite None
    if ($isHttps) {
        setcookie($cName, '', [
            'expires' => time() - 31536000,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }
    
    // 4. Opciones no-httponly
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

// Destruir la sesión PHP
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Prevenir almacenamiento en caché del logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Destino de redirección
$targetRedirect = '';
if (!empty($_GET['redirect'])) {
    $targetRedirect = $_GET['redirect'];
} elseif ($isStaff && !$isCliente) {
    $targetRedirect = '/login.php';
} elseif ($isCliente) {
    $targetRedirect = '/cliente-login.php';
} else {
    $targetRedirect = '/login.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando sesión...</title>
    <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($targetRedirect, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #111111;
            color: #FFFFFF;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top-color: #C0A062;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .text {
            font-size: 0.95rem;
            font-weight: 600;
            color: #AAAAAA;
        }
    </style>
</head>
<body>
    <div>
        <div class="spinner"></div>
        <div class="text">Cerrando sesión de forma segura...</div>
    </div>

    <script>
        (function() {
            // 1. Limpieza exhaustiva de almacenamiento local y de sesión
            try {
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('kortzen_pwa_token');
                    localStorage.removeItem('kortzen_pwa_client_id');
                    localStorage.removeItem('kortzen_pwa_user_id');
                    localStorage.removeItem('kortzen_pwa_admin_active_tab');
                    localStorage.clear();
                }
            } catch(e) {}

            try {
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.removeItem('kortzen_pwa_admin_active_tab');
                    sessionStorage.clear();
                }
            } catch(e) {}

            // 2. Limpieza de cookies del lado del cliente
            try {
                var cookies = document.cookie.split(";");
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i];
                    var eqPos = cookie.indexOf("=");
                    var name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                    document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=" + window.location.hostname;
                    document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=." + window.location.hostname;
                }
            } catch(e) {}

            // 3. Redirección inmediata sin dejar rastro en historial
            var target = <?php echo json_encode($targetRedirect); ?>;
            window.location.replace(target);
        })();
    </script>
    <noscript>
        <p>Sesión cerrada. <a href="<?php echo htmlspecialchars($targetRedirect, ENT_QUOTES, 'UTF-8'); ?>" style="color: #C0A062;">Haz clic aquí para continuar</a>.</p>
    </noscript>
</body>
</html>
