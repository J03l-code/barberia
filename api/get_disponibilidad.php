<?php
require_once '../config.php';

header('Content-Type: application/json');

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$barberoId = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : 0;
$servicioId = isset($_GET['servicio_id']) ? intval($_GET['servicio_id']) : 0;
$sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 1;
$excludeCitaId = isset($_GET['exclude_cita_id']) ? intval($_GET['exclude_cita_id']) : 0;

try {
    $pdo = getConnection();

    // 1. Obtener duración del servicio
    $duracion = 40; // Default 40 min
    $nombreServicio = 'Servicio';
    $exclusiveBarberId = null;

    if ($servicioId > 0) {
        $stmtServicio = $pdo->prepare("SELECT duracion_minutos, nombre, barbero_id FROM servicios WHERE id = ?");
        $stmtServicio->execute([$servicioId]);
        $servicio = $stmtServicio->fetch();
        if ($servicio) {
            $duracion = intval($servicio['duracion_minutos']) > 0 ? intval($servicio['duracion_minutos']) : 40;
            $nombreServicio = $servicio['nombre'];
            $exclusiveBarberId = !empty($servicio['barbero_id']) ? intval($servicio['barbero_id']) : null;
        }
    }

    // Si el servicio tiene barbero exclusivo (ej. Corte con Mateo)
    if ($exclusiveBarberId && $barberoId == 0) {
        $barberoId = $exclusiveBarberId;
    }

    // 2. Obtener lista de barberos a evaluar
    if ($barberoId > 0) {
        $stmtBarbers = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE id = ? AND activo = 1");
        $stmtBarbers->execute([$barberoId]);
        $barberos = $stmtBarbers->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtBarbers = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE activo = 1 AND (rol = 'barbero' OR rol = 'admin_local') AND (sucursal_id = ? OR sucursal_id IS NULL OR sucursal_id = 0) ORDER BY id ASC");
        $stmtBarbers->execute([$sucursalId]);
        $barberos = $stmtBarbers->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($barberos)) {
        echo json_encode([
            'success' => true,
            'slots' => [],
            'slots_manana' => [],
            'slots_tarde' => [],
            'proxima_disponibilidad' => null,
            'mensaje' => 'No hay barberos disponibles para esta selección.'
        ]);
        exit;
    }

    // Función auxiliar para obtener slots de un barbero en una fecha dada
    function getBarberSlotsForDate($pdo, $bId, $fDate, $srvDuration, $excludeId = 0) {
        $ts = strtotime($fDate);
        $diaSem = date('w', $ts);

        // Horario base
        $stmtH = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ? AND activo = 1");
        $stmtH->execute([$bId, $diaSem]);
        $hBase = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$hBase) return [];

        // Día bloqueado completo
        $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
        $stmtB->execute([$bId, $fDate]);
        $bloq = $stmtB->fetch(PDO::FETCH_ASSOC);
        if ($bloq && !empty($bloq['todo_el_dia'])) return [];

        // Bloqueos parciales
        $stmtBH = $pdo->prepare("SELECT hora_inicio, hora_fin FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
        $stmtBH->execute([$bId, $fDate]);
        $bParciales = $stmtBH->fetchAll(PDO::FETCH_ASSOC);

        // Almuerzo fijo
        $stmtUser = $pdo->prepare("SELECT almuerzo_inicio, almuerzo_fin, almuerzo_activo FROM usuarios WHERE id = ?");
        $stmtUser->execute([$bId]);
        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($uData && ($uData['almuerzo_activo'] ?? 1) == 1 && !empty($uData['almuerzo_inicio']) && !empty($uData['almuerzo_fin'])) {
            $bParciales[] = [
                'hora_inicio' => $uData['almuerzo_inicio'],
                'hora_fin' => $uData['almuerzo_fin']
            ];
        }

        $startOfDay = strtotime("$fDate " . $hBase['hora_inicio']);
        $endOfDay = strtotime("$fDate " . $hBase['hora_fin']);

        // Si es hoy, filtrar horas pasadas (+30 min margen)
        if ($fDate === date('Y-m-d')) {
            $now = time() + (15 * 60);
            if ($now > $startOfDay) {
                // Redondear al siguiente múltiplo de 20 o 30 min
                $minutos = intval(date('i', $now));
                $bloque = ceil($minutos / 20) * 20;
                $startOfDay = strtotime(date('Y-m-d H:00:00', $now)) + ($bloque * 60);
            }
        }

        // Citas del día
        $sqlC = "SELECT fecha_hora, duracion_minutos FROM citas WHERE barbero_id = ? AND estado != 'cancelada' AND DATE(fecha_hora) = ?";
        $paramsC = [$bId, $fDate];
        if ($excludeId > 0) {
            $sqlC .= " AND id != ?";
            $paramsC[] = $excludeId;
        }
        $stmtC = $pdo->prepare($sqlC);
        $stmtC->execute($paramsC);
        $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $ocupados = [];
        foreach ($citas as $c) {
            $ini = strtotime($c['fecha_hora']);
            $dur = intval($c['duracion_minutos']) > 0 ? intval($c['duracion_minutos']) : 40;
            $ocupados[] = ['inicio' => $ini, 'fin' => $ini + ($dur * 60)];
        }
        foreach ($bParciales as $bp) {
            $iniB = strtotime("$fDate " . $bp['hora_inicio']);
            $finB = strtotime("$fDate " . $bp['hora_fin']);
            $ocupados[] = ['inicio' => $iniB, 'fin' => $finB];
        }

        $slots = [];
        $intervalo = 40 * 60; // Slots cada 40 minutos o duración de servicio
        if ($srvDuration >= 60) $intervalo = 60 * 60;
        elseif ($srvDuration <= 30) $intervalo = 30 * 60;

        $cur = $startOfDay;
        while (($cur + ($srvDuration * 60)) <= $endOfDay) {
            $sIni = $cur;
            $sFin = $cur + ($srvDuration * 60);

            $libre = true;
            foreach ($ocupados as $oc) {
                if ($sIni < $oc['fin'] && $sFin > $oc['inicio']) {
                    $libre = false;
                    break;
                }
            }

            if ($libre) {
                $slots[] = date('H:i', $sIni);
            }
            $cur += $intervalo;
        }

        return $slots;
    }

    // 3. Obtener slots para la fecha seleccionada
    $mergedSlots = [];
    $slotBarberMap = []; // slot => [barberId1, barberId2...]

    foreach ($barberos as $b) {
        $bSlots = getBarberSlotsForDate($pdo, $b['id'], $fecha, $duracion, $excludeCitaId);
        foreach ($bSlots as $s) {
            if (!in_array($s, $mergedSlots)) {
                $mergedSlots[] = $s;
            }
            $slotBarberMap[$s][] = $b['id'];
        }
    }
    sort($mergedSlots);

    // Separar en Mañana (< 13:00) y Tarde/Noche (>= 13:00)
    $slotsManana = [];
    $slotsTarde = [];
    foreach ($mergedSlots as $s) {
        $horaNum = intval(substr($s, 0, 2));
        if ($horaNum < 13) {
            $slotsManana[] = $s;
        } else {
            $slotsTarde[] = $s;
        }
    }

    // 4. Calcular "Próxima disponibilidad" (Earliest next slot)
    $proximaDisp = null;
    $diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $mesesNombres = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    // Buscar a partir de hoy durante los siguientes 14 días
    for ($d = 0; $d <= 14; $d++) {
        $testDate = date('Y-m-d', strtotime("+$d days"));
        $testSlots = [];
        $firstBarberFound = null;

        foreach ($barberos as $b) {
            $bS = getBarberSlotsForDate($pdo, $b['id'], $testDate, $duracion, $excludeCitaId);
            if (!empty($bS)) {
                if (empty($testSlots) || strtotime($bS[0]) < strtotime($testSlots[0])) {
                    $testSlots = $bS;
                    $firstBarberFound = $b;
                }
            }
        }

        if (!empty($testSlots)) {
            $earliestSlot = $testSlots[0];
            $testTs = strtotime($testDate);
            $diaSemTxt = $diasNombres[date('w', $testTs)];
            $diaNum = date('j', $testTs);
            $mesTxt = $mesesNombres[intval(date('n', $testTs))];

            $diaLabel = ($d === 0) ? "Hoy $earliestSlot" : (($d === 1) ? "Mañana $earliestSlot" : "$diaSemTxt $diaNum • $earliestSlot");

            $proximaDisp = [
                'fecha' => $testDate,
                'hora' => $earliestSlot,
                'label' => $diaLabel,
                'barbero_id' => $firstBarberFound ? $firstBarberFound['id'] : $barberos[0]['id'],
                'barbero_nombre' => $firstBarberFound ? $firstBarberFound['nombre'] : $barberos[0]['nombre']
            ];
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'slots' => $mergedSlots,
        'slots_manana' => $slotsManana,
        'slots_tarde' => $slotsTarde,
        'slot_barber_map' => $slotBarberMap,
        'proxima_disponibilidad' => $proximaDisp,
        'duracion_servicio' => $duracion,
        'total_disponibles' => count($mergedSlots)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
