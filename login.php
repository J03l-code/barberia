<?php
/**
 * Login para STAFF/BARBEROS
 * Solo acceso con email y contraseña
 */

require_once 'config.php';
require_once 'includes/recaptcha_helper.php';

// Si ya está logueado como staff, redirigir al dashboard adecuado
if (isLoggedIn()) {
    $u = getCurrentUser();
    $target = $_GET['redirect'] ?? '';
    if (!empty($target)) {
        header('Location: ' . $target);
    } elseif ($u['rol'] === 'barbero') {
        header('Location: barber-dashboard.php');
    } elseif (isMobileDevice() || !empty($_GET['pwa']) || !empty($_COOKIE['kortzen_is_pwa'])) {
        header('Location: pwa-admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceRateLimit('login_staff', 10, 300, 'Demasiados intentos de inicio de sesión. Por favor espera 5 minutos.');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $sql = "SELECT * FROM usuarios WHERE email = ?";
            $result = query($sql, [$email]);

            if (count($result) > 0) {
                $user = $result[0];

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nombre'] = $user['nombre'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_rol'] = $user['rol'];

                    // Guardar cookie persistente PWA (365 días)
                    setcookie('kortzen_pwa_user_id', (string)$user['id'], [
                        'expires' => time() + 31536000,
                        'path' => '/',
                        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);

                    session_regenerate_id(true);

                    registrarLog('LOGIN', 'usuarios', $user['id'], "Inicio de sesión exitoso de usuario '{$user['nombre']}' (Rol: {$user['rol']})");

                    $redirectTarget = !empty($_POST['redirect']) ? $_POST['redirect'] : (!empty($_GET['redirect']) ? $_GET['redirect'] : '');
                    $isPwaOrMobile = isMobileDevice() || !empty($_POST['is_pwa']) || (!empty($_COOKIE['kortzen_is_pwa']) && $_COOKIE['kortzen_is_pwa'] === '1');

                    if (!empty($redirectTarget)) {
                        header('Location: ' . $redirectTarget);
                    } elseif ($user['rol'] === 'barbero') {
                        header('Location: barber-dashboard.php');
                    } elseif ($isPwaOrMobile) {
                        header('Location: pwa-admin.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error al procesar la solicitud';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KORTZEN - Acceso Staff y Administradores</title>
    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <link rel="stylesheet" href="css/style.css">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h1 class="login-logo">KORTZEN</h1>
            <p class="login-subtitle">Acceso Staff y Administradores</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ($_POST['redirect'] ?? '')); ?>">
                <input type="hidden" name="is_pwa" id="isPwaInput" value="0">

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email" maxlength="100">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-input" required
                        autocomplete="current-password" maxlength="100">
                </div>

                <!-- Google reCAPTCHA v3 (Invisible Score) -->
                <script src="https://www.google.com/recaptcha/api.js?render=<?php echo defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>"></script>
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                <script>
                  if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('<?php echo defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>', {action: 'login'}).then(function(token) {
                            var el = document.getElementById('g-recaptcha-response');
                            if (el) el.value = token;
                        });
                    });
                  }
                  if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                      var pwaInp = document.getElementById('isPwaInput');
                      if (pwaInp) pwaInp.value = '1';
                      try {
                          document.cookie = "kortzen_is_pwa=1; path=/; max-age=31536000; SameSite=Lax";
                      } catch(e){}
                  }
                </script>

                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>

            <div style="margin-top: 15px; margin-bottom: 15px;">
                <a href="cliente-login.php" style="color: #aaaaaa; font-size: 0.85rem; text-decoration: none; display: inline-block; transition: color 0.2s;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#aaaaaa'">¿Eres Cliente? Inicia Sesión Aquí →</a>
            </div>

            <a href="index.html" class="back-link">← Volver al sitio</a>
        </div>
    </div>
</body>

</html>
