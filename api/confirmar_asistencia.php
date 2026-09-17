<?php
/**
 * KORTZEN - API Confirmar Asistencia de Cita por el Cliente
 */
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cita_id = intval($_POST['cita_id'] ?? $_GET['cita_id'] ?? $_GET['id'] ?? 0);
$cliente_id = isset($_SESSION['cliente_id']) ? intval($_SESSION['cliente_id']) : 0;

if ($cita_id <= 0) {
    header('Location: ../cliente-dashboard.php?error=' . urlencode('Cita no válida.'));
    exit;
}

try {
    $pdo = getConnection();

    // Auto-migración columna asistencia_confirmada
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN asistencia_confirmada TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    // Verificar propiedad si el cliente está en sesión
    if ($cliente_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND cliente_id = ?");
        $stmtCheck->execute([$cita_id, $cliente_id]);
        $valida = $stmtCheck->fetchColumn();

        if (!$valida) {
            header('Location: ../cliente-dashboard.php?error=' . urlencode('Cita no encontrada o no pertenece a tu cuenta.'));
            exit;
        }
    }

    // Actualizar estado de asistencia_confirmada = 1 y estado = 'confirmada' en toda la plataforma
    $stmtUpd = $pdo->prepare("UPDATE citas SET asistencia_confirmada = 1, estado = 'confirmada' WHERE id = ?");
    $stmtUpd->execute([$cita_id]);

    $stmtCName = $pdo->prepare("
        SELECT c.nombre, cita.barbero_id, s.nombre as servicio_nombre, cita.fecha_hora 
        FROM citas cita 
        LEFT JOIN clientes c ON cita.cliente_id = c.id 
        LEFT JOIN servicios s ON cita.servicio_id = s.id 
        WHERE cita.id = ?
    ");
    $stmtCName->execute([$cita_id]);
    $citaData = $stmtCName->fetch(PDO::FETCH_ASSOC);
    $clienteNombre = $citaData['nombre'] ?? "Cita #$cita_id";

    registrarLog('CONFIRMAR', 'citas', $cita_id, "El cliente '$clienteNombre' confirmó su asistencia a la cita #$cita_id. Estado actualizado a 'confirmada'.");

    // Notificar al barbero en tiempo real
    try {
        require_once __DIR__ . '/../includes/webpush_helper.php';
        if (!empty($citaData['barbero_id'])) {
            notificarBarbero($pdo, $citaData['barbero_id'], $cita_id, 'cita_confirmada', [
                'cliente' => $clienteNombre,
                'servicio' => $citaData['servicio_nombre'] ?? 'Servicio',
                'fecha' => !empty($citaData['fecha_hora']) ? date('d/m/Y', strtotime($citaData['fecha_hora'])) : date('d/m/Y'),
                'hora' => !empty($citaData['fecha_hora']) ? date('H:i', strtotime($citaData['fecha_hora'])) : date('H:i')
            ]);
        }
    } catch (Exception $eNotif) {}

    header('Location: ../cliente-dashboard.php?success=' . urlencode('Excelente. Has confirmado tu asistencia a la cita. Tu barbero ha sido notificado.'));
    exit;

} catch (Exception $e) {
    header('Location: ../cliente-dashboard.php?error=' . urlencode('Error al confirmar asistencia: ' . $e->getMessage()));
    exit;
}
