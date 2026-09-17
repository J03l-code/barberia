<?php
session_start();
require_once '../config.php';

// Verificar login
if (!isClienteLoggedIn()) {
    header('Location: ../cliente-login.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../mis-citas.php');
    exit;
}

$citaId = intval($_POST['cita_id'] ?? 0);
$cliente = getCurrentCliente();

if (!$citaId) {
    header('Location: ../mis-citas.php?error=' . urlencode('ID de cita inválido'));
    exit;
}

try {
    $pdo = getConnection();

    // 1. Verificar que la cita pertenece al cliente
    $stmt = $pdo->prepare("SELECT id, fecha_hora FROM citas WHERE id = ? AND cliente_id = ?");
    $stmt->execute([$citaId, $cliente['id']]);
    $cita = $stmt->fetch();

    if (!$cita) {
        throw new Exception('Cita no encontrada o no tienes permiso.');
    }

    // 2. Verificar si ya pasó la fecha (opcional, pero recomendado)
    if (strtotime($cita['fecha_hora']) < time()) {
        throw new Exception('No puedes cancelar una cita pasada.');
    }

    // 3. Cancelar
    $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
    $stmtUpdate->execute([$citaId]);

    // 4. Notificar al barbero
    try {
        require_once __DIR__ . '/../includes/webpush_helper.php';
        notificarBarbero($pdo, $cita['barbero_id'] ?? 0, $citaId, 'cita_cancelada');
    } catch (Exception $eNotif) {}

    // 5. Log para admins
    if (function_exists('registrarLog')) {
        $cName = $cliente['nombre'] ?? 'Cliente';
        registrarLog('CANCELAR', 'citas', $citaId, "El cliente '$cName' canceló su cita #$citaId.");
    }

    header('Location: ../mis-citas.php?success=' . urlencode('Cita cancelada correctamente.'));

} catch (Exception $e) {
    header('Location: ../mis-citas.php?error=' . urlencode($e->getMessage()));
}
