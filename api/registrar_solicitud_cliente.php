<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$sucursal = trim($_POST['sucursal'] ?? 'KORTZEN Llano Chico');
$servicio = trim($_POST['servicio'] ?? '');
$barbero = trim($_POST['barbero'] ?? '');
$notas = trim($_POST['notas'] ?? '');

if (empty($nombre) || empty($telefono)) {
    echo json_encode(['success' => false, 'message' => 'Nombre y teléfono son obligatorios']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Verificar si ya existe cliente con este teléfono
    $stmtCheck = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ? LIMIT 1");
    $stmtCheck->execute([$telefono]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $clienteId = $existing['id'];
        $stmtUpd = $pdo->prepare("UPDATE clientes SET nombre = ?, email = COALESCE(NULLIF(?, ''), email), notas = CONCAT(COALESCE(notas, ''), '\n[Solicitud WhatsApp: ', NOW(), ' - Sucursal: ', ?, ' - Servicio: ', ?, ']') WHERE id = ?");
        $stmtUpd->execute([$nombre, $email, $sucursal, $servicio, $clienteId]);
    } else {
        $codigoReferido = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre), 0, 5) . rand(100, 999));
        $stmtIns = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email, notas, codigo_referido, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
        $notaCompleta = "Registrado vía Solicitud WhatsApp. Sucursal: $sucursal. Barbero: $barbero. Servicio: $servicio" . ($notas ? " - Notas: $notas" : "");
        $stmtIns->execute([$nombre, $telefono, !empty($email) ? $email : null, $notaCompleta, $codigoReferido]);
        $clienteId = $pdo->lastInsertId();
    }
    
    echo json_encode(['success' => true, 'cliente_id' => $clienteId]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'note' => 'Guardado local omitido']);
}
