<?php
/**
 * Debug temporal para diagnosticar slots de barbero
 * ELIMINAR DESPUÉS DE DIAGNÓSTICO
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $barberoId = intval($_GET['barbero_id'] ?? 0);
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    
    if (!$barberoId) {
        echo json_encode(['error' => 'barbero_id requerido']);
        exit;
    }
    
    $diaSem = date('w', strtotime($fecha));
    
    // 1. Horario del barbero para ese dia
    $stmtH = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?");
    $stmtH->execute([$barberoId, $diaSem]);
    $horario = $stmtH->fetch(PDO::FETCH_ASSOC);
    
    // 2. Todas las citas del dia
    $stmtC = $pdo->prepare("SELECT c.id, c.fecha_hora, c.hora_fin, c.estado, s.nombre as servicio, s.duracion_minutos 
        FROM citas c LEFT JOIN servicios s ON c.servicio_id = s.id 
        WHERE c.barbero_id = ? AND DATE(c.fecha_hora) = ? ORDER BY c.fecha_hora");
    $stmtC->execute([$barberoId, $fecha]);
    $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Almuerzo
    $stmtU = $pdo->prepare("SELECT almuerzo_activo, almuerzo_inicio, almuerzo_fin FROM usuarios WHERE id = ?");
    $stmtU->execute([$barberoId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);
    
    // 4. Bloqueos
    $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
    $stmtB->execute([$barberoId, $fecha]);
    $bloqDia = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtBH = $pdo->prepare("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
    $stmtBH->execute([$barberoId, $fecha]);
    $bloqHoras = $stmtBH->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Calcular ocupados
    $ocupados = [];
    foreach ($citas as $c) {
        if ($c['estado'] === 'cancelada' || $c['estado'] === 'no_asistio') continue;
        $ini = strtotime($c['fecha_hora']);
        $rawDur = intval($c['duracion_minutos']) > 0 ? intval($c['duracion_minutos']) : 60;
        if ($rawDur <= 0) $effDur = 60;
        elseif ($rawDur <= 30) $effDur = 30;
        elseif ($rawDur <= 60) $effDur = 60;
        else $effDur = (int)(ceil($rawDur / 30) * 30);
        $fin = $ini + ($effDur * 60);
        $ocupados[] = ['inicio' => date('H:i', $ini), 'fin' => date('H:i', $fin), 'servicio' => $c['servicio'], 'estado' => $c['estado']];
    }
    
    // Almuerzo
    if ($user && ($user['almuerzo_activo'] ?? 1) == 1 && !empty($user['almuerzo_inicio']) && !empty($user['almuerzo_fin'])) {
        $ocupados[] = ['inicio' => $user['almuerzo_inicio'], 'fin' => $user['almuerzo_fin'], 'tipo' => 'almuerzo'];
    }
    
    // Bloqueos parciales
    foreach ($bloqHoras as $bh) {
        $ocupados[] = ['inicio' => $bh['hora_inicio'], 'fin' => $bh['hora_fin'], 'tipo' => 'bloqueo_hora'];
    }

    echo json_encode([
        'fecha' => $fecha,
        'dia_semana' => $diaSem,
        'dia_nombre' => ['Dom','Lun','Mar','Mie','Jue','Vie','Sab'][$diaSem],
        'horario_barbero' => $horario,
        'almuerzo' => $user,
        'citas_del_dia' => $citas,
        'bloqueos_dia' => $bloqDia,
        'bloqueos_horas' => $bloqHoras,
        'intervalos_ocupados' => $ocupados
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
