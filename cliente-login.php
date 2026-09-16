<?php
/**
 * Login para CLIENTES con Google
 * Página dedicada para que los clientes inicien sesión antes de reservar
 */

session_start();
require_once 'config.php';

// Si ya está logueado como cliente, redirigir
if (isset($_SESSION['cliente_logged_in']) && $_SESSION['cliente_logged_in']) {
    header('Location: cliente-dashboard.php');
    exit;
}

// Verificar si Google OAuth está configurado
$googleConfigured = false;
$googleConfigPath = __DIR__ . '/api/auth/google-config.php';
if (file_exists($googleConfigPath)) {
    require_once $googleConfigPath;
    // Si el archivo existe, asumimos que está configurado correctamente 
    // para evitar falsos negativos de la función de validación
    $googleConfigured = true;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - KORTZEN</title>
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js" defer></script>
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--color-black-matte);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background-color: var(--color-charcoal);
            border: 1px solid var(--color-charcoal-light);
            border-radius: 12px;
            padding: 3rem;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .login-logo {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-white-pure);
            letter-spacing: 0.15em;
            margin-bottom: 0.5rem;
        }

        .login-logo span {
            color: var(--color-white-pure);
        }

        .login-subtitle {
            color: var(--color-gray-light);
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .benefits-list {
            text-align: left;
            margin: 1.5rem 0 2rem;
            padding: 1.25rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .benefits-list h4 {
            color: var(--color-white-off);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .benefits-list ul {
            list-style: none;
        }

        .benefits-list li {
            color: var(--color-gray-light);
            font-size: 0.9rem;
            padding: 0.4rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .benefits-list li::before {
            content: '✓';
            color: var(--color-white-pure);
            font-weight: 600;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            padding: 16px 24px;
            background: var(--color-white-pure);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-black-matte);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .btn-google:hover {
            background: var(--color-gray-light);
            transform: translateY(-2px);
        }

        .btn-google svg {
            flex-shrink: 0;
        }

        .security-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--color-gray);
            font-size: 0.8rem;
            margin-top: 1rem;
        }

        .security-note svg {
            color: var(--color-gray);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--color-gray);
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
        }

        .divider span {
            padding: 0 1rem;
        }

        .btn-guest {
            display: inline-block;
            color: var(--color-white-off);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .btn-guest:hover {
            color: var(--color-white-pure);
            text-decoration: underline;
        }

        .back-link {
            display: block;
            margin-top: 2rem;
            color: #6B7280;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #C0A062;
        }

        .error-box {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            color: #FCA5A5;
            font-size: 0.9rem;
        }

        .error-box code {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #9CA3AF;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.5rem;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1 class="login-logo">KORT<span>ZEN</span></h1>
        <p class="login-subtitle">Inicia sesión para reservar tu cita y acceder a beneficios exclusivos</p>

        <div class="benefits-list">
            <h4>Al iniciar sesión podrás:</h4>
            <ul>
                <li>Reservar citas rápidamente</li>
                <li>Ver tu historial de citas</li>
                <li>Recibir recordatorios por email</li>
                <li>Guardar tu barbero favorito</li>
            </ul>
        </div>

        <!-- Botón de Google siempre visible -->
        <a href="/api/auth/google-login.php" class="btn-google">
            <svg width="22" height="22" viewBox="0 0 24 24">
                <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4" />
                <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853" />
                <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                    fill="#FBBC05" />
                <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    fill="#EA4335" />
            </svg>
            Continuar con Google
        </a>

        <div class="security-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Conexión segura con tu cuenta de Google
        </div>

        <p style="color: #777777; font-size: 0.78rem; margin-top: 12px; line-height: 1.4; text-align: center;">
            Al ingresar, aceptas nuestros <a href="/terminos-de-uso.html" target="_blank" style="color: #D4AF37; text-decoration: underline;">Términos de Uso</a> y nuestra <a href="/politica-de-privacidad.html" target="_blank" style="color: #D4AF37; text-decoration: underline;">Política de Privacidad</a>.
        </p>

        <!-- Opción para clientes sin cuenta de Google -->
        <div style="margin: 1.5rem 0 1rem 0; padding: 1.25rem 1rem; background: rgba(37, 211, 102, 0.08); border: 1.5px solid rgba(37, 211, 102, 0.35); border-radius: 12px; text-align: center;">
            <div style="font-size: 0.92rem; font-weight: 800; color: #FFFFFF; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>💬</span> ¿No tienes cuenta de Google?
            </div>
            <p style="font-size: 0.82rem; color: #BBBBBB; margin-bottom: 12px; line-height: 1.4;">
                Llena un formulario rápido y envíalo a nuestro WhatsApp con 1 clic para que creemos tu perfil al instante.
            </p>
            <a href="solicitar-registro.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 16px; background: #25D366; color: #FFFFFF; font-weight: 800; font-size: 0.9rem; border-radius: 8px; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3); transition: all 0.2s ease;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.075-1.847-.426-1.547-.64-2.527-2.222-2.604-2.325-.077-.103-.623-.829-.623-1.581 0-.752.393-1.122.533-1.272.14-.15.305-.188.407-.188.102 0 .204.002.294.006.096.004.225-.036.35.267.13.313.442 1.079.48 1.157.039.078.065.17.013.273-.051.103-.077.167-.154.256-.077.09-.161.2-.23.269-.077.077-.157.161-.067.316.09.154.401.662.861 1.072.593.528 1.093.692 1.248.769.155.077.246.064.337-.039.091-.103.391-.455.495-.61.104-.155.207-.129.349-.077.142.052.898.423 1.053.5.155.078.258.117.297.181.039.065.039.378-.105.783z"/></svg>
                Solicitar Perfil por WhatsApp
            </a>
        </div>

        <div class="divider"><span>o</span></div>

        <a href="/contacto.html" class="btn-guest">Continuar sin cuenta →</a>

        <div style="margin-top: 20px; margin-bottom: 10px;">
            <a href="login.php" style="color: #aaaaaa; font-size: 0.85rem; text-decoration: none; display: inline-block; transition: color 0.2s;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#aaaaaa'">Acceso Barberos y Administradores →</a>
        </div>

        <a href="/" class="back-link">← Volver al inicio</a>
    </div>
</body>

</html>
