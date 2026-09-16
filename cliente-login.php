<?php
/**
 * Login para CLIENTES - KORTZEN
 * Permite acceso mediante Teléfono/Email + Contraseña (con configuración en primer ingreso)
 * o mediante Google OAuth
 */

session_start();
require_once 'config.php';

// Si ya está logueado como cliente, redirigir
if (isClienteLoggedIn()) {
    header('Location: cliente-dashboard.php');
    exit;
}

// Cancelar configuración si pulsa volver
if (isset($_GET['cancel_setup'])) {
    unset($_SESSION['setup_cliente_id']);
    header('Location: cliente-login.php');
    exit;
}

$error = '';
$success = '';
$setupCliente = null;

// Verificar si viene cliente_id en sesión para configuración de contraseña
if (isset($_SESSION['setup_cliente_id'])) {
    try {
        $pdo = getConnection();
        $stmtC = $pdo->prepare("SELECT id, nombre, email, telefono, password FROM clientes WHERE id = ?");
        $stmtC->execute([$_SESSION['setup_cliente_id']]);
        $setupCliente = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$setupCliente || !empty($setupCliente['password'])) {
            $setupCliente = null;
            unset($_SESSION['setup_cliente_id']);
        }
    } catch (Exception $e) {
        $setupCliente = null;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $identificador = trim($_POST['identificador'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($identificador)) {
            $error = 'Por favor ingresa tu número de WhatsApp o correo electrónico.';
        } else {
            try {
                $pdo = getConnection();
                $identificadorLimpio = preg_replace('/[^0-9]/', '', $identificador);
                
                $sql = "SELECT * FROM clientes WHERE 
                        (telefono = ? OR (telefono != '' AND telefono = ?) OR (email = ? AND email != '')) 
                        ORDER BY id DESC LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$identificador, $identificadorLimpio, $identificador]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cliente) {
                    $error = 'No encontramos ninguna cuenta con ese teléfono o correo. Solicita la creación de tu perfil por WhatsApp.';
                } else {
                    // CASO 1: Primer ingreso - No tiene contraseña establecida
                    if (empty($cliente['password'])) {
                        $_SESSION['setup_cliente_id'] = $cliente['id'];
                        $setupCliente = $cliente;
                    } 
                    // CASO 2: Ya tiene contraseña establecida
                    else {
                        if (empty($password)) {
                            $error = 'Por favor ingresa tu contraseña.';
                        } elseif (password_verify($password, $cliente['password'])) {
                            // Login exitoso
                            $_SESSION['cliente_logged_in'] = true;
                            $_SESSION['cliente_id'] = $cliente['id'];
                            $_SESSION['cliente_nombre'] = $cliente['nombre'];
                            $_SESSION['cliente_email'] = $cliente['email'] ?? '';
                            $_SESSION['cliente_foto'] = $cliente['foto_perfil'] ?? null;
                            $_SESSION['cliente_google_id'] = $cliente['google_id'] ?? null;

                            if (!empty($cliente['email'])) {
                                $token = generarPwaToken($cliente['id'], $cliente['email']);
                                setcookie('kortzen_pwa_token', $cliente['id'] . ':' . $token, time() + (86400 * 90), '/', '', false, true);
                            }

                            $returnUrl = $_SESSION['kortzen_booking_return'] ?? 'cliente-dashboard.php';
                            unset($_SESSION['kortzen_booking_return']);
                            header('Location: ' . $returnUrl);
                            exit;
                        } else {
                            $error = 'La contraseña ingresada es incorrecta.';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Ocurrió un error al procesar tu solicitud. Por favor intenta de nuevo.';
            }
        }
    } 
    elseif ($action === 'set_password') {
        $clienteId = intval($_POST['cliente_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$clienteId || empty($newPassword)) {
            $error = 'Por favor ingresa tu nueva contraseña.';
        } elseif (strlen($newPassword) < 4) {
            $error = 'La contraseña debe tener al menos 4 caracteres.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                $pdo = getConnection();
                $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmtUpd = $pdo->prepare("UPDATE clientes SET password = ? WHERE id = ?");
                $stmtUpd->execute([$hashed, $clienteId]);

                $stmtGet = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
                $stmtGet->execute([$clienteId]);
                $cliente = $stmtGet->fetch(PDO::FETCH_ASSOC);

                unset($_SESSION['setup_cliente_id']);
                $setupCliente = null;

                $_SESSION['cliente_logged_in'] = true;
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nombre'] = $cliente['nombre'];
                $_SESSION['cliente_email'] = $cliente['email'] ?? '';
                $_SESSION['cliente_foto'] = $cliente['foto_perfil'] ?? null;
                $_SESSION['cliente_google_id'] = $cliente['google_id'] ?? null;

                if (!empty($cliente['email'])) {
                    $token = generarPwaToken($cliente['id'], $cliente['email']);
                    setcookie('kortzen_pwa_token', $cliente['id'] . ':' . $token, time() + (86400 * 90), '/', '', false, true);
                }

                $returnUrl = $_SESSION['kortzen_booking_return'] ?? 'cliente-dashboard.php';
                unset($_SESSION['kortzen_booking_return']);
                header('Location: ' . $returnUrl);
                exit;
            } catch (Exception $e) {
                $error = 'Error al guardar tu contraseña.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $setupCliente ? 'Crear Contraseña' : 'Iniciar Sesión'; ?> - KORTZEN</title>
    
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js" defer></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body, 'Outfit', sans-serif);
            background-color: var(--color-charcoal, #F7F7F7);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            color: #111111;
        }

        .login-container {
            background-color: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            padding: 2.25rem 1.75rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.07);
            box-sizing: border-box;
        }

        .login-logo {
            font-size: 1.85rem;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.15em;
            margin-bottom: 0.4rem;
        }

        .login-logo span {
            color: #000000;
        }

        .login-subtitle {
            color: #555555;
            font-size: 0.88rem;
            margin-bottom: 1.5rem;
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #222222;
            margin-bottom: 0.35rem;
        }

        .form-control {
            width: 100%;
            padding: 13px 14px;
            background: #F9FAFB;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            color: #111111;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            background: #FFFFFF;
        }

        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            background: #000000;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .btn-submit:hover, .btn-submit:active {
            background: #222222;
            color: #FFFFFF;
            transform: translateY(-2px);
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 13px 20px;
            background: #FFFFFF;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #111111;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            box-sizing: border-box;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
        }

        .btn-google:hover {
            background: #F3F4F6;
            border-color: #9CA3AF;
            color: #000000;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.25rem 0;
            color: #888888;
            font-size: 0.8rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E5E7EB;
        }

        .divider span {
            padding: 0 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .alert-box {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            text-align: left;
            line-height: 1.4;
        }

        .alert-danger {
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
        }

        .alert-success {
            background: #DCFCE7;
            border: 1px solid #86EFAC;
            color: #166534;
        }

        .first-time-box {
            background: #FFFBEB;
            border: 1.5px solid #F59E0B;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 1.25rem;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1 class="login-logo">KORT<span>ZEN</span></h1>

        <?php if ($setupCliente): ?>
            <!-- VISTA DE PRIMER INGRESO: ESTABLECER CONTRASEÑA -->
            <div class="first-time-box">
                <div style="font-size: 1.05rem; font-weight: 900; color: #92400E; margin-bottom: 4px;">
                    👋 ¡Hola, <?php echo htmlspecialchars(explode(' ', trim($setupCliente['nombre']))[0]); ?>!
                </div>
                <div style="font-size: 0.85rem; color: #1F2937; line-height: 1.4;">
                    Es tu primer ingreso a la plataforma. Por favor crea tu <strong>contraseña personal</strong> para acceder a tu cuenta y agendar tus próximas citas.
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert-box alert-danger">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="cliente-login.php">
                <input type="hidden" name="action" value="set_password">
                <input type="hidden" name="cliente_id" value="<?php echo $setupCliente['id']; ?>">

                <div class="form-group">
                    <label class="form-label" for="new_password">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Mínimo 4 caracteres" required autofocus minlength="4">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repite tu contraseña" required minlength="4">
                </div>

                <button type="submit" class="btn-submit">
                    ✓ Guardar Contraseña y Entrar
                </button>
            </form>

            <div style="margin-top: 1.25rem;">
                <a href="cliente-login.php?cancel_setup=1" style="color: #6B7280; font-size: 0.82rem; text-decoration: none;">← Cancelar y volver</a>
            </div>

        <?php else: ?>
            <!-- VISTA DE INICIO DE SESIÓN REGULAR -->
            <p class="login-subtitle">Inicia sesión con tu teléfono o correo para acceder a tu cuenta y agendar citas.</p>

            <?php if ($error): ?>
                <div class="alert-box alert-danger">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="cliente-login.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label" for="identificador">WhatsApp / Teléfono o Correo</label>
                    <input type="text" id="identificador" name="identificador" class="form-control" placeholder="Ej: 0991234567 o correo@ejemplo.com" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['identificador'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label class="form-label" for="password" style="margin: 0;">Contraseña</label>
                        <span style="font-size: 0.72rem; color: #6B7280;">(Si es 1ra vez, déjalo vacío)</span>
                    </div>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Tu contraseña" autocomplete="current-password">
                </div>

                <button type="submit" class="btn-submit">
                    Iniciar Sesión
                </button>
            </form>

            <div class="divider"><span>o</span></div>

            <!-- Botón de Google -->
            <a href="/api/auth/google-login.php" class="btn-google">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continuar con Google
            </a>

            <!-- Opción para clientes sin cuenta creada aún -->
            <div style="margin: 1.5rem 0 1rem 0; padding: 1.15rem 1.1rem; background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 12px; text-align: center;">
                <div style="font-size: 0.92rem; font-weight: 800; color: #111827; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>💬</span> ¿No tienes perfil creado aún?
                </div>
                <p style="font-size: 0.83rem; color: #374151; margin-bottom: 12px; line-height: 1.4;">
                    Solicita tu registro por WhatsApp para que el administrador cree tu cuenta.
                </p>
                <a href="solicitar-registro.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 14px; background: #16A34A; color: #FFFFFF; font-weight: 800; font-size: 0.85rem; border-radius: 8px; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25); transition: all 0.2s ease;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.075-1.847-.426-1.547-.64-2.527-2.222-2.604-2.325-.077-.103-.623-.829-.623-1.581 0-.752.393-1.122.533-1.272.14-.15.305-.188.407-.188.102 0 .204.002.294.006.096.004.225-.036.35.267.13.313.442 1.079.48 1.157.039.078.065.17.013.273-.051.103-.077.167-.154.256-.077.09-.161.2-.23.269-.077.077-.157.161-.067.316.09.154.401.662.861 1.072.593.528 1.093.692 1.248.769.155.077.246.064.337-.039.091-.103.391-.455.495-.61.104-.155.207-.129.349-.077.142.052.898.423 1.053.5.155.078.258.117.297.181.039.065.039.378-.105.783z"/></svg>
                    Solicitar Perfil por WhatsApp
                </a>
            </div>

            <div style="margin-top: 15px; margin-bottom: 5px;">
                <a href="login.php" style="color: #6B7280; font-size: 0.82rem; text-decoration: none; display: inline-block; transition: color 0.2s;" onmouseover="this.style.color='#111111'" onmouseout="this.style.color='#6B7280'">Acceso Barberos y Administradores →</a>
            </div>

            <a href="/" style="display: block; margin-top: 1.25rem; color: #6B7280; font-size: 0.85rem; text-decoration: none;" onmouseover="this.style.color='#111111'" onmouseout="this.style.color='#6B7280'">← Volver al inicio</a>
        <?php endif; ?>
    </div>
</body>

</html>
