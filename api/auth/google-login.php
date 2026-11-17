<?php
/**
 * Iniciar Login con Google
 * Redirige al usuario a la página de autenticación de Google
 */

session_start();
require_once __DIR__ . '/google-config.php';

// Verificar si Google está configurado
if (!isGoogleConfigured()) {
    die('
        <div style="font-family: Arial; max-width: 600px; margin: 100px auto; padding: 30px; border: 2px solid #dc3545; border-radius: 12px; background: #f8d7da; color: #721c24;">
            <h2>⚠️ Google OAuth No Configurado</h2>
            <p>Para habilitar el login con Google, necesitas configurar las credenciales en:</p>
            <code style="background: #fff; padding: 5px 10px; border-radius: 4px; display: block; margin: 10px 0;">
                /api/auth/google-config.php
            </code>
            <p><strong>Pasos:</strong></p>
            <ol>
                <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Crea un proyecto y configura OAuth 2.0</li>
                <li>Copia el Client ID y Client Secret</li>
                <li>Pégalos en el archivo de configuración</li>
            </ol>
            <a href="/" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #721c24; color: white; text-decoration: none; border-radius: 6px;">
                ← Volver al inicio
            </a>
        </div>
    ');
}

// Obtener estado personalizado si viene de un flujo específico (ej: reservar paso 4)
$customState = null;
if (!empty($_GET['redirect']) && preg_match('/^[a-zA-Z0-9_\-]+$/', $_GET['redirect'])) {
    $customState = $_GET['redirect'];
} elseif (!empty($_GET['state']) && preg_match('/^[a-zA-Z0-9_\-]+$/', $_GET['state'])) {
    $customState = $_GET['state'];
}

// Generar URL de autorización y redirigir
$authUrl = getGoogleAuthUrl($customState);
header('Location: ' . $authUrl);
exit;
