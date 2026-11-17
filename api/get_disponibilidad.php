<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$barberoId = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : 0;
$servicioId = isset($_GET['servicio_id']) ? intval($_GET['servicio_id']) : 0;
$sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 1;
$excludeCitaId = isset($_GET['exclude_cita_id']) ? intval($_GET['exclude_cita_id']) : 0;

try {
    $pdo = getConnection();

    // 1. Obtener duración del servicio
    $duracion = 60; // Default 60 min (1 hora completa)
    $nombreServicio = 'Servicio';
    $exclusiveBarberId = null;

    if ($servicioId > 0) {
        $stmtServicio = $pdo->prepare("SELECT * FROM servicios WHERE id = ?");
        $stmtServicio->execute([$servicioId]);
        $servicio = $stmtServicio->fetch(PDO::FETCH_ASSOC);
        if ($servicio) {
            $duracion = !empty($servicio['duracion_minutos']) ? intval($servicio['duracion_minutos']) : (!empty($servicio['duracion']) ? intval($servicio['duracion']) : 60);
            $nombreServicio = $servicio['nombre'] ?? 'Servicio';
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

    // Función auxiliar para calcular duración efectiva de un servicio
    // Servicios <= 30 min -> 30 min
    // Servicios de 31 a 60 min -> 60 min (hora redonda)
    // Servicios > 60 min -> múltiplos de 30 min (ej. 90m, 120m)
    function calcEffectiveDuration($rawMin) {
        $raw = intval($rawMin);
        if ($raw <= 0) return 60;
        if ($raw <= 30) return 30;
        if ($raw <= 60) return 60;
        return (int)(ceil($raw / 30) * 30);
    }

    // Función auxiliar para obtener slots de un barbero en una fecha dada
    // Forzando siempre horas enteras (:00) y medias horas (:30) consecutivas tras citas de 30 min
    function getBarberSlotsForDate($pdo, $bId, $fDate, $srvDuration, $excludeId = 0) {
        $ts = strtotime($fDate);
        $diaSem = date('w', $ts);

        // 1. Verificar si el barbero ya tiene citas agendadas en esta fecha (si tiene citas, claramente labora ese día)
        $stmtCitasCount = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE barbero_id = ? AND DATE(fecha_hora) = ? AND estado NOT IN ('cancelada', 'no_asistio')");
        $stmtCitasCount->execute([$bId, $fDate]);
        $hasCitasOnDate = intval($stmtCitasCount->fetchColumn() ?? 0) > 0;

        // 2. Horario base semanal
        $stmtHCheck = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?");
        $stmtHCheck->execute([$bId, $diaSem]);
        $hCheck = $stmtHCheck->fetch(PDO::FETCH_ASSOC);

        // Si el día está desactivado en la agenda semanal Y NO tiene citas confirmadas para hoy -> día libre
        if ($hCheck && intval($hCheck['activo']) === 0 && !$hasCitasOnDate) {
            return [];
        }

        // 3. Día bloqueado completo (solo si no tiene citas confirmadas agendadas)
        $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
        $stmtB->execute([$bId, $fDate]);
        $bloq = $stmtB->fetch(PDO::FETCH_ASSOC);
        if ($bloq && !empty($bloq['todo_el_dia']) && !$hasCitasOnDate) {
            return [];
        }

        // 4. Definir horario base (asegurando cobertura completa de la jornada)
        if ($hCheck && intval($hCheck['activo']) === 1) {
            $hBase = $hCheck;
            // Asegurar que la jornada cubra hasta las 20:00 de Lunes a Sábado
            if ($diaSem != 0 && strtotime("$fDate " . $hBase['hora_fin']) < strtotime("$fDate 20:00:00")) {
                $hBase['hora_fin'] = '20:00:00';
            }
            // Asegurar que comience a las 11:00 o antes si hay citas
            if ($hasCitasOnDate && strtotime("$fDate " . $hBase['hora_inicio']) > strtotime("$fDate 11:00:00")) {
                $hBase['hora_inicio'] = '11:00:00';
            }
        } else {
            // Horario predeterminado KORTZEN (11:00 a 20:00 Lun-Sáb, 10:00 a 17:00 Dom)
            $hBase = [
                'hora_inicio' => ($diaSem == 0) ? '10:00:00' : '11:00:00',
                'hora_fin' => ($diaSem == 0) ? '17:00:00' : '20:00:00',
                'activo' => 1
            ];
        }

        // Bloqueos parciales
        $stmtBH = $pdo->prepare("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
        $stmtBH->execute([$bId, $fDate]);
        $bParciales = $stmtBH->fetchAll(PDO::FETCH_ASSOC);

        // Almuerzo fijo (solo si el administrador lo configuró y activó deliberadamente)
        $stmtUser = $pdo->prepare("SELECT almuerzo_activo, almuerzo_inicio, almuerzo_fin FROM usuarios WHERE id = ?");
        $stmtUser->execute([$bId]);
        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($uData && intval($uData['almuerzo_activo'] ?? 0) === 1 && !empty($uData['almuerzo_inicio']) && !empty($uData['almuerzo_fin'])) {
            $bParciales[] = [
                'hora_inicio' => $uData['almuerzo_inicio'],
                'hora_fin' => $uData['almuerzo_fin']
            ];
        }

        $startOfDay = strtotime("$fDate " . $hBase['hora_inicio']);
        $endOfDay = strtotime("$fDate " . $hBase['hora_fin']);

        // Citas del día (con duración obtenida del servicio)
        // Excluir citas canceladas y no asistidas para liberar esos slots
        $sqlC = "SELECT c.fecha_hora, COALESCE(s.duracion_minutos, 60) as duracion_minutos 
                 FROM citas c 
                 LEFT JOIN servicios s ON c.servicio_id = s.id 
                 WHERE c.barbero_id = ? AND c.estado NOT IN ('cancelada', 'no_asistio') AND DATE(c.fecha_hora) = ?";
        $paramsC = [$bId, $fDate];
        if ($excludeId > 0) {
            $sqlC .= " AND c.id != ?";
            $paramsC[] = $excludeId;
        }
        $stmtC = $pdo->prepare($sqlC);
        $stmtC->execute($paramsC);
        $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Duración efectiva del servicio que se desea agendar
        $effSrvDuration = calcEffectiveDuration($srvDuration);

        // Calcular intervalos ocupados y puntos de continuidad (:30)
        $ocupados = [];
        $puntosContinuidad = [];

        foreach ($citas as $c) {
            $ini = strtotime($c['fecha_hora']);
            $rawDur = intval($c['duracion_minutos']) > 0 ? intval($c['duracion_minutos']) : 60;
            $effDur = calcEffectiveDuration($rawDur);
            $fin = $ini + ($effDur * 60);
            $ocupados[] = ['inicio' => $ini, 'fin' => $fin];

            // Si finaliza en minuto 30, es candidato para dar continuidad inmediata
            if (date('i', $fin) === '30') {
                $puntosContinuidad[] = $fin;
            }
        }

        foreach ($bParciales as $bp) {
            $iniB = strtotime("$fDate " . $bp['hora_inicio']);
            $finB = strtotime("$fDate " . $bp['hora_fin']);
            $ocupados[] = ['inicio' => $iniB, 'fin' => $finB];

            if (date('i', $finB) === '30') {
                $puntosContinuidad[] = $finB;
            }
        }

        // Generar candidatos de horarios:
        // 1. Horas enteras (:00) dentro del horario laboral
        $candidatos = [];
        $startHour = intval(date('H', $startOfDay));
        $endHour = intval(date('H', $endOfDay));

        for ($h = $startHour; $h <= $endHour; $h++) {
            $slotHourTs = strtotime(sprintf('%s %02d:00:00', $fDate, $h));
            if ($slotHourTs >= $startOfDay && $slotHourTs < $endOfDay) {
                $candidatos[] = $slotHourTs;
            }
        }

        // Si la jornada formalmente empieza a los 30 minutos (ej. 10:30), agregarla
        if (date('i', $startOfDay) === '30') {
            $candidatos[] = $startOfDay;
        }

        // 2. Horarios de media hora (:30) continuos inmediatos tras fin de servicio o almuerzo
        foreach ($puntosContinuidad as $pc) {
            if ($pc >= $startOfDay && $pc < $endOfDay) {
                $candidatos[] = $pc;
            }
        }

        // Eliminar duplicados y ordenar cronológicamente
        $candidatos = array_unique($candidatos);
        sort($candidatos);

        // Margen de anticipación mínima para hoy (+15 min)
        $nowMargin = ($fDate === date('Y-m-d')) ? time() + (15 * 60) : 0;
        $slots = [];

        foreach ($candidatos as $cur) {
            // Asegurar estrictamente que no existan minutos extraños (solo :00 o :30)
            $minuto = date('i', $cur);
            if ($minuto !== '00' && $minuto !== '30') {
                continue;
            }

            // Si es hoy, descartar turnos pasados o con menos de 15 minutos de anticipación
            if ($fDate === date('Y-m-d') && $cur < $nowMargin) {
                continue;
            }

            // El turno con su duración debe terminar dentro del horario laboral
            $sFin = $cur + ($effSrvDuration * 60);
            if ($sFin > $endOfDay) {
                continue;
            }

            // Comprobar colisión contra turnos y bloqueos ocupados
            $libre = true;
            foreach ($ocupados as $oc) {
                if ($cur < $oc['fin'] && $sFin > $oc['inicio']) {
                    $libre = false;
                    break;
                }
            }

            if ($libre) {
                $slots[] = date('H:i', $cur);
            }
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
