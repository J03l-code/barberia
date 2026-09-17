<?php
/**
 * KORTZEN - WebPush VAPID Dispatcher Helper with RFC 8291 Encryption
 */

require_once __DIR__ . '/webpush_encrypt.php';

define('VAPID_PUBLIC_KEY', 'BN3FX2wXwG5gj_QlNIm0OZuDaQj37jelLWAZHsjGpu86iIlFkIvcylgw9rimD6APwtzJOzYiIbC_V3qiaTZ6Z8U');
define('VAPID_PRIVATE_KEY', 'O9mKeqQcXT9n-9wt_Jc3ypub6GrlV9av9rPQb2lVxDc');
define('VAPID_SUBJECT', 'mailto:info@kortzen.com');

function kortzen_b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function kortzen_b64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function generarVapidAuthHeader($endpoint, $vapidPublic = VAPID_PUBLIC_KEY, $vapidPrivate = VAPID_PRIVATE_KEY, $subject = VAPID_SUBJECT) {
    $parsedUrl = parse_url($endpoint);
    $aud = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');

    $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
    $payload = json_encode([
        'aud' => $aud,
        'exp' => time() + 43200,
        'sub' => $subject
    ]);

    $jwtUnsigned = kortzen_b64url_encode($header) . '.' . kortzen_b64url_encode($payload);

    $privKeyRaw = kortzen_b64url_decode($vapidPrivate);
    $pubKeyRaw = kortzen_b64url_decode($vapidPublic);

    $derPriv = hex2bin("30770201010420") . $privKeyRaw . hex2bin("a00a06082a8648ce3d030107a144034200") . $pubKeyRaw;
    $pemPriv = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($derPriv), 64, "\n") . "-----END EC PRIVATE KEY-----\n";

    $signature = '';
    $success = openssl_sign($jwtUnsigned, $signature, $pemPriv, OPENSSL_ALGO_SHA256);

    if (!$success) {
        return false;
    }

    $asn1 = $signature;
    $offset = 2;
    $rLength = ord($asn1[$offset + 1]);
    $r = substr($asn1, $offset + 2, $rLength);
    $sLength = ord($asn1[$offset + 2 + $rLength + 1]);
    $s = substr($asn1, $offset + 2 + $rLength + 2, $sLength);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    $rawSignature = $r . $s;
    $jwt = $jwtUnsigned . '.' . kortzen_b64url_encode($rawSignature);

    return 'vapid t=' . $jwt . ', k=' . $vapidPublic;
}

/**
 * Despachar Notificación Push con Cifrado RFC 8291 (aes128gcm) a Apple APNs (iPhone 16 Pro Max) / Google FCM
 */
function enviarWebPushVapid($subscription, $payload) {
    $endpoint = is_array($subscription) ? ($subscription['endpoint'] ?? '') : $subscription;
    if (empty($endpoint) || strpos($endpoint, 'http') !== 0) {
        return false;
    }

    $p256dh = is_array($subscription) ? ($subscription['p256dh'] ?? '') : '';
    $authSecret = is_array($subscription) ? ($subscription['auth'] ?? '') : '';

    $authHeader = generarVapidAuthHeader($endpoint);

    // Intentar cifrado RFC 8291 aes128gcm para Apple APNs (iOS Safari)
    $encryptedBody = null;
    if (!empty($p256dh) && !empty($authSecret) && $p256dh !== 'granted') {
        $encryptedBody = encryptWebPushPayload($payload, $p256dh, $authSecret);
    }

    $headers = [];
    if ($authHeader) {
        $headers[] = 'Authorization: ' . $authHeader;
    }
    $headers[] = 'TTL: 86400';
    $headers[] = 'Urgency: high';

    if ($encryptedBody !== false && $encryptedBody !== null) {
        $headers[] = 'Content-Type: application/octet-stream';
        $headers[] = 'Content-Encoding: aes128gcm';
        $postData = $encryptedBody;
    } else {
        $headers[] = 'Content-Type: application/json';
        $postData = is_string($payload) ? $payload : json_encode($payload);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}

/**
 * Garantizar estructura de tablas para notificaciones de clientes y barberos
 */
function asegurarTablasNotificaciones($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones_pwa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NULL,
                usuario_id INT NULL,
                cita_id INT NULL,
                titulo VARCHAR(255) NOT NULL,
                mensaje TEXT NOT NULL,
                url VARCHAR(500) DEFAULT '/barber-dashboard.php',
                leido TINYINT(1) DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cli_leido (cliente_id, leido),
                INDEX idx_usu_leido (usuario_id, leido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE notificaciones_pwa ADD COLUMN usuario_id INT NULL AFTER cliente_id");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE notificaciones_pwa MODIFY cliente_id INT NULL");
    } catch (Exception $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NULL,
                usuario_id INT NULL,
                endpoint VARCHAR(500) NOT NULL,
                p256dh TEXT NULL,
                auth TEXT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_id),
                INDEX idx_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE push_subscriptions ADD COLUMN usuario_id INT NULL AFTER cliente_id");
    } catch (Exception $e) {}
}

/**
 * Notificar explícitamente al Barbero cuando se crea, confirma o cancela una cita
 * 
 * @param PDO $pdo Conexión a la BD
 * @param int $barberoId ID del barbero en tabla usuarios
 * @param int $citaId ID de la cita
 * @param string $tipo 'nueva_reserva' | 'cita_confirmada' | 'cita_cancelada' | 'cita_reagendada'
 * @param array $datos Opcional con datos [cliente, servicio, fecha, hora, sucursal]
 */
function notificarBarbero($pdo, $barberoId, $citaId, $tipo, $datos = []) {
    $barberoId = intval($barberoId);
    $citaId = intval($citaId);

    if ($barberoId <= 0) {
        if ($citaId > 0) {
            $stmtB = $pdo->prepare("SELECT barbero_id FROM citas WHERE id = ?");
            $stmtB->execute([$citaId]);
            $barberoId = intval($stmtB->fetchColumn() ?: 0);
        }
    }

    if ($barberoId <= 0) return false;

    asegurarTablasNotificaciones($pdo);

    // Obtener datos de la cita si no vienen completos
    if (empty($datos['cliente']) || empty($datos['servicio']) || empty($datos['fecha'])) {
        try {
            $stmtC = $pdo->prepare("
                SELECT c.*, cl.nombre as cliente_nombre, s.nombre as servicio_nombre, suc.nombre as sucursal_nombre
                FROM citas c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
                WHERE c.id = ?
            ");
            $stmtC->execute([$citaId]);
            $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);

            if ($cRow) {
                $datos['cliente'] = $datos['cliente'] ?? ($cRow['cliente_nombre'] ?? 'Cliente');
                $datos['servicio'] = $datos['servicio'] ?? ($cRow['servicio_nombre'] ?? 'Servicio');
                $datos['fecha'] = $datos['fecha'] ?? date('d/m/Y', strtotime($cRow['fecha_hora']));
                $datos['hora'] = $datos['hora'] ?? date('H:i', strtotime($cRow['fecha_hora']));
                $datos['sucursal'] = $datos['sucursal'] ?? ($cRow['sucursal_nombre'] ?? '');
            }
        } catch (Exception $e) {}
    }

    $cliente = trim($datos['cliente'] ?? 'Cliente');
    $servicio = trim($datos['servicio'] ?? 'Servicio');
    $fecha = trim($datos['fecha'] ?? date('d/m/Y'));
    $hora = trim($datos['hora'] ?? date('H:i'));

    $titulo = 'Actualización de Cita';
    $mensaje = "Tienes una novedad sobre la cita de {$cliente}.";
    $url = '/barber-dashboard.php';

    switch ($tipo) {
        case 'nueva_reserva':
            $titulo = 'Nueva Cita Agendada';
            $mensaje = "El cliente {$cliente} reservó {$servicio} para el {$fecha} a las {$hora}.";
            break;
        case 'cita_confirmada':
            $titulo = 'Cita Confirmada';
            $mensaje = "La cita de {$cliente} ({$servicio}) para el {$fecha} a las {$hora} fue confirmada.";
            break;
        case 'cita_cancelada':
            $titulo = 'Cita Cancelada';
            $mensaje = "La cita de {$cliente} ({$servicio}) para el {$fecha} a las {$hora} ha sido cancelada.";
            break;
        case 'cita_reagendada':
            $titulo = 'Cita Reagendada';
            $mensaje = "La cita de {$cliente} ({$servicio}) fue reprogramada para el {$fecha} a las {$hora}.";
            break;
    }

    // 1. Guardar en base de datos
    try {
        $stmtIns = $pdo->prepare("
            INSERT INTO notificaciones_pwa (usuario_id, cita_id, titulo, mensaje, url, leido, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmtIns->execute([$barberoId, $citaId ?: null, $titulo, $mensaje, $url]);
    } catch (Exception $e) {}

    // 2. Despachar Notificaciones Push a todos los dispositivos del barbero
    try {
        $stmtSubs = $pdo->prepare("SELECT * FROM push_subscriptions WHERE usuario_id = ?");
        $stmtSubs->execute([$barberoId]);
        $subs = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($subs)) {
            $payload = json_encode([
                'title' => $titulo,
                'body' => $mensaje,
                'icon' => '/assets/icons/favicon.png',
                'url' => $url,
                'data' => [
                    'url' => $url,
                    'cita_id' => $citaId,
                    'tipo' => $tipo
                ]
            ]);

            foreach ($subs as $sub) {
                if (!empty($sub['endpoint'])) {
                    @enviarWebPushVapid($sub, $payload);
                }
            }
        }
    } catch (Exception $e) {}

    return true;
}

/**
 * Notificar al Cliente
 */
function notificarCliente($pdo, $clienteId, $citaId, $titulo, $mensaje, $url = '/cliente-dashboard.php') {
    $clienteId = intval($clienteId);
    if ($clienteId <= 0) return false;

    asegurarTablasNotificaciones($pdo);

    try {
        $stmtIns = $pdo->prepare("
            INSERT INTO notificaciones_pwa (cliente_id, cita_id, titulo, mensaje, url, leido, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmtIns->execute([$clienteId, $citaId ?: null, $titulo, $mensaje, $url]);
    } catch (Exception $e) {}

    try {
        $stmtSubs = $pdo->prepare("SELECT * FROM push_subscriptions WHERE cliente_id = ?");
        $stmtSubs->execute([$clienteId]);
        $subs = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($subs)) {
            $payload = json_encode([
                'title' => $titulo,
                'body' => $mensaje,
                'icon' => '/assets/icons/favicon.png',
                'url' => $url,
                'data' => [
                    'url' => $url,
                    'cita_id' => $citaId
                ]
            ]);

            foreach ($subs as $sub) {
                if (!empty($sub['endpoint'])) {
                    @enviarWebPushVapid($sub, $payload);
                }
            }
        }
    } catch (Exception $e) {}

    return true;
}

