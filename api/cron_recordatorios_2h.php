<?php
/**
 * KORTZEN - Cron Script: Recordatorio de Citas (Push + Email 2 Horas Antes)
 * Soporta ejecución automática de fondo y Modo Prueba Manual (?test=1 o ?id=X)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

// Permitir si es CLI, si tiene el token secreto CRON_SECRET, o si es un administrador logueado
$isAuthorized = (php_sapi_name() === 'cli') || 
    (($_GET['token'] ?? $_GET['cron_key'] ?? '') === CRON_SECRET) ||
    (isLoggedIn() && ($_SESSION['user_rol'] ?? '') === 'admin');

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Token de cron inválido o sesión de administrador requerida.']);
    exit;
}

$isTest = isset($_GET['test']) || isset($_GET['forzar']) || isset($_GET['id']);
$testCitaId = intval($_GET['id'] ?? 0);

try {
    $pdo = getConnection();
    try {
        $pdo->exec("SET time_zone = '-05:00'");
    } catch (Exception $eTz) {}

    // Auto-migración columnas y tablas requeridas para entregas garantizadas
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_2h_enviado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NULL,
                endpoint VARCHAR(500) NOT NULL,
                p256dh TEXT NULL,
                auth TEXT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e_psub) {}
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones_pwa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                cita_id INT NULL,
                titulo VARCHAR(255) NOT NULL,
                mensaje TEXT NOT NULL,
                url VARCHAR(500) NULL,
                leido TINYINT(1) DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cli_leido (cliente_id, leido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e_pwanot) {}

    $citasPendientes = [];

    if ($testCitaId > 0) {
        // Buscar cita específica por ID para prueba manual
        $sql = "
            SELECT c.*, 
                   cli.nombre as cliente_nombre, cli.email as cliente_email, cli.telefono as cliente_telefono,
                   serv.nombre as servicio_nombre, serv.duracion_minutos,
                   barb.nombre as barbero_nombre,
                   suc.nombre as sucursal_nombre
            FROM citas c
            INNER JOIN clientes cli ON c.cliente_id = cli.id
            INNER JOIN servicios serv ON c.servicio_id = serv.id
            INNER JOIN usuarios barb ON c.barbero_id = barb.id
            LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
            WHERE c.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$testCitaId]);
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($isTest) {
        // Buscar la última cita activa de hoy o próxima para prueba manual
        $sql = "
            SELECT c.*, 
                   cli.nombre as cliente_nombre, cli.email as cliente_email, cli.telefono as cliente_telefono,
                   serv.nombre as servicio_nombre, serv.duracion_minutos,
                   barb.nombre as barbero_nombre,
                   suc.nombre as sucursal_nombre
            FROM citas c
            INNER JOIN clientes cli ON c.cliente_id = cli.id
            INNER JOIN servicios serv ON c.servicio_id = serv.id
            INNER JOIN usuarios barb ON c.barbero_id = barb.id
            LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
            WHERE c.estado != 'cancelada'
            ORDER BY c.fecha_hora DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Modo Normal Cron: Citas confirmadas/pendientes en la ventana de las próximas 3 horas (180 min)
        $sql = "
            SELECT c.*, 
                   cli.nombre as cliente_nombre, cli.email as cliente_email, cli.telefono as cliente_telefono,
                   serv.nombre as servicio_nombre, serv.duracion_minutos,
                   barb.nombre as barbero_nombre,
                   suc.nombre as sucursal_nombre
            FROM citas c
            INNER JOIN clientes cli ON c.cliente_id = cli.id
            INNER JOIN servicios serv ON c.servicio_id = serv.id
            INNER JOIN usuarios barb ON c.barbero_id = barb.id
            LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
            WHERE c.estado IN ('confirmada', 'pendiente')
              AND (c.recordatorio_2h_enviado IS NULL OR c.recordatorio_2h_enviado = 0)
              AND (
                  (c.fecha_hora >= NOW() - INTERVAL 30 MINUTE AND c.fecha_hora <= NOW() + INTERVAL 180 MINUTE)
                  OR (TIMESTAMPDIFF(MINUTE, NOW(), c.fecha_hora) BETWEEN -30 AND 180)
              )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $enviadosEmail = 0;
    $enviadosPush = 0;
    $detallesResumen = [];

    $stmtUpd = $pdo->prepare("UPDATE citas SET recordatorio_2h_enviado = 1 WHERE id = ?");

    foreach ($citasPendientes as $cita) {
        $citaId = $cita['id'];
        $clienteId = $cita['cliente_id'];
        $horaFormateada = date('H:i', strtotime($cita['fecha_hora']));
        $fechaFormateada = date('d/m/Y', strtotime($cita['fecha_hora']));

        // Marcar como procesado (si no es test forzado)
        if (!$isTest) {
            $stmtUpd->execute([$citaId]);
        }

        // 1. Enviar Correo Electrónico de Recordatorio
        $datosMail = [
            'fecha' => $fechaFormateada,
            'hora' => $horaFormateada,
            'servicio' => $cita['servicio_nombre'],
            'barbero' => $cita['barbero_nombre'],
            'sucursal' => $cita['sucursal_nombre'] ?? 'Kortzen Barbería'
        ];

        $emailEstado = 'sin_email';
        if (!empty($cita['cliente_email'])) {
            $mailResult = enviarCorreoRecordatorio($cita['cliente_email'], $cita['cliente_nombre'], $datosMail);
            if ($mailResult) {
                $enviadosEmail++;
                $emailEstado = 'enviado_ok (' . $cita['cliente_email'] . ')';
            } else {
                $emailEstado = 'error_envio (' . $cita['cliente_email'] . ')';
            }
        }

        // 2. Despachar Notificación Push al Sistema Operativo (Google FCM / Apple APNs) - Solo al cliente de la cita
        $countDispositivos = 0;
        try {
            if ($clienteId > 0) {
                $stmtPush = $pdo->prepare("SELECT * FROM push_subscriptions WHERE cliente_id = ?");
                $stmtPush->execute([$clienteId]);
                $subscriptions = $stmtPush->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($subscriptions)) {
                    $payloadPush = json_encode([
                        'title' => "✂️ Confirmar Asistencia: Tu cita es en 2 horas",
                        'body' => "¡Hola {$cita['cliente_nombre']}! Recuerda que a las {$horaFormateada} tienes tu cita con {$cita['barbero_nombre']}. Toca aquí para confirmar.",
                        'icon' => '/assets/icons/favicon.png',
                        'url' => "/cliente-dashboard.php?confirmar_cita={$citaId}"
                    ]);

                    require_once __DIR__ . '/../includes/webpush_helper.php';

                    foreach ($subscriptions as $sub) {
                        if (!empty($sub['endpoint'])) {
                            enviarWebPushVapid($sub, $payloadPush);
                            $enviadosPush++;
                            $countDispositivos++;
                        }
                    }
                }
            }
        } catch (Exception $eSub) {}

        // 3. Registrar Notificación PWA en base de datos para entrega garantizada al abrir la app
        try {
            $stmtNotifPwa = $pdo->prepare("
                INSERT INTO notificaciones_pwa (cliente_id, cita_id, titulo, mensaje, url) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $tituloPush = "✂️ Confirmar Asistencia: Tu cita es en 2 horas";
            $msgPush = "¡Hola {$cita['cliente_nombre']}! Recuerda que a las {$horaFormateada} tienes tu cita de {$cita['servicio_nombre']} con {$cita['barbero_nombre']}. Toca aquí para confirmar tu asistencia.";
            $urlPush = "/cliente-dashboard.php?confirmar_cita={$citaId}";

            $stmtNotifPwa->execute([$clienteId, $citaId, $tituloPush, $msgPush, $urlPush]);
        } catch (Exception $ePwa) {}

        $detallesResumen[] = [
            'cita_id' => $citaId,
            'cliente' => $cita['cliente_nombre'],
            'email' => $cita['cliente_email'],
            'email_estado' => $emailEstado,
            'fecha_hora' => $cita['fecha_hora'],
            'push_dispositivos' => $countDispositivos
        ];
    }

    echo json_encode([
        'success' => true,
        'modo' => $isTest ? 'PRUEBA MANUAL (?test=1)' : 'AUTOMÁTICO (CRON)',
        'citas_procesadas' => count($citasPendientes),
        'emails_enviados' => $enviadosEmail,
        'push_disparados' => $enviadosPush,
        'detalles' => $detallesResumen,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
