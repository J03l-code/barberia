<?php
/**
 * Configuración de Google OAuth 2.0
 * KORTZEN - Sistema de Login con Google
 * 
 * INSTRUCCIONES PARA OBTENER LAS CREDENCIALES:
 * 
 * 1. Ve a https://console.cloud.google.com/
 * 2. Crea un nuevo proyecto o selecciona uno existente
 * 3. Ve a "APIs & Services" > "Credentials"
 * 4. Click en "Create Credentials" > "OAuth client ID"
 * 5. Selecciona "Web application"
 * 6. Configura:
 *    - Nombre: KORTZEN Login
 *    - Orígenes autorizados de JavaScript:
 *      * http://localhost:2020 (desarrollo)
 *      * https://kortzenbrb.jiyanedesign.com (producción)
 *    - URIs de redirección autorizados:
 *      * http://localhost:2020/api/auth/google-callback.php (desarrollo)
 *      * https://kortzenbrb.jiyanedesign.com/api/auth/google-callback.php (producción)
 * 7. Copia el Client ID y Client Secret aquí abajo
 * ID: 287462075073-lmhplvlmgarkmgosqbo34oqco25esfn5.apps.googleusercontent.com
 * secret: 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
 */

// ============================================================
// ⚠️ CONFIGURA ESTOS VALORES CON TUS CREDENCIALES DE GOOGLE
// ============================================================

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '287462075073-lmhplvlmgarkmgosqbo34oqco25esfn5.apps.googleusercontent.com');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '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');
}

// URLs de redirección dinámicas según el dominio actual
$isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);
if ($isLocalhost) {
    define('GOOGLE_REDIRECT_URI', 'http://localhost:2020/api/auth/google-callback.php');
    define('SITE_BASE_URL', 'http://localhost:2020');
} else {
    $currentBase = defined('SITE_URL') ? SITE_URL : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'barbershop.jiyanedesign.com'));
    define('GOOGLE_REDIRECT_URI', rtrim($currentBase, '/') . '/api/auth/google-callback.php');
    define('SITE_BASE_URL', rtrim($currentBase, '/'));
}

// URLs de Google OAuth 2.0
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');

// Scopes que solicitamos (perfil y email sin bloqueo de verificación)
define('GOOGLE_SCOPES', 'openid email profile');

/**
 * Genera la URL de autorización de Google
 * @param string $state Token CSRF para seguridad
 * @return string URL para redirigir al usuario
 */
function getGoogleAuthUrl($state = null)
{
    if ($state === null) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
    }

    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => GOOGLE_SCOPES,
        'access_type' => 'offline',
        'state' => $state,
        'prompt' => 'select_account' // Siempre mostrar selector de cuenta
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Intercambia el código de autorización por un access token
 * @param string $code Código recibido de Google
 * @return array|false Datos del token o false si falla
 */
function exchangeCodeForToken($code)
{
    $postData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google Token Error: HTTP $httpCode - $response");
        return false;
    }

    return json_decode($response, true);
}

/**
 * Obtiene la información del usuario usando el access token
 * @param string $accessToken Token de acceso
 * @return array|false Datos del usuario o false si falla
 */
function getGoogleUserInfo($accessToken)
{
    $ch = curl_init(GOOGLE_USERINFO_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google UserInfo Error: HTTP $httpCode - $response");
        return false;
    }

    return json_decode($response, true);
}

/**
 * Verifica si las credenciales de Google están configuradas
 * @return bool
 */
function isGoogleConfigured()
{
    // Verificación simplificada: si el ID es largo, asumimos que es real.
    return strlen(GOOGLE_CLIENT_ID) > 30;
}
