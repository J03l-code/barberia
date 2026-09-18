<?php
/**
 * KORTZEN - API de Agenda y Disponibilidad en Tiempo Real para Administradores
 * Retorna eventos de citas, estado de barberos y slots disponibles por día/semana
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();
$userRol = $currentUser['rol'] ?? '';

if (!in_array($userRol, ['admin', 'admin_local', 'administrador', 'superadmin'])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
    exit;
}

$pdo = getConnection();

// Parámetros
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));
$barberoId = intval($_GET['barbero_id'] ?? 0);
$filterSucursalId = intval($_GET['sucursal_id'] ?? 0);

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Scoping de sucursales según rol
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$allowedSucursalIds = [];

if ($userRol === 'admin_local') {
    if (!empty($userSucursalesIds)) {
        if ($filterSucursalId > 0 && in_array($filterSucursalId, $userSucursalesIds)) {
            $allowedSucursalIds = [$filterSucursalId];
        } else {
            $allowedSucursalIds = $userSucursalesIds;
        }
    } else {
        $allowedSucursalIds = [-1]; // Sin acceso
    }
} else {
    // Admin global
    if ($filterSucursalId > 0) {
        $allowedSucursalIds = [$filterSucursalId];
    }
}

try {
    // 1. Obtener Sucursales disponibles
    $sucursales = [];
    if ($userRol === 'admin_local') {
        $sucursales = getUsuarioSucursales($currentUser['id']);
    } else {
        $sucursales = query("SELECT id, nombre, direccion FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
    }

    // 2. Obtener Barberos
    $paramsBarberos = [];
    $whereBarberExtra = "";
    
    if (!empty($allowedSucursalIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($allowedSucursalIds), '?'));
        $whereBarberExtra .= " AND (u.sucursal_id IN ($inPlaceholders) OR EXISTS (SELECT 1 FROM usuarios_sucursales us WHERE us.usuario_id = u.id AND us.sucursal_id IN ($inPlaceholders)))";
        $paramsBarberos = array_merge($paramsBarberos, $allowedSucursalIds, $allowedSucursalIds);
    }
    
    if ($barberoId > 0) {
        $whereBarberExtra .= " AND u.id = ?";
        $paramsBarberos[] = $barberoId;
    }
    
    $whereBarberExtra .= " ORDER BY u.nombre ASC";

    $barberos = [];
    try {
        $sqlBarberos = "SELECT u.id, u.nombre, u.email, u.sucursal_id, COALESCE(u.foto_url, '') AS foto_url, u.almuerzo_inicio, u.almuerzo_fin, u.almuerzo_activo, s.nombre AS sucursal_nombre 
                        FROM usuarios u 
                        LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                        WHERE u.rol IN ('barbero', 'admin_local', 'admin') AND u.activo = 1" . $whereBarberExtra;
        $stmtB = $pdo->prepare($sqlBarberos);
        $stmtB->execute($paramsBarberos);
        $barberos = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eB) {
        $sqlBarberos = "SELECT u.id, u.nombre, u.email, u.sucursal_id, COALESCE(u.foto_url, '') AS foto_url, s.nombre AS sucursal_nombre 
                        FROM usuarios u 
                        LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                        WHERE u.rol IN ('barbero', 'admin_local', 'admin') AND u.activo = 1" . $whereBarberExtra;
        $stmtB = $pdo->prepare($sqlBarberos);
        $stmtB->execute($paramsBarberos);
        $barberos = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    }

    $barberosMap = [];
    $barberosIds = [];
    foreach ($barberos as $b) {
        $barberosMap[$b['id']] = $b;
        $barberosIds[] = intval($b['id']);
    }

    // 3. Obtener Citas en el rango de fechas
    $citas = [];
    if (!empty($barberosIds)) {
        $inBarbers = implode(',', $barberosIds);
        $sqlCitas = "
            SELECT 
                c.id,
                c.fecha_hora,
                c.estado,
                c.precio_final,
                COALESCE(c.propina, 0.00) AS propina,
                c.notas,
                c.cliente_id,
                c.barbero_id,
                c.sucursal_id,
                COALESCE(ref.codigo_usado, '') AS codigo_promocional,
                COALESCE(ref.descuento_aplicado, 0.00) AS descuento_aplicado,
                COALESCE(ref.puntos_otorgados, 0) AS puntos_ganados,
                COALESCE(cl.nombre, 'Cliente Directo') AS cliente_nombre,
                COALESCE(cl.telefono, '') AS cliente_telefono,
                COALESCE(cl.email, '') AS cliente_email,
                COALESCE(cl.foto_perfil, '') AS cliente_foto,
                COALESCE(s.nombre, 'Servicio General') AS servicio_nombre,
                COALESCE(s.duracion_minutos, 45) AS duracion_minutos,
                COALESCE(s.precio, 0.00) AS precio_servicio,
                u.nombre AS barbero_nombre,
                suc.nombre AS sucursal_nombre
            FROM citas c
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios u ON c.barbero_id = u.id
            LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
            LEFT JOIN referidos ref ON c.id = ref.cita_id
            WHERE c.barbero_id IN ($inBarbers)
              AND c.fecha_hora >= ?
              AND c.fecha_hora <= ?
              AND c.estado != 'cancelada'
            ORDER BY c.fecha_hora ASC
        ";

        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Obtener Bloqueos de Días y Horas en el rango
    $bloqueosDias = [];
    $bloqueosHoras = [];
    $horariosBase = [];

    if (!empty($barberosIds)) {
        $inBarbers = implode(',', $barberosIds);

        // Horarios base por día de semana
        $stmtHb = $pdo->query("SELECT barbero_id, dia_semana, hora_inicio, hora_fin, activo FROM horarios_barberos WHERE barbero_id IN ($inBarbers) AND activo = 1");
        while ($row = $stmtHb->fetch(PDO::FETCH_ASSOC)) {
            $horariosBase[$row['barbero_id']][$row['dia_semana']] = $row;
        }

        // Días bloqueados completos
        $stmtBd = $pdo->prepare("SELECT barbero_id, fecha, motivo, todo_el_dia FROM dias_bloqueados WHERE barbero_id IN ($inBarbers) AND fecha >= ? AND fecha <= ?");
        $stmtBd->execute([$startDate, $endDate]);
        while ($row = $stmtBd->fetch(PDO::FETCH_ASSOC)) {
            $bloqueosDias[$row['barbero_id']][$row['fecha']] = $row;
        }

        // Bloqueos de horas específicas
        $stmtBh = $pdo->prepare("SELECT barbero_id, fecha, hora_inicio, hora_fin, motivo FROM bloqueos_horas WHERE barbero_id IN ($inBarbers) AND fecha >= ? AND fecha <= ?");
        $stmtBh->execute([$startDate, $endDate]);
        while ($row = $stmtBh->fetch(PDO::FETCH_ASSOC)) {
            $bloqueosHoras[$row['barbero_id']][$row['fecha']][] = $row;
        }
    }

    // 5. Organizar Citas por Fecha y por Barbero
    $citasPorFecha = [];
    $citasPorBarberoFecha = [];
    $totalSemanaIngresos = 0.00;
    $totalCitasSemana = count($citas);

    foreach ($citas as $c) {
        $f = date('Y-m-d', strtotime($c['fecha_hora']));
        $bId = intval($c['barbero_id']);
        
        $citasPorFecha[$f][] = $c;
        $citasPorBarberoFecha[$bId][$f][] = $c;

        if ($c['estado'] === 'completada') {
            $monto = floatval($c['precio_final'] > 0 ? $c['precio_final'] : $c['precio_servicio']);
            $totalSemanaIngresos += $monto;
        }
    }

    // 6. Calcular Disponibilidad Gráfica Diaria por Barbero
    $disponibilidadPorFecha = [];
    $currentTs = strtotime($startDate);
    $endTs = strtotime($endDate);

    while ($currentTs <= $endTs) {
        $fechaLoop = date('Y-m-d', $currentTs);
        $diaSemana = intval(date('w', $currentTs)); // 0 (Dom) .. 6 (Sab)

        $disponibilidadPorFecha[$fechaLoop] = [
            'fecha' => $fechaLoop,
            'dia_semana' => $diaSemana,
            'dia_nombre' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$diaSemana],
            'barberos' => []
        ];

        foreach ($barberos as $b) {
            $bId = intval($b['id']);
            $hb = $horariosBase[$bId][$diaSemana] ?? null;
            $bloqueoDia = $bloqueosDias[$bId][$fechaLoop] ?? null;
            $bloqsHoras = $bloqueosHoras[$bId][$fechaLoop] ?? [];
            $citasBarberoDia = $citasPorBarberoFecha[$bId][$fechaLoop] ?? [];

            $isTrabaja = ($hb && intval($hb['activo']) === 1) && (!$bloqueoDia || !$bloqueoDia['todo_el_dia']);

            $slotsLibres = [];
            $intervalosOcupados = [];

            if ($isTrabaja) {
                $horaInicioStr = $hb['hora_inicio']; // ej 10:00:00
                $horaFinStr = $hb['hora_fin'];       // ej 20:00:00

                $startMin = strtotime("$fechaLoop $horaInicioStr");
                $endMin = strtotime("$fechaLoop $horaFinStr");

                // Registrar citas como ocupadas
                foreach ($citasBarberoDia as $cb) {
                    $cStart = strtotime($cb['fecha_hora']);
                    $cEnd = !empty($cb['hora_fin']) ? strtotime("$fechaLoop " . $cb['hora_fin']) : ($cStart + (intval($cb['duracion_minutos']) * 60));
                    $intervalosOcupados[] = [
                        'tipo' => 'cita',
                        'inicio' => $cStart,
                        'fin' => $cEnd,
                        'titulo' => $cb['cliente_nombre'] . ' - ' . $cb['servicio_nombre'],
                        'cita_id' => $cb['id'],
                        'estado' => $cb['estado']
                    ];
                }

                // Registrar Almuerzo si activo
                if (($b['almuerzo_activo'] ?? 1) == 1 && !empty($b['almuerzo_inicio']) && !empty($b['almuerzo_fin'])) {
                    $almStart = strtotime("$fechaLoop " . $b['almuerzo_inicio']);
                    $almEnd = strtotime("$fechaLoop " . $b['almuerzo_fin']);
                    $intervalosOcupados[] = [
                        'tipo' => 'almuerzo',
                        'inicio' => $almStart,
                        'fin' => $almEnd,
                        'titulo' => 'Almuerzo'
                    ];
                }

                // Registrar Bloqueos de horas
                foreach ($bloqsHoras as $blh) {
                    $blStart = strtotime("$fechaLoop " . $blh['hora_inicio']);
                    $blEnd = strtotime("$fechaLoop " . $blh['hora_fin']);
                    $intervalosOcupados[] = [
                        'tipo' => 'bloqueo',
                        'inicio' => $blStart,
                        'fin' => $blEnd,
                        'titulo' => $blh['motivo'] ?? 'Horario Bloqueado'
                    ];
                }

                // Generar slots libres cada 30 min
                $slotDuration = 30 * 60; // 30 min
                $nowTs = time();

                for ($t = $startMin; $t + $slotDuration <= $endMin; $t += $slotDuration) {
                    // Si la fecha es hoy y la hora ya pasó, no marcar disponible
                    if ($fechaLoop === date('Y-m-d') && $t < $nowTs) {
                        continue;
                    }

                    $slotStart = $t;
                    $slotEnd = $t + $slotDuration;
                    $isOccupied = false;

                    foreach ($intervalosOcupados as $occ) {
                        if ($slotStart < $occ['fin'] && $slotEnd > $occ['inicio']) {
                            $isOccupied = true;
                            break;
                        }
                    }

                    if (!$isOccupied) {
                        $slotsLibres[] = [
                            'hora_inicio' => date('H:i', $slotStart),
                            'hora_fin' => date('H:i', $slotEnd),
                            'label' => date('g:i A', $slotStart)
                        ];
                    }
                }
            }

            $disponibilidadPorFecha[$fechaLoop]['barberos'][] = [
                'barbero_id' => $bId,
                'barbero_nombre' => $b['nombre'],
                'foto_perfil' => $b['foto_perfil'] ?? '',
                'sucursal_nombre' => $b['sucursal_nombre'] ?? '',
                'labora' => $isTrabaja,
                'motivo_no_labora' => $bloqueoDia['motivo'] ?? (!$hb ? 'No labora este día' : ''),
                'hora_inicio' => $hb['hora_inicio'] ?? null,
                'hora_fin' => $hb['hora_fin'] ?? null,
                'total_citas' => count($citasBarberoDia),
                'total_slots_libres' => count($slotsLibres),
                'slots_libres' => $slotsLibres,
                'citas' => $citasBarberoDia
            ];
        }

        $currentTs += 86400; // Siguiente día
    }

    echo json_encode([
        'success' => true,
        'rango' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'mes_nombre' => [
                '1' => 'enero', '2' => 'febrero', '3' => 'marzo', '4' => 'abril',
                '5' => 'mayo', '6' => 'junio', '7' => 'julio', '8' => 'agosto',
                '9' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
            ][date('n', strtotime($startDate))] . ' ' . date('Y', strtotime($startDate))
        ],
        'metricas' => [
            'ingresos_semana' => number_format($totalSemanaIngresos, 2, '.', ''),
            'citas_semana' => $totalCitasSemana
        ],
        'sucursales' => $sucursales,
        'barberos' => $barberos,
        'citas' => $citas,
        'citas_por_fecha' => $citasPorFecha,
        'disponibilidad' => $disponibilidadPorFecha
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar agenda: ' . $e->getMessage()
    ]);
}
