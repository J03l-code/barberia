<?php
require_once '../config.php';

header('Content-Type: application/json');

// Validar parámetros
$fecha = $_GET['fecha'] ?? null;
$barberoId = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : 0;
$servicioId = isset($_GET['servicio_id']) ? intval($_GET['servicio_id']) : 0;
$excludeCitaId = isset($_GET['exclude_cita_id']) ? intval($_GET['exclude_cita_id']) : 0;

if (!$fecha || !$barberoId || !$servicioId) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros (fecha, barbero, servicio)', 'slots' => []]);
    exit;
}

try {
    $pdo = getConnection();

    // 1. Obtener duración del servicio
    $stmtServicio = $pdo->prepare("SELECT duracion_minutos, nombre FROM servicios WHERE id = ?");
    $stmtServicio->execute([$servicioId]);
    $servicio = $stmtServicio->fetch();
    $duracion = $servicio ? intval($servicio['duracion_minutos']) : 30;

    // 2. Verificar día de la semana y si trabaja
    $timestamp = strtotime($fecha);
    $diaSemana = date('w', $timestamp); // 0 (Domingo) a 6 (Sábado)

    // Obtener horario base del barbero para ese día
    $stmtHorario = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ? AND activo = 1");
    $stmtHorario->execute([$barberoId, $diaSemana]);
    $horarioBase = $stmtHorario->fetch();

    if (!$horarioBase) {
        echo json_encode([
            'success' => true,
            'slots' => [],
            'mensaje' => 'El barbero no labora el día seleccionado.',
            'duracion_servicio' => $duracion
        ]);
        exit;
    }

    // 3. Verificar días bloqueados (vacaciones, libres)
    $stmtBloqueo = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
    $stmtBloqueo->execute([$barberoId, $fecha]);
    $bloqueo = $stmtBloqueo->fetch();

    if ($bloqueo && $bloqueo['todo_el_dia']) {
        echo json_encode([
            'success' => true,
            'slots' => [],
            'mensaje' => 'El barbero tiene el día completo bloqueado o en descanso.',
            'duracion_servicio' => $duracion
        ]);
        exit;
    }

    // 3.5. Obtener bloqueos por horas
    $stmtBloqueoHoras = $pdo->prepare("SELECT hora_inicio, hora_fin FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
    $stmtBloqueoHoras->execute([$barberoId, $fecha]);
    $bloqueosParciales = $stmtBloqueoHoras->fetchAll(PDO::FETCH_ASSOC);

    // 3.6. Incluir Horario de Almuerzo Fijo del Barbero
    $stmtBarberUser = $pdo->prepare("SELECT almuerzo_inicio, almuerzo_fin, almuerzo_activo FROM usuarios WHERE id = ?");
    $stmtBarberUser->execute([$barberoId]);
    $barberUser = $stmtBarberUser->fetch(PDO::FETCH_ASSOC);

    if ($barberUser && ($barberUser['almuerzo_activo'] ?? 1) == 1 && !empty($barberUser['almuerzo_inicio']) && !empty($barberUser['almuerzo_fin'])) {
        $bloqueosParciales[] = [
            'hora_inicio' => $barberUser['almuerzo_inicio'],
            'hora_fin' => $barberUser['almuerzo_fin']
        ];
    }

    // Definir límites del día
    $horaInicioStr = $horarioBase['hora_inicio']; // Ej: "10:00:00"
    $horaFinStr = $horarioBase['hora_fin'];       // Ej: "20:00:00"

    // Convertir a timestamps para el día específico
    $startOfDay = ceil(strtotime("$fecha $horaInicioStr") / 3600) * 3600;
    $endOfDay = strtotime("$fecha $horaFinStr");

    // Si es hoy, filtrar horas pasadas
    if ($fecha === date('Y-m-d')) {
        $now = time();
        $startOfDay = max($startOfDay, ceil($now / 3600) * 3600); // Próxima hora en punto
    }

    // 4. Obtener citas existentes para ese barbero y fecha (excluyendo cita actual si es edición)
    $sqlCitas = "
        SELECT c.id, c.fecha_hora, c.servicio_id, 
               s.duracion_minutos 
        FROM citas c
        JOIN servicios s ON c.servicio_id = s.id
        WHERE c.barbero_id = ? 
          AND c.estado != 'cancelada'
          AND DATE(c.fecha_hora) = ?
    ";
    $paramsCitas = [$barberoId, $fecha];
    if ($excludeCitaId > 0) {
        $sqlCitas .= " AND c.id != ?";
        $paramsCitas[] = $excludeCitaId;
    }

    $stmtCitas = $pdo->prepare($sqlCitas);
    $stmtCitas->execute($paramsCitas);
    $citas = $stmtCitas->fetchAll();

    // Mapear intervalos ocupados
    $ocupados = [];
    foreach ($citas as $cita) {
        $inicio = strtotime($cita['fecha_hora']);
        $duracionCita = intval($cita['duracion_minutos']);
        $fin = $inicio + ($duracionCita * 60);
        $ocupados[] = ['inicio' => $inicio, 'fin' => $fin];
    }

    // Agregar bloqueos parciales a ocupados
    foreach ($bloqueosParciales as $bp) {
        $inicioBloqueo = strtotime("$fecha " . $bp['hora_inicio']);
        $finBloqueo = strtotime("$fecha " . $bp['hora_fin']);
        $ocupados[] = ['inicio' => $inicioBloqueo, 'fin' => $finBloqueo];
    }

    // 5. Generar slots disponibles (SOLO HORAS EN PUNTO O INTERVALOS REGULARES)
    $slots = [];
    $intervalo = 60 * 60; // Slots cada 60 minutos (Horas en punto)

    $current = $startOfDay;

    while (($current + ($duracion * 60)) <= $endOfDay) {
        $slotInicio = $current;
        $slotFin = $current + ($duracion * 60);

        $disponible = true;

        // Verificar colisión con citas o bloqueos
        foreach ($ocupados as $ocupado) {
            if ($slotInicio < $ocupado['fin'] && $slotFin > $ocupado['inicio']) {
                $disponible = false;
                break;
            }
        }

        if ($disponible) {
            $slots[] = date('H:i', $slotInicio);
        }

        $current += $intervalo;
    }

    if (empty($slots)) {
        // Buscar próximo día disponible en los siguientes 7 días
        $proximaFecha = null;
        for ($i = 1; $i <= 7; $i++) {
            $fechaTest = date('Y-m-d', strtotime("$fecha +$i days"));
            $diaSemanaTest = date('w', strtotime($fechaTest));

            $stmtHTest = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ? AND activo = 1");
            $stmtHTest->execute([$barberoId, $diaSemanaTest]);
            if (!$stmtHTest->fetch()) continue;

            $stmtBTest = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
            $stmtBTest->execute([$barberoId, $fechaTest]);
            $bTest = $stmtBTest->fetch();
            if ($bTest && $bTest['todo_el_dia']) continue;

            $proximaFecha = $fechaTest;
            break;
        }

        echo json_encode([
            'success' => true,
            'slots' => [],
            'mensaje' => 'No hay horarios disponibles para esta fecha.',
            'duracion_servicio' => $duracion,
            'sugerencia_proxima_fecha' => $proximaFecha,
            'sugerencia_legible' => $proximaFecha ? date('d/m/Y', strtotime($proximaFecha)) : null
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'duracion_servicio' => $duracion,
        'total_disponibles' => count($slots)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
