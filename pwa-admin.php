<?php
/**
 * KORTZEN - PWA Integral Exclusiva para Administradores
 * 100% aislada de la plataforma web de escritorio.
 * Incluye: Agenda & Disponibilidad en Tiempo Real, Overview Completo con todos los KPIs, Agenda General de Hoy, Calendario de Ocupación Interactivo, Modal de Ganancias Netas Históricas, Citas, Equipo, Inventario, Servicios, Sucursales, Clientes, Reseñas y Galería.
 */

require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();
$userRol = $currentUser['rol'] ?? '';

if (!in_array($userRol, ['admin', 'admin_local', 'administrador', 'superadmin'])) {
    if ($userRol === 'barbero') {
        header('Location: barber-dashboard.php');
    } else {
        header('Location: cliente-dashboard.php');
    }
    exit;
}

$pdo = getConnection();

// Scoping de Sucursales
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$sucursalesList = [];
$filterSucursalId = intval($_GET['sucursal_id'] ?? 0);

if ($userRol === 'admin_local') {
    $sucursalesList = getUsuarioSucursales($currentUser['id']);
    if ($filterSucursalId > 0 && in_array($filterSucursalId, $userSucursalesIds)) {
        $scopedBranchIds = [$filterSucursalId];
    } else {
        $scopedBranchIds = !empty($userSucursalesIds) ? $userSucursalesIds : [-1];
        $filterSucursalId = 0;
    }
} else {
    try {
        $sucursalesList = query("SELECT id, nombre, direccion, telefono, horario_apertura, horario_cierre, activo FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
    } catch (Exception $e) {
        $sucursalesList = [];
    }
    if ($filterSucursalId > 0) {
        $scopedBranchIds = [$filterSucursalId];
    } else {
        $scopedBranchIds = [];
    }
}

// -------------------------------------------------------------
// METRICAS Y KPIS COMPLETOS (EXACTAMENTE IGUALES AL DASHBOARD WEB)
// -------------------------------------------------------------
$hoy = date('Y-m-d');
$mesInicio = date('Y-m-01');
$mesFin = date('Y-m-t');

if (!empty($scopedBranchIds)) {
    $idsStr = implode(',', array_map('intval', $scopedBranchIds));
    $whereCitasDirect = " AND sucursal_id IN ($idsStr)";
    $whereCitasAliased = " AND c.sucursal_id IN ($idsStr)";
    $whereProdDirect = " AND sucursal_id IN ($idsStr)";
    $whereProdAliased = " AND vp.sucursal_id IN ($idsStr)";
    $whereInvDirect = " AND sucursal_id IN ($idsStr)";
    $whereInvAliased = " AND i.sucursal_id IN ($idsStr)";
} else {
    $whereCitasDirect = "";
    $whereCitasAliased = "";
    $whereProdDirect = "";
    $whereProdAliased = "";
    $whereInvDirect = "";
    $whereInvAliased = "";
}

// 1. KPI: Ventas Citas + Productos Hoy
$citasHoyRow = query("SELECT 
    COUNT(*) as total_citas, 
    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN estado = 'completada' THEN precio_final ELSE 0 END) as venta_citas,
    SUM(CASE WHEN estado = 'completada' THEN propina ELSE 0 END) as propinas_hoy
FROM citas WHERE DATE(fecha_hora) = ?$whereCitasDirect", [$hoy])[0] ?? [];

$prodHoyRow = query("SELECT SUM(precio_unitario * cantidad) as total_prod, SUM(cantidad) as items_prod 
                     FROM ventas_productos WHERE DATE(fecha) = ?$whereProdDirect", [$hoy])[0] ?? [];

$vCitasHoy = floatval($citasHoyRow['venta_citas'] ?? 0);
$vProdHoy = floatval($prodHoyRow['total_prod'] ?? 0);
$ventaTotalHoy = $vCitasHoy + $vProdHoy;
$citasCompletadasHoy = intval($citasHoyRow['completadas'] ?? 0);
$itemsVendidosHoy = intval($prodHoyRow['items_prod'] ?? 0);

// 2. KPI: Recaudación Citas + Productos Mes
$citasMesRow = query("SELECT 
    COUNT(*) as total_citas, 
    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN estado = 'completada' THEN precio_final ELSE 0 END) as venta_citas,
    SUM(CASE WHEN estado = 'completada' THEN propina ELSE 0 END) as propinas_mes
FROM citas WHERE estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?$whereCitasDirect", [$mesInicio, $mesFin])[0] ?? [];

$prodMesRow = query("SELECT SUM(precio_unitario * cantidad) as total_prod 
                     FROM ventas_productos WHERE DATE(fecha) BETWEEN ? AND ?$whereProdDirect", [$mesInicio, $mesFin])[0] ?? [];

$vCitasMes = floatval($citasMesRow['venta_citas'] ?? 0);
$vProdMes = floatval($prodMesRow['total_prod'] ?? 0);
$recaudacionMesTotal = $vCitasMes + $vProdMes;
$citasCompletadasMes = intval($citasMesRow['completadas'] ?? 0);
$totalPropinasMes = floatval($citasMesRow['propinas_mes'] ?? 0);

// 3. Ganancias Netas Reales del Negocio
$netaServiciosMesRow = query("
    SELECT SUM(
        IFNULL(c.precio_final, 0) * (
            100.0 - CASE 
                WHEN c.barbero_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN (
                    CASE WHEN DAYOFWEEK(c.fecha_hora) IN (1, 7) THEN IFNULL(u.comision_fin_semana, IFNULL(u.comision_porcentaje, 50.0))
                    ELSE IFNULL(u.comision_porcentaje, 50.0) END
                )
                ELSE 0.0
            END
        ) / 100.0
    ) as total_neto_servicios
    FROM citas c
    LEFT JOIN usuarios u ON c.barbero_id = u.id
    WHERE c.estado = 'completada' AND DATE(c.fecha_hora) BETWEEN ? AND ?$whereCitasAliased
", [$mesInicio, $mesFin])[0] ?? [];

$netaProdMesRow = query("
    SELECT SUM(
        (IFNULL(vp.cantidad, 1) * IFNULL(vp.precio_unitario, 0)) * (
            100.0 - CASE 
                WHEN vp.usuario_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN IFNULL(u.comision_productos, 10.0)
                ELSE 0.0
            END
        ) / 100.0
    ) as total_neto_productos
    FROM ventas_productos vp
    LEFT JOIN usuarios u ON vp.usuario_id = u.id
    WHERE DATE(vp.fecha) BETWEEN ? AND ?$whereProdAliased
", [$mesInicio, $mesFin])[0] ?? [];

$gananciaNetaMesServicios = floatval($netaServiciosMesRow['total_neto_servicios'] ?? 0);
$gananciaNetaMesProd = floatval($netaProdMesRow['total_neto_productos'] ?? 0);
$gananciaNetaMesTotal = $gananciaNetaMesServicios + $gananciaNetaMesProd;

// Ganancias Netas Hoy
$netaServiciosHoyRow = query("
    SELECT SUM(
        IFNULL(c.precio_final, 0) * (
            100.0 - CASE 
                WHEN c.barbero_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN (
                    CASE WHEN DAYOFWEEK(c.fecha_hora) IN (1, 7) THEN IFNULL(u.comision_fin_semana, IFNULL(u.comision_porcentaje, 50.0))
                    ELSE IFNULL(u.comision_porcentaje, 50.0) END
                )
                ELSE 0.0
            END
        ) / 100.0
    ) as total_neto_servicios
    FROM citas c
    LEFT JOIN usuarios u ON c.barbero_id = u.id
    WHERE c.estado = 'completada' AND DATE(c.fecha_hora) = ?$whereCitasAliased
", [$hoy])[0] ?? [];

$netaProdHoyRow = query("
    SELECT SUM(
        (IFNULL(vp.cantidad, 1) * IFNULL(vp.precio_unitario, 0)) * (
            100.0 - CASE 
                WHEN vp.usuario_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN IFNULL(u.comision_productos, 10.0)
                ELSE 0.0
            END
        ) / 100.0
    ) as total_neto_productos
    FROM ventas_productos vp
    LEFT JOIN usuarios u ON vp.usuario_id = u.id
    WHERE DATE(vp.fecha) = ?$whereProdAliased
", [$hoy])[0] ?? [];

$gananciaNetaHoyServicios = floatval($netaServiciosHoyRow['total_neto_servicios'] ?? 0);
$gananciaNetaHoyProd = floatval($netaProdHoyRow['total_neto_productos'] ?? 0);
$gananciaNetaHoyTotal = $gananciaNetaHoyServicios + $gananciaNetaHoyProd;

// Historial Mensual Completo para el Modal
$netaHistServiciosRows = query("
    SELECT 
        DATE_FORMAT(c.fecha_hora, '%Y-%m') as mes_key,
        COUNT(c.id) as total_citas,
        SUM(IFNULL(c.precio_final, 0)) as bruto_servicios,
        SUM(
            IFNULL(c.precio_final, 0) * (
                CASE 
                    WHEN c.barbero_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN (
                        CASE WHEN DAYOFWEEK(c.fecha_hora) IN (1, 7) THEN IFNULL(u.comision_fin_semana, IFNULL(u.comision_porcentaje, 50.0))
                        ELSE IFNULL(u.comision_porcentaje, 50.0) END
                    )
                    ELSE 0.0
                END
            ) / 100.0
        ) as comision_barberos_servicios,
        SUM(
            IFNULL(c.precio_final, 0) * (
                100.0 - CASE 
                    WHEN c.barbero_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN (
                        CASE WHEN DAYOFWEEK(c.fecha_hora) IN (1, 7) THEN IFNULL(u.comision_fin_semana, IFNULL(u.comision_porcentaje, 50.0))
                        ELSE IFNULL(u.comision_porcentaje, 50.0) END
                    )
                    ELSE 0.0
                END
            ) / 100.0
        ) as neto_negocio_servicios
    FROM citas c
    LEFT JOIN usuarios u ON c.barbero_id = u.id
    WHERE c.estado = 'completada' $whereCitasAliased
    GROUP BY DATE_FORMAT(c.fecha_hora, '%Y-%m')
    ORDER BY mes_key DESC
");

$netaHistProdRows = query("
    SELECT 
        DATE_FORMAT(vp.fecha, '%Y-%m') as mes_key,
        COUNT(vp.id) as total_ventas,
        SUM(IFNULL(vp.cantidad, 1) * IFNULL(vp.precio_unitario, 0)) as bruto_productos,
        SUM(
            (IFNULL(vp.cantidad, 1) * IFNULL(vp.precio_unitario, 0)) * (
                CASE 
                    WHEN vp.usuario_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN IFNULL(u.comision_productos, 10.0)
                    ELSE 0.0
                END
            ) / 100.0
        ) as comision_barberos_productos,
        SUM(
            (IFNULL(vp.cantidad, 1) * IFNULL(vp.precio_unitario, 0)) * (
                100.0 - CASE 
                    WHEN vp.usuario_id IS NOT NULL AND (u.rol = 'barbero' OR u.rol IS NULL) THEN IFNULL(u.comision_productos, 10.0)
                    ELSE 0.0
                END
            ) / 100.0
        ) as neto_negocio_productos
    FROM ventas_productos vp
    LEFT JOIN usuarios u ON vp.usuario_id = u.id
    WHERE 1=1 $whereProdAliased
    GROUP BY DATE_FORMAT(vp.fecha, '%Y-%m')
    ORDER BY mes_key DESC
");

$nombresMesesEsp = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$mesesHistoricosMap = [];

foreach ($netaHistServiciosRows as $sRow) {
    $mKey = $sRow['mes_key'];
    if (!isset($mesesHistoricosMap[$mKey])) {
        $partes = explode('-', $mKey);
        $anio = $partes[0] ?? date('Y');
        $mesNum = $partes[1] ?? '01';
        $mesTxt = ($nombresMesesEsp[$mesNum] ?? $mesNum) . ' ' . $anio;
        $mesesHistoricosMap[$mKey] = [
            'mes_key' => $mKey,
            'mes_nombre' => $mesTxt,
            'citas_completadas' => 0,
            'ventas_productos' => 0,
            'bruto_servicios' => 0.0,
            'bruto_productos' => 0.0,
            'comision_servicios' => 0.0,
            'comision_productos' => 0.0,
            'neto_servicios' => 0.0,
            'neto_productos' => 0.0
        ];
    }
    $mesesHistoricosMap[$mKey]['citas_completadas'] = intval($sRow['total_citas']);
    $mesesHistoricosMap[$mKey]['bruto_servicios'] = floatval($sRow['bruto_servicios']);
    $mesesHistoricosMap[$mKey]['comision_servicios'] = floatval($sRow['comision_barberos_servicios']);
    $mesesHistoricosMap[$mKey]['neto_servicios'] = floatval($sRow['neto_negocio_servicios']);
}

foreach ($netaHistProdRows as $pRow) {
    $mKey = $pRow['mes_key'];
    if (!isset($mesesHistoricosMap[$mKey])) {
        $partes = explode('-', $mKey);
        $anio = $partes[0] ?? date('Y');
        $mesNum = $partes[1] ?? '01';
        $mesTxt = ($nombresMesesEsp[$mesNum] ?? $mesNum) . ' ' . $anio;
        $mesesHistoricosMap[$mKey] = [
            'mes_key' => $mKey,
            'mes_nombre' => $mesTxt,
            'citas_completadas' => 0,
            'ventas_productos' => 0,
            'bruto_servicios' => 0.0,
            'bruto_productos' => 0.0,
            'comision_servicios' => 0.0,
            'comision_productos' => 0.0,
            'neto_servicios' => 0.0,
            'neto_productos' => 0.0
        ];
    }
    $mesesHistoricosMap[$mKey]['ventas_productos'] = intval($pRow['total_ventas']);
    $mesesHistoricosMap[$mKey]['bruto_productos'] = floatval($pRow['bruto_productos']);
    $mesesHistoricosMap[$mKey]['comision_productos'] = floatval($pRow['comision_barberos_productos']);
    $mesesHistoricosMap[$mKey]['neto_productos'] = floatval($pRow['neto_negocio_productos']);
}

krsort($mesesHistoricosMap);

$histTotalBruto = 0.0;
$histTotalComisiones = 0.0;
$histTotalNetoNegocio = 0.0;
$histTotalCitas = 0;
$histTotalVentasProd = 0;

foreach ($mesesHistoricosMap as $mKey => $mData) {
    $brutoTotal = $mData['bruto_servicios'] + $mData['bruto_productos'];
    $comisionTotal = $mData['comision_servicios'] + $mData['comision_productos'];
    $netoTotal = $mData['neto_servicios'] + $mData['neto_productos'];

    $histTotalBruto += $brutoTotal;
    $histTotalComisiones += $comisionTotal;
    $histTotalNetoNegocio += $netoTotal;
    $histTotalCitas += $mData['citas_completadas'];
    $histTotalVentasProd += $mData['ventas_productos'];
}

$margenHistoricoNegocio = $histTotalBruto > 0 ? (($histTotalNetoNegocio / $histTotalBruto) * 100) : 100;

// Ticket Promedio
$ticketPromedioMes = $citasCompletadasMes > 0 ? ($vCitasMes / $citasCompletadasMes) : 0;

// Stock Global
$invStats = query("SELECT SUM(cantidad * precio) as total_valor, SUM(cantidad) as total_items FROM inventario WHERE 1=1 $whereInvDirect")[0] ?? [];
$valorInventario = floatval($invStats['total_valor'] ?? 0);
$totalItems = floatval($invStats['total_items'] ?? 0);

// Top Barberos
$topBarberos = query("SELECT u.id, u.nombre, s.nombre as sucursal, COUNT(c.id) as citas, SUM(c.precio_final) as total
                      FROM usuarios u
                      JOIN citas c ON u.id = c.barbero_id
                      LEFT JOIN sucursales s ON c.sucursal_id = s.id
                      WHERE c.estado = 'completada' AND DATE(c.fecha_hora) BETWEEN ? AND ? $whereCitasAliased
                      GROUP BY u.id ORDER BY total DESC LIMIT 5", [$mesInicio, $mesFin]);

// Inventario Bajo
$lowStock = query("SELECT i.producto, i.cantidad, i.stock_minimo, s.nombre as sucursal 
                   FROM inventario i
                   JOIN sucursales s ON i.sucursal_id = s.id
                   WHERE i.cantidad <= i.stock_minimo $whereInvAliased
                   ORDER BY i.cantidad ASC LIMIT 5");

// Agenda General de Hoy
$agendaGlobal = query("SELECT c.*, u.nombre as barbero, s.nombre as servicio, suc.nombre as sucursal_nombre, cli.nombre as cliente, cli.telefono, cli.id as cliente_id 
                       FROM citas c
                       JOIN usuarios u ON c.barbero_id = u.id
                       JOIN servicios s ON c.servicio_id = s.id
                       JOIN sucursales suc ON c.sucursal_id = suc.id
                       JOIN clientes cli ON c.cliente_id = cli.id
                       WHERE DATE(c.fecha_hora) = ? $whereCitasAliased
                       ORDER BY c.fecha_hora ASC", [$hoy]);

// Fidelización
$totalHoyCitas = count($agendaGlobal);
$recurrentes = 0;
foreach ($agendaGlobal as $c) {
    $historial = query("SELECT COUNT(*) as n FROM citas WHERE cliente_id = ? AND estado = 'completada'", [$c['cliente_id']])[0]['n'] ?? 0;
    if ($historial > 1) $recurrentes++;
}
$retentionRate = $totalHoyCitas > 0 ? round(($recurrentes / $totalHoyCitas) * 100) : 0;

// Gráfica 7 Días
$last7Days = [];
$diasEsp = ['Sun' => 'Dom', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mie', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sab'];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $tCitas = query("SELECT SUM(precio_final) as t FROM citas WHERE estado = 'completada' AND DATE(fecha_hora) = ? $whereCitasDirect", [$d])[0]['t'] ?? 0;
    $tProd = query("SELECT SUM(precio_unitario * cantidad) as t FROM ventas_productos WHERE DATE(fecha) = ? $whereProdDirect", [$d])[0]['t'] ?? 0;
    $dayName = date('D', strtotime($d));
    $last7Days[] = ['date' => $diasEsp[$dayName], 'val' => floatval($tCitas + $tProd)];
}
$maxVal = max(array_column($last7Days, 'val'));
$maxVal = $maxVal > 0 ? $maxVal : 1;

// Descuentos por Referidos
$refMesRow = query("
    SELECT 
        COUNT(r.id) as total_referidos,
        SUM(IFNULL(r.descuento_aplicado, 0)) as total_descuento,
        SUM(IFNULL(r.puntos_otorgados, 0)) as total_puntos
    FROM referidos r
    LEFT JOIN citas c ON r.cita_id = c.id
    WHERE r.estado != 'cancelado' 
      AND (
          (c.fecha_hora IS NOT NULL AND DATE(c.fecha_hora) BETWEEN ? AND ?)
          OR (c.fecha_hora IS NULL AND DATE(r.fecha_creacion) BETWEEN ? AND ?)
      ) $whereCitasAliased
", [$mesInicio, $mesFin, $mesInicio, $mesFin])[0] ?? [];

$totalDescuentosReferidosMes = floatval($refMesRow['total_descuento'] ?? 0);
$totalUsosReferidosMes = intval($refMesRow['total_referidos'] ?? 0);

$refHistRow = query("
    SELECT 
        COUNT(r.id) as total_referidos_hist,
        SUM(IFNULL(r.descuento_aplicado, 0)) as total_descuento_hist
    FROM referidos r
    LEFT JOIN citas c ON r.cita_id = c.id
    WHERE r.estado != 'cancelado' $whereCitasAliased
")[0] ?? [];

$totalDescuentosReferidosHist = floatval($refHistRow['total_descuento_hist'] ?? 0);

$meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
$mesActual = $meses[date('F')] ?? date('F');

// Calendario de Ocupación
$calMonth = date('m');
$calYear = date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$calMonth, (int)$calYear);
$firstDayOfMonth = date('N', strtotime("$calYear-$calMonth-01")); // 1 (Mon) to 7 (Sun)

$bookings = query("SELECT DATE(fecha_hora) as d, COUNT(*) as c FROM citas WHERE MONTH(fecha_hora) = ? AND YEAR(fecha_hora) = ? $whereCitasDirect GROUP BY d", [$calMonth, $calYear]);

$bookingsMap = [];
foreach ($bookings as $b) {
    $dayNum = (int) date('d', strtotime($b['d']));
    $bookingsMap[$dayNum] = $b['c'];
}

// -------------------------------------------------------------
// LISTADOS PARA MODULOS
// -------------------------------------------------------------
// 1. Barberos
$barberosList = [];
try {
    if ($userRol === 'admin_local' && !empty($scopedBranchIds)) {
        $inList = implode(',', array_fill(0, count($scopedBranchIds), '?'));
        $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, u.comision_porcentaje, u.comision_fin_semana, u.comision_productos, s.nombre AS sucursal_nombre 
                                FROM usuarios u 
                                LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                WHERE u.rol IN ('barbero', 'admin_local', 'admin') AND u.activo = 1
                                  AND (u.sucursal_id IN ($inList) OR EXISTS (SELECT 1 FROM usuarios_sucursales us WHERE us.usuario_id = u.id AND us.sucursal_id IN ($inList)))
                                ORDER BY u.nombre ASC");
        $stmt->execute(array_merge($scopedBranchIds, $scopedBranchIds));
        $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if ($filterSucursalId > 0) {
            $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, u.comision_porcentaje, u.comision_fin_semana, u.comision_productos, s.nombre AS sucursal_nombre 
                                    FROM usuarios u 
                                    LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                    WHERE u.rol IN ('barbero', 'admin_local', 'admin') AND u.activo = 1 AND u.sucursal_id = ?
                                    ORDER BY u.nombre ASC");
            $stmt->execute([$filterSucursalId]);
            $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, u.comision_porcentaje, u.comision_fin_semana, u.comision_productos, s.nombre AS sucursal_nombre 
                                 FROM usuarios u 
                                 LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                 WHERE u.rol IN ('barbero', 'admin_local', 'admin') AND u.activo = 1
                                 ORDER BY u.nombre ASC");
            $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $barberosList = [];
}

// 2. Servicios
$serviciosList = [];
try {
    $serviciosList = query("SELECT s.*, cs.nombre as categoria_nombre FROM servicios s LEFT JOIN categorias_servicios cs ON s.categoria = cs.nombre WHERE s.activo = 1 ORDER BY s.categoria ASC, s.nombre ASC");
} catch (Exception $e) {
    $serviciosList = [];
}

// 3. Inventario
$inventarioList = [];
try {
    $sqlInv = "SELECT i.*, s.nombre as sucursal_nombre FROM inventario i LEFT JOIN sucursales s ON i.sucursal_id = s.id WHERE 1=1";
    if (!empty($scopedBranchIds)) {
        $inB = implode(',', array_map('intval', $scopedBranchIds));
        $sqlInv .= " AND (i.sucursal_id IN ($inB) OR i.sucursal_id IS NULL)";
    }
    $sqlInv .= " ORDER BY i.producto ASC";
    $inventarioList = query($sqlInv);
} catch (Exception $e) {
    $inventarioList = [];
}

// 4. Clientes
$clientesList = [];
try {
    $clientesList = query("SELECT c.*, (SELECT COUNT(*) FROM citas WHERE cliente_id = c.id) as total_citas FROM clientes c WHERE c.activo = 1 ORDER BY c.nombre ASC LIMIT 100");
} catch (Exception $e) {
    $clientesList = [];
}

// 5. Reseñas
$resenasList = [];
try {
    $resenasList = query("SELECT r.*, c.nombre as cliente_nombre FROM resenas r LEFT JOIN clientes c ON r.cliente_id = c.id ORDER BY r.fecha_creacion DESC LIMIT 50");
} catch (Exception $e) {
    $resenasList = [];
}

// 6. Galería
$galeriaList = [];
try {
    $galeriaList = query("SELECT * FROM galeria ORDER BY fecha_subida DESC LIMIT 40");
} catch (Exception $e) {
    $galeriaList = [];
}

$activeTab = $_GET['tab'] ?? 'agenda';
$nombreAdmin = $currentUser['nombre'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>KORTZEN Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-pwa: #FAF9F6;
            --bg-card: #FFFFFF;
            --border-pwa: #EAEAEA;
            --text-dark: #111111;
            --text-gray: #666666;
            --text-light: #999999;
            --gold-pwa: #C0A062;
            --green-pwa: #10B981;
            --red-pwa: #EF4444;
            --orange-pwa: #F59E0B;
            --shadow-pwa: 0 4px 20px rgba(0,0,0,0.04);
            --safe-bottom: env(safe-area-inset-bottom, 16px);
            --safe-top: env(safe-area-inset-top, 12px);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--bg-pwa);
            color: var(--text-dark);
            padding-bottom: calc(85px + var(--safe-bottom));
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .pwa-app-shell {
            max-width: 600px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg-card);
            position: relative;
            box-shadow: 0 0 30px rgba(0,0,0,0.04);
        }

        /* Top Header */
        .pwa-top-bar {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: calc(var(--safe-top) + 8px) 16px 12px 16px;
            border-bottom: 1px solid var(--border-pwa);
            z-index: 90;
        }

        .pwa-icon-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            transition: background 0.15s;
        }

        .pwa-icon-btn:active {
            background: rgba(0,0,0,0.05);
        }

        .pwa-title-box {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-dark);
            cursor: pointer;
        }

        .pwa-top-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pwa-avatar-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #111111;
            color: #FFFFFF;
            font-weight: 800;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Date Strip */
        .pwa-date-strip-container {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-pwa);
            padding: 12px 16px 14px 16px;
        }

        .pwa-date-strip-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .pwa-branch-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F4F4F4;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #444444;
        }

        .pwa-strip-nav {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pwa-nav-btn {
            background: #F4F4F4;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #222;
        }

        .pwa-date-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }

        .pwa-day-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            padding: 4px 0;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .pwa-day-letter {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
        }

        .pwa-day-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-dark);
            position: relative;
        }

        .pwa-day-col.is-selected .pwa-day-num {
            background: #111111 !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .pwa-day-col .pwa-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--gold-pwa);
            position: absolute;
            bottom: 3px;
        }

        .pwa-day-col.is-selected .pwa-dot {
            background: #FFFFFF;
        }

        /* Barber Chips */
        .pwa-barber-chips {
            padding: 10px 16px;
            background: #FAFAFA;
            border-bottom: 1px solid var(--border-pwa);
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .pwa-barber-chips::-webkit-scrollbar {
            display: none;
        }

        .pwa-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            border: 1px solid var(--border-pwa);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s;
        }

        .pwa-chip.active {
            background: #111111;
            color: #FFFFFF;
            border-color: #111111;
        }

        .pwa-chip-avatar {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #DDDDDD;
            font-size: 9px;
            line-height: 18px;
            text-align: center;
            color: #111;
            font-weight: 800;
        }

        .pwa-chip.active .pwa-chip-avatar {
            background: #FFFFFF;
            color: #111;
        }

        /* Views / Tabs */
        .pwa-view-panel {
            display: none;
            padding: 16px;
            animation: fadeIn 0.2s ease-in-out;
        }

        .pwa-view-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Agenda Feed & Cards */
        .pwa-day-group {
            margin-bottom: 24px;
        }

        .pwa-day-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pwa-avail-badge {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--green-pwa);
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 3px 8px;
            border-radius: 12px;
            cursor: pointer;
        }

        .pwa-day-empty {
            color: var(--text-light);
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 0 12px 0;
        }

        .pwa-cita-card {
            background: #FFF5F2;
            border-left: 4px solid #E05638;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .pwa-cita-card.status-completada {
            background: #F0FDF4;
            border-left-color: #10B981;
        }

        .pwa-cita-card.status-pendiente {
            background: #FFFBEB;
            border-left-color: #F59E0B;
        }

        .pwa-cita-card:active {
            transform: scale(0.99);
        }

        .pwa-cita-header {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }

        .pwa-cita-client {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .pwa-cita-service {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-gray);
            text-transform: uppercase;
        }

        .pwa-cita-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: #555555;
        }

        .pwa-barber-tag {
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(0,0,0,0.06);
            padding: 2px 7px;
            border-radius: 4px;
            color: #333333;
        }

        .pwa-live-time {
            display: flex;
            align-items: center;
            margin: 14px 0;
        }

        .pwa-live-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #111111;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .pwa-live-line {
            flex: 1;
            height: 1.5px;
            background: #111111;
        }

        .pwa-avail-box {
            background: #F9FAFB;
            border: 1px dashed #D1D5DB;
            border-radius: 10px;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 14px;
            display: none;
        }

        .pwa-avail-box.open {
            display: block;
        }

        .pwa-slot-pill {
            background: #FFFFFF;
            border: 1.5px solid #10B981;
            color: #065F46;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            margin: 3px;
        }

        .pwa-slot-pill:active {
            background: #10B981;
            color: #FFFFFF;
        }

        /* KPI Grids */
        .pwa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .pwa-kpi-card {
            background: #FFFFFF;
            border: 1.5px solid var(--border-pwa);
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow-pwa);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pwa-kpi-title {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-gray);
            margin-bottom: 4px;
        }

        .pwa-kpi-value {
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--text-dark);
        }

        .pwa-kpi-sub {
            font-size: 0.72rem;
            color: var(--text-light);
            margin-top: 4px;
            font-weight: 600;
        }

        .pwa-item-card {
            background: #FFFFFF;
            border: 1.5px solid var(--border-pwa);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-pwa);
        }

        /* Calendario de Ocupación */
        .pwa-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-top: 12px;
        }

        .pwa-cal-head {
            text-align: center;
            font-size: 0.68rem;
            font-weight: 800;
            color: #888;
            text-transform: uppercase;
            padding-bottom: 4px;
        }

        .pwa-cal-cell {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            min-height: 52px;
            padding: 6px 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.15s;
        }

        .pwa-cal-cell:active, .pwa-cal-cell:hover {
            border-color: #111;
            background: #FFF;
        }

        .pwa-cal-cell.is-today {
            border: 1.5px solid var(--gold-pwa);
            background: #FFFDF8;
        }

        .pwa-cal-cell.has-citas {
            background: #F0FDF4;
            border-color: #86EFAC;
        }

        .pwa-cal-num {
            font-size: 0.82rem;
            font-weight: 800;
            color: #111;
        }

        .pwa-cal-badge {
            font-size: 0.65rem;
            font-weight: 800;
            color: #047857;
            background: #DCFCE7;
            padding: 1px 4px;
            border-radius: 4px;
            white-space: nowrap;
        }

        /* Floating Action Button (+) */
        .pwa-fab {
            position: fixed;
            bottom: calc(85px + var(--safe-bottom));
            right: 20px;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #111111;
            color: #FFFFFF;
            border: none;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            z-index: 50;
            transition: transform 0.15s;
        }

        .pwa-fab:active {
            transform: scale(0.93);
        }

        /* Bottom Summary Pill */
        .pwa-summary-pill {
            position: fixed;
            bottom: calc(65px + var(--safe-bottom));
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 550px;
            background: #FFFFFF;
            border: 1px solid var(--border-pwa);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-pwa);
            z-index: 35;
        }

        /* Bottom Tab Bar */
        .pwa-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid var(--border-pwa);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 4px calc(var(--safe-bottom) + 4px) 4px;
            z-index: 80;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.04);
        }

        .pwa-tab-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 0.68rem;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            transition: color 0.15s;
        }

        .pwa-tab-btn.active {
            color: var(--text-dark);
            font-weight: 800;
        }

        .pwa-tab-icon {
            font-size: 1.15rem;
        }

        /* Drawer / Menú Lateral */
        .pwa-drawer-mask {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s, visibility 0.25s;
        }

        .pwa-drawer-mask.open {
            opacity: 1;
            visibility: visible;
        }

        .pwa-drawer-content {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 82%;
            max-width: 340px;
            background: #FFFFFF;
            z-index: 101;
            transform: translateX(-100%);
            transition: transform 0.25s ease-out;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            padding-top: var(--safe-top);
        }

        .pwa-drawer-content.open {
            transform: translateX(0);
        }

        .pwa-drawer-promo {
            background: #E8F5E9;
            margin: 16px;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
        }

        .pwa-drawer-promo h3 {
            font-size: 0.95rem;
            font-weight: 800;
            color: #111111;
            margin-bottom: 4px;
        }

        .pwa-drawer-promo p {
            font-size: 0.75rem;
            color: #4B5563;
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .pwa-drawer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
            border-bottom: 1px solid #FAFAFA;
        }

        .pwa-drawer-item:active {
            background: #F4F4F4;
        }

        /* Action Sheets / Modals */
        .pwa-action-sheet {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 120;
            display: none;
            align-items: flex-end;
            justify-content: center;
        }

        .pwa-sheet-box {
            background: #FFFFFF;
            width: 100%;
            max-width: 600px;
            border-radius: 20px 20px 0 0;
            padding: 20px 20px calc(var(--safe-bottom) + 20px) 20px;
            animation: slideUp 0.2s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .pwa-btn-main {
            background: #111111;
            color: #FFFFFF;
            padding: 13px;
            border-radius: 12px;
            border: none;
            font-size: 0.92rem;
            font-weight: 800;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            width: 100%;
        }

        .pwa-btn-secondary {
            background: #F4F4F4;
            color: #111111;
            padding: 10px 14px;
            border-radius: 8px;
            border: none;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="pwa-app-shell">
    <!-- Top Header -->
    <header class="pwa-top-bar">
        <button class="pwa-icon-btn" onclick="abrirDrawer()" aria-label="Menú">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div class="pwa-title-box" id="pwaTopTitle" onclick="abrirDrawer()">
            <span id="txtMes"><?php echo strtolower($mesActual); ?></span> <span id="txtAnio"><?php echo date('Y'); ?></span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>

        <div class="pwa-top-right">
            <button class="pwa-icon-btn" onclick="cambiarVistaPwa('citas')" aria-label="Notificaciones">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
            <div class="pwa-avatar-badge" onclick="cambiarVistaPwa('equipo')">
                <?php echo strtoupper(substr($nombreAdmin, 0, 2)); ?>
            </div>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- VIEW 1: AGENDA & DISPONIBILIDAD EN TIEMPO REAL -->
    <!-- ========================================================================= -->
    <section id="viewAgenda" class="pwa-view-panel <?php echo $activeTab === 'agenda' ? 'active' : ''; ?>">
        <!-- Date Strip -->
        <div class="pwa-date-strip-container" style="margin: -16px -16px 14px -16px;">
            <div class="pwa-date-strip-header">
                <div class="pwa-branch-pill">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>
                        <?php 
                        if ($filterSucursalId > 0) {
                            foreach ($sucursalesList as $s) {
                                if ($s['id'] == $filterSucursalId) { echo htmlspecialchars($s['nombre']); break; }
                            }
                        } else {
                            echo 'Todas las Sucursales';
                        }
                        ?>
                    </span>
                </div>
                <div class="pwa-strip-nav">
                    <button class="pwa-nav-btn" onclick="cambiarSemanaAgenda(-1)" title="Semana anterior">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <button class="pwa-nav-btn" onclick="irAHoyAgenda()" style="width: auto; padding: 0 8px; border-radius: 12px; font-weight: 800; font-size: 0.7rem;">Hoy</button>
                    <button class="pwa-nav-btn" onclick="cambiarSemanaAgenda(1)" title="Semana siguiente">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </div>
            </div>

            <div class="pwa-date-strip" id="pwaDateStripDays"></div>
        </div>

        <!-- Barber Filter Chips -->
        <div class="pwa-barber-chips" style="margin: -14px -16px 14px -16px;">
            <div class="pwa-chip active" data-barbero-id="0" onclick="filtrarBarberoAgenda(0, this)">
                <span class="pwa-chip-avatar">ALL</span>
                <span>Todos los Horarios</span>
            </div>
            <?php foreach ($barberosList as $b): ?>
                <div class="pwa-chip" data-barbero-id="<?php echo $b['id']; ?>" onclick="filtrarBarberoAgenda(<?php echo $b['id']; ?>, this)">
                    <span class="pwa-chip-avatar"><?php echo strtoupper(substr($b['nombre'], 0, 2)); ?></span>
                    <span><?php echo htmlspecialchars($b['nombre']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Feed de Citas y Disponibilidad en Vivo -->
        <div id="pwaAgendaFeed">
            <div style="text-align: center; padding: 40px; color: var(--text-light);">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px; font-weight: 600;">Cargando disponibilidad...</p>
            </div>
        </div>

        <!-- Resumen Flotante Inferior -->
        <div class="pwa-summary-pill">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; color: var(--text-gray);">
                <i class="fas fa-receipt"></i>
                <span>Ingresos de esta semana</span>
            </div>
            <div style="font-size: 1rem; font-weight: 800; color: var(--text-dark); text-decoration: underline;" id="txtIngresosSemana">$0.00</div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 2: OVERVIEW COMPLETO (TODOS LOS KPIS, AGENDA HOY, CALENDARIO, MODAL) -->
    <!-- ========================================================================= -->
    <section id="viewOverview" class="pwa-view-panel <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Overview Completo</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray); margin-top: 2px;">Métricas del negocio en tiempo real</p>
            </div>
        </div>

        <!-- Cuadros de Métricas al Principio -->
        <div class="pwa-kpi-grid">
            <!-- 1. Venta Total Hoy -->
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Venta Total (Hoy)</div>
                <div class="pwa-kpi-value">$<?php echo number_format($ventaTotalHoy, 2); ?></div>
                <div class="pwa-kpi-sub"><?php echo $citasCompletadasHoy; ?> citas • <?php echo $itemsVendidosHoy; ?> prods</div>
            </div>

            <!-- 2. Recaudación Total Mes -->
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Recaudación (Mes)</div>
                <div class="pwa-kpi-value">$<?php echo number_format($recaudacionMesTotal, 2); ?></div>
                <div class="pwa-kpi-sub"><?php echo $mesActual; ?> (<?php echo $citasCompletadasMes; ?> citas)</div>
            </div>

            <!-- 3. Ganancias Netas Negocio (con botón modal) -->
            <div class="pwa-kpi-card" onclick="abrirModalNetasPwa()" style="background: #F0FDF4; border: 1.5px solid #10B981; cursor: pointer;">
                <div class="pwa-kpi-title" style="color: #047857; display: flex; justify-content: space-between;">
                    <span>Ganancias Netas</span>
                    <span style="background: #10B981; color: #fff; font-size: 0.6rem; padding: 1px 5px; border-radius: 4px; font-weight: 900;">NEGOCIO</span>
                </div>
                <div class="pwa-kpi-value" style="color: #065F46;">$<?php echo number_format($gananciaNetaMesTotal, 2); ?></div>
                <div class="pwa-kpi-sub" style="color: #047857; font-weight: 700;">
                    Cortes: $<?php echo number_format($gananciaNetaMesServicios, 0); ?> • Ventas: $<?php echo number_format($gananciaNetaMesProd, 0); ?>
                </div>
                <div style="font-size: 0.68rem; color: #059669; font-weight: 800; margin-top: 4px; text-decoration: underline;">
                    Ver Histórico de Meses →
                </div>
            </div>

            <!-- 4. Ticket Promedio -->
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Ticket Promedio</div>
                <div class="pwa-kpi-value">$<?php echo number_format($ticketPromedioMes, 2); ?></div>
                <div class="pwa-kpi-sub">Por cita este mes</div>
            </div>

            <!-- 5. Propinas Mes -->
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Propinas (Mes)</div>
                <div class="pwa-kpi-value" style="color: var(--green-pwa);">+$<?php echo number_format($totalPropinasMes, 2); ?></div>
                <div class="pwa-kpi-sub">Total propinas barberos</div>
            </div>

            <!-- 6. Valor Stock -->
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Valor Stock Prods</div>
                <div class="pwa-kpi-value">$<?php echo number_format($valorInventario, 2); ?></div>
                <div class="pwa-kpi-sub"><?php echo $totalItems; ?> unidades stock</div>
            </div>

            <!-- 7. Descuentos por Referidos -->
            <div class="pwa-kpi-card" style="background: #EEF2FF; border: 1.5px solid #6366F1;">
                <div class="pwa-kpi-title" style="color: #4338CA; display: flex; justify-content: space-between;">
                    <span>Desc. Referidos</span>
                    <span style="background: #6366F1; color: #fff; font-size: 0.6rem; padding: 1px 5px; border-radius: 4px; font-weight: 900;">REF</span>
                </div>
                <div class="pwa-kpi-value" style="color: #312E81;">-$<?php echo number_format($totalDescuentosReferidosMes, 2); ?></div>
                <div class="pwa-kpi-sub" style="color: #4338CA;">
                    <?php echo $totalUsosReferidosMes; ?> referidos este mes
                </div>
            </div>

            <!-- 8. Fidelización -->
            <div class="pwa-kpi-card" style="background: #111111; color: #FFFFFF; border-color: #111111;">
                <div class="pwa-kpi-title" style="color: #AAAAAA;">Fidelización Hoy</div>
                <div class="pwa-kpi-value" style="color: #FFFFFF;"><?php echo $retentionRate; ?>%</div>
                <div class="pwa-kpi-sub" style="color: #10B981;">
                    <?php echo $recurrentes; ?> de <?php echo $totalHoyCitas; ?> citas recurrentes
                </div>
            </div>
        </div>

        <!-- Tendencia de Ventas (7 Días) -->
        <div class="pwa-item-card">
            <div style="font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 12px;">
                Tendencia de Ventas (Últimos 7 Días)
            </div>
            <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 110px; padding-top: 10px;">
                <?php foreach ($last7Days as $day):
                    $height = ($day['val'] / $maxVal) * 100;
                    $color = $day['val'] > 0 ? 'var(--gold-pwa)' : '#E5E7EB';
                    ?>
                    <div style="text-align: center; width: 100%;">
                        <div style="font-size: 9px; color: #888; margin-bottom: 4px;">$<?php echo (int) $day['val']; ?></div>
                        <div style="height: <?php echo max(4, $height); ?>%; background: <?php echo $color; ?>; width: 60%; margin: 0 auto; border-radius: 4px 4px 0 0;"></div>
                        <div style="margin-top: 6px; font-size: 10px; font-weight: 700; color: #666;"><?php echo $day['date']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Barberos y Alertas Stock -->
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
            <!-- Ranking Barberos -->
            <div class="pwa-item-card">
                <div style="font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-crown" style="color: var(--gold-pwa);"></i>
                    <span>Top Barberos (Mes)</span>
                </div>
                <?php if (empty($topBarberos)): ?>
                    <div style="font-size: 0.8rem; color: #888; text-align: center; padding: 12px;">Sin datos aún este mes.</div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($topBarberos as $tb): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; border-bottom: 1px solid #F3F4F6; padding-bottom: 6px;">
                                <div>
                                    <span style="font-weight: 800;"><?php echo htmlspecialchars($tb['nombre']); ?></span>
                                    <span style="font-size: 0.72rem; color: #888; margin-left: 4px;">(<?php echo $tb['citas']; ?> citas)</span>
                                </div>
                                <span style="font-weight: 900; color: var(--gold-pwa);">$<?php echo number_format($tb['total'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alerta Stock -->
            <?php if (!empty($lowStock)): ?>
                <div class="pwa-item-card" style="border-left: 4px solid var(--red-pwa); background: #FFFDFD;">
                    <div style="font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: var(--red-pwa); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Alerta de Stock Bajo</span>
                    </div>
                    <?php foreach ($lowStock as $ls): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; margin-bottom: 4px;">
                            <span><?php echo htmlspecialchars($ls['producto']); ?></span>
                            <span style="background: #FEE2E2; color: #DC2626; font-weight: 800; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px;">
                                <?php echo $ls['cantidad']; ?> / <?php echo $ls['stock_minimo']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================================================= -->
        <!-- AGENDA GENERAL DE HOY (CON CONTROL INTERACTIVO Y BOTÓN GUARDAR) -->
        <!-- ========================================================================= -->
        <div class="pwa-item-card" style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div style="font-size: 0.95rem; font-weight: 900; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-calendar-day" style="color: var(--gold-pwa);"></i>
                    <span>Agenda General de Hoy</span>
                </div>
                <button type="button" onclick="cambiarVistaPwa('citas')" class="pwa-btn-secondary" style="font-size: 0.72rem; padding: 4px 8px;">
                    Ver Todas →
                </button>
            </div>

            <?php if (empty($agendaGlobal)): ?>
                <div style="text-align: center; padding: 24px; color: var(--text-gray); font-size: 0.85rem;">
                    No hay citas registradas para el día de hoy.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($agendaGlobal as $cg): 
                        $horaStr = date('H:i', strtotime($cg['fecha_hora']));
                        $telLimpio = preg_replace('/\D/', '', $cg['telefono'] ?? '');
                    ?>
                        <div style="background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 10px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <div>
                                    <span style="font-weight: 900; font-size: 0.95rem; color: var(--text-dark);"><?php echo htmlspecialchars($cg['cliente']); ?></span>
                                    <div style="font-size: 0.75rem; color: var(--text-gray); font-weight: 600;">
                                        ✂️ <?php echo htmlspecialchars($cg['servicio']); ?> • 💈 <?php echo htmlspecialchars($cg['barbero']); ?>
                                    </div>
                                </div>
                                <span style="font-weight: 900; font-size: 0.9rem; color: var(--gold-pwa);"><?php echo $horaStr; ?></span>
                            </div>

                            <!-- Acciones de contacto y cambio de estado -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; pt-2; border-top: 1px dashed #E5E7EB; padding-top: 8px;">
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <?php if (!empty($telLimpio)): ?>
                                        <a href="https://wa.me/<?php echo $telLimpio; ?>?text=<?php echo urlencode('Hola ' . explode(' ', $cg['cliente'])[0] . ', te escribo de Kortzen sobre tu cita.'); ?>" target="_blank" style="background: #25D366; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.85rem;">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                        <a href="tel:<?php echo $telLimpio; ?>" style="background: #111; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.8rem;">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <select id="select_estado_overview_<?php echo $cg['id']; ?>" style="padding: 5px 8px; border-radius: 6px; font-weight: 700; font-size: 0.72rem; border: 1px solid #CCC; background: #FFF;">
                                        <option value="pendiente" <?php echo $cg['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="confirmada" <?php echo $cg['estado'] === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                        <option value="en_atencion" <?php echo $cg['estado'] === 'en_atencion' ? 'selected' : ''; ?>>En Atención</option>
                                        <option value="completada" <?php echo $cg['estado'] === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                        <option value="cancelada" <?php echo $cg['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                    <button type="button" onclick="guardarEstadoOverviewPwa(<?php echo $cg['id']; ?>)" style="background: #111; color: #FFF; border: none; padding: 5px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; cursor: pointer;">
                                        Guardar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================================================= -->
        <!-- CALENDARIO DE OCUPACIÓN INTERACTIVO (CON CARGA DE CITAS AL HACER CLIC) -->
        <!-- ========================================================================= -->
        <div class="pwa-item-card" style="margin-bottom: 24px;">
            <div style="font-size: 0.95rem; font-weight: 900; color: var(--text-dark); margin-bottom: 4px;">
                Calendario de Ocupación - <?php echo $mesActual . ' ' . $calYear; ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-bottom: 10px;">
                Toca cualquier día para ver sus citas y recaudación
            </div>

            <div class="pwa-cal-grid">
                <div class="pwa-cal-head">Lun</div>
                <div class="pwa-cal-head">Mar</div>
                <div class="pwa-cal-head">Mie</div>
                <div class="pwa-cal-head">Jue</div>
                <div class="pwa-cal-head">Vie</div>
                <div class="pwa-cal-head">Sab</div>
                <div class="pwa-cal-head">Dom</div>

                <?php
                // Espacios vacíos antes del primer día
                for ($i = 1; $i < $firstDayOfMonth; $i++) {
                    echo "<div></div>";
                }

                // Días del mes
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $count = $bookingsMap[$day] ?? 0;
                    $isToday = ($day == date('d'));
                    $hasCitas = ($count > 0);

                    $classStr = 'pwa-cal-cell';
                    if ($isToday) $classStr .= ' is-today';
                    if ($hasCitas) $classStr .= ' has-citas';

                    echo "<div class='$classStr' onclick='cargarDetalleDiaCalendario($day)'>";
                    echo "<div class='pwa-cal-num'>$day</div>";
                    if ($hasCitas) {
                        echo "<div class='pwa-cal-badge'>$count citas</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>

            <!-- Contenedor dinámico de citas del día seleccionado en el calendario -->
            <div id="pwaCalDayDetails" style="display: none; margin-top: 14px; background: #FAFAFA; border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div style="font-weight: 800; font-size: 0.88rem;" id="pwaCalDayTitle">Citas del Día</div>
                    <button type="button" onclick="document.getElementById('pwaCalDayDetails').style.display='none'" style="background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #888;">✕</button>
                </div>
                <div id="pwaCalDayContent" style="font-size: 0.82rem;"></div>
                <div id="pwaCalDaySummary" style="margin-top: 8px; font-weight: 800; color: var(--green-pwa); font-size: 0.85rem; text-align: right; border-top: 1px solid #E5E7EB; padding-top: 6px;"></div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 3: CITAS COMPLETAS -->
    <!-- ========================================================================= -->
    <section id="viewCitas" class="pwa-view-panel <?php echo $activeTab === 'citas' ? 'active' : ''; ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Citas & Reservas</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Control de agenda y estados</p>
            </div>
            <button onclick="abrirModalNuevaCitaPwa()" class="pwa-btn-secondary" style="background: #111; color: #fff;">
                + CITA
            </button>
        </div>

        <div id="pwaCitasListContainer">
            <div style="text-align: center; padding: 30px; color: var(--text-gray);">Cargando citas...</div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 4: EQUIPO & DISPONIBILIDAD EN TIEMPO REAL -->
    <!-- ========================================================================= -->
    <section id="viewEquipo" class="pwa-view-panel <?php echo $activeTab === 'equipo' ? 'active' : ''; ?>">
        <div style="margin-bottom: 16px;">
            <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Equipo & Disponibilidad</h2>
            <p style="font-size: 0.8rem; color: var(--text-gray);">Turnos libres en vivo de todos los barberos</p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($barberosList as $b): ?>
                <div class="pwa-item-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: #111; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                <?php echo strtoupper(substr($b['nombre'], 0, 2)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 800; font-size: 1rem; color: var(--text-dark);"><?php echo htmlspecialchars($b['nombre']); ?></div>
                                <div style="font-size: 0.78rem; color: var(--text-gray);"><?php echo htmlspecialchars($b['sucursal_nombre'] ?? 'Kortzen'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Horarios Disponibles del Barbero Hoy -->
                    <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: var(--green-pwa); text-transform: uppercase;">
                                <i class="fas fa-clock"></i> Turnos Libres Hoy
                            </span>
                            <button type="button" onclick="copiarDisponibilidadBarbero(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nombre']); ?>')" style="background: #25D366; color: #fff; border: none; padding: 4px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; cursor: pointer;">
                                <i class="fab fa-whatsapp"></i> Copiar Horarios
                            </button>
                        </div>
                        <div id="barber-slots-<?php echo $b['id']; ?>" style="display: flex; flex-wrap: wrap; gap: 4px;">
                            <span style="font-size: 0.75rem; color: var(--text-light);">Consultando disponibilidad...</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 5: INVENTARIO CENTRAL -->
    <!-- ========================================================================= -->
    <section id="viewInventario" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Inventario Central</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Stock de productos y suministros</p>
            </div>
            <a href="inventario.php" class="pwa-btn-secondary" style="background: #111; color: #fff;">+ PRODUCTO</a>
        </div>

        <?php if (empty($inventarioList)): ?>
            <div class="pwa-item-card" style="text-align: center; color: var(--text-gray); padding: 30px;">
                No hay productos registrados en el inventario.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($inventarioList as $inv): ?>
                    <div class="pwa-item-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-dark);"><?php echo htmlspecialchars($inv['producto']); ?></div>
                                <div style="font-size: 0.78rem; color: var(--text-gray);"><?php echo htmlspecialchars($inv['sucursal_nombre'] ?? 'Stock General'); ?></div>
                            </div>
                            <span style="font-weight: 900; font-size: 1.1rem; color: <?php echo floatval($inv['cantidad']) > 0 ? 'var(--green-pwa)' : 'var(--red-pwa)'; ?>;">
                                <?php echo number_format($inv['cantidad'], 0); ?> <span style="font-size: 0.75rem; font-weight: 600; color: #666;"><?php echo htmlspecialchars($inv['unidad'] ?? 'unid'); ?></span>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.8rem; color: #666; font-weight: 700;">
                            <div>Precio Ref: $<?php echo number_format($inv['precio'], 2); ?></div>
                            <div>Mínimo: <?php echo $inv['stock_minimo']; ?> unid</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 6: SERVICIOS & PRECIOS -->
    <!-- ========================================================================= -->
    <section id="viewServicios" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Servicios & Precios</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Catálogo de cortes y tratamientos</p>
            </div>
            <a href="servicios.php" class="pwa-btn-secondary" style="background: #111; color: #fff;">+ SERVICIO</a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($serviciosList as $srv): ?>
                <div class="pwa-item-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-dark);"><?php echo htmlspecialchars($srv['nombre']); ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-gray);"><?php echo htmlspecialchars($srv['categoria'] ?? 'General'); ?> • <?php echo $srv['duracion_minutos']; ?> min</div>
                        </div>
                        <div style="font-weight: 900; font-size: 1.15rem; color: var(--gold-pwa);">
                            $<?php echo number_format($srv['precio'], 2); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 7: SUCURSALES & SEDES -->
    <!-- ========================================================================= -->
    <section id="viewSucursales" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Sucursales & Sedes</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Sedes habilitadas de la barbería</p>
            </div>
            <a href="sucursales.php" class="pwa-btn-secondary" style="background: #111; color: #fff;">+ SEDE</a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($sucursalesList as $suc): ?>
                <div class="pwa-item-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                        <div style="font-weight: 800; font-size: 1rem; color: var(--text-dark);"><?php echo htmlspecialchars($suc['nombre']); ?></div>
                        <span style="font-size: 0.7rem; font-weight: 800; padding: 3px 8px; border-radius: 10px; text-transform: uppercase; background: <?php echo $suc['activo'] ? '#DCFCE7; color: #15803D;' : '#F3F4F6; color: #666;'; ?>">
                            <?php echo $suc['activo'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 4px;">📍 <?php echo htmlspecialchars($suc['direccion'] ?? 'Sin dirección'); ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-gray);">📞 <?php echo htmlspecialchars($suc['telefono'] ?? 'Sin teléfono'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 8: DIRECTORIO DE CLIENTES -->
    <!-- ========================================================================= -->
    <section id="viewClientes" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Clientes</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Directorio de clientes registrados</p>
            </div>
            <a href="clientes.php" class="pwa-btn-secondary" style="background: #111; color: #fff;">Ver Web</a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($clientesList as $cli): ?>
                <div class="pwa-item-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-dark);"><?php echo htmlspecialchars($cli['nombre']); ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-gray);"><?php echo htmlspecialchars($cli['telefono'] ?: ($cli['email'] ?? 'Sin teléfono')); ?></div>
                            <div style="font-size: 0.72rem; color: var(--gold-pwa); font-weight: 700; margin-top: 2px;">⭐ <?php echo intval($cli['puntos'] ?? 0); ?> Puntos • <?php echo intval($cli['total_citas']); ?> citas</div>
                        </div>
                        <?php if (!empty($cli['telefono'])): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $cli['telefono']); ?>" target="_blank" style="background: #25D366; color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1.1rem;">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 9: RESEÑAS & FEEDBACK -->
    <!-- ========================================================================= -->
    <section id="viewResenas" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Reseñas & Feedback</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Opiniones de clientes</p>
            </div>
            <a href="resenas.php" class="pwa-btn-secondary">Moderar</a>
        </div>

        <?php if (empty($resenasList)): ?>
            <div class="pwa-item-card" style="text-align: center; color: var(--text-gray); padding: 30px;">
                No hay reseñas registradas aún.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($resenasList as $res): ?>
                    <div class="pwa-item-card">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <div style="font-weight: 800; font-size: 0.9rem;"><?php echo htmlspecialchars($res['cliente_nombre'] ?? 'Cliente'); ?></div>
                            <div style="color: var(--gold-pwa); font-size: 0.85rem;">
                                <?php echo str_repeat('★', intval($res['calificacion'] ?? 5)); ?>
                            </div>
                        </div>
                        <div style="font-size: 0.82rem; color: #444; line-height: 1.4;"><?php echo htmlspecialchars($res['comentario'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ========================================================================= -->
    <!-- VIEW 10: GALERÍA WEB -->
    <!-- ========================================================================= -->
    <section id="viewGaleria" class="pwa-view-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-dark);">Galería Web</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Fotos y trabajos publicados</p>
            </div>
            <a href="galeria_admin.php" class="pwa-btn-secondary" style="background: #111; color: #fff;">+ SUBIR</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <?php foreach ($galeriaList as $img): ?>
                <div style="border-radius: 10px; overflow: hidden; height: 140px; background: #EEE; position: relative;">
                    <img src="<?php echo htmlspecialchars($img['imagen_url'] ?? $img['url']); ?>" alt="Corte" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Floating Action Button (+) -->
    <button class="pwa-fab" onclick="abrirModalNuevaCitaPwa()" aria-label="Crear Cita">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </button>

    <!-- Bottom Tab Bar -->
    <nav class="pwa-bottom-bar">
        <button class="pwa-tab-btn <?php echo $activeTab === 'agenda' ? 'active' : ''; ?>" onclick="cambiarVistaPwa('agenda')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <span>Agenda</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" onclick="cambiarVistaPwa('overview')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <span>Overview</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'citas' ? 'active' : ''; ?>" onclick="cambiarVistaPwa('citas')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            </div>
            <span>Citas</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'equipo' ? 'active' : ''; ?>" onclick="cambiarVistaPwa('equipo')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <span>Equipo</span>
        </button>
        <button class="pwa-tab-btn" onclick="abrirDrawer()">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </div>
            <span>Menú</span>
        </button>
    </nav>
</div>

<!-- Drawer / Sidebar Modal -->
<div class="pwa-drawer-mask" id="pwaDrawerMask" onclick="cerrarDrawer()"></div>
<aside class="pwa-drawer-content" id="pwaDrawerContent">
    <div class="pwa-drawer-promo">
        <h3>KORTZEN PRO Admin</h3>
        <p>Disponibilidad y gestión integral en tiempo real.</p>
        <span style="background: #111; color: #fff; padding: 6px 14px; border-radius: 12px; font-size: 0.75rem; font-weight: 800;">Modo Administrador</span>
    </div>

    <div style="padding: 0 16px 10px 16px;">
        <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; margin-bottom: 8px;">Sucursales</div>
        <select onchange="cambiarSucursalPwa(this.value)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; font-size: 0.88rem;">
            <option value="0" <?php echo ($filterSucursalId == 0) ? 'selected' : ''; ?>>Todas las Sucursales</option>
            <?php foreach ($sucursalesList as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo ($filterSucursalId == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="padding: 10px 16px 6px 16px; font-size: 0.72rem; font-weight: 800; color: var(--text-light); text-transform: uppercase;">
        Vistas Principales
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('agenda'); cerrarDrawer();">
        <i class="fas fa-calendar-alt" style="width: 20px;"></i>
        <span>Agenda & Disponibilidad</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('overview'); cerrarDrawer();">
        <i class="fas fa-chart-pie" style="width: 20px;"></i>
        <span>Overview & Rendimiento</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('citas'); cerrarDrawer();">
        <i class="fas fa-cut" style="width: 20px;"></i>
        <span>Citas Agendadas</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('equipo'); cerrarDrawer();">
        <i class="fas fa-users" style="width: 20px;"></i>
        <span>Equipo de Barberos</span>
    </div>

    <div style="padding: 14px 16px 6px 16px; font-size: 0.72rem; font-weight: 800; color: var(--text-light); text-transform: uppercase;">
        Módulos del Sistema
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('inventario'); cerrarDrawer();">
        <i class="fas fa-boxes" style="width: 20px;"></i>
        <span>Inventario Central</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('servicios'); cerrarDrawer();">
        <i class="fas fa-tag" style="width: 20px;"></i>
        <span>Servicios & Precios</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('sucursales'); cerrarDrawer();">
        <i class="fas fa-store" style="width: 20px;"></i>
        <span>Sucursales & Sedes</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('clientes'); cerrarDrawer();">
        <i class="fas fa-user-friends" style="width: 20px;"></i>
        <span>Directorio de Clientes</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('resenas'); cerrarDrawer();">
        <i class="fas fa-star" style="width: 20px;"></i>
        <span>Reseñas & Feedback</span>
    </div>
    <div class="pwa-drawer-item" onclick="cambiarVistaPwa('galeria'); cerrarDrawer();">
        <i class="fas fa-images" style="width: 20px;"></i>
        <span>Galería Web</span>
    </div>
    <a href="logout.php" class="pwa-drawer-item" style="color: #EF4444; margin-top: 10px; border-top: 1px solid var(--border-pwa); padding-top: 14px;">
        <i class="fas fa-sign-out-alt" style="width: 20px;"></i>
        <span>Cerrar Sesión</span>
    </a>
</aside>

<!-- Modal Detalle Cita Sheet -->
<div class="pwa-action-sheet" id="pwaCitaSheet" onclick="if(event.target===this) cerrarModalCitaPwa()">
    <div class="pwa-sheet-box">
        <div style="width: 40px; height: 4px; background: #DDD; border-radius: 4px; margin: 0 auto 16px auto;"></div>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-dark);" id="pwaSheetCliNombre">Cliente</div>
                <div style="font-size: 0.85rem; color: var(--text-gray);" id="pwaSheetServNombre">Servicio</div>
            </div>
            <button onclick="cerrarModalCitaPwa()" style="background: #F4F4F4; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer;">✕</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
            <div style="background: #F9F9F9; padding: 10px; border-radius: 8px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase;">Hora</div>
                <div style="font-size: 0.9rem; font-weight: 800;" id="pwaSheetHora">-</div>
            </div>
            <div style="background: #F9F9F9; padding: 10px; border-radius: 8px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase;">Barbero</div>
                <div style="font-size: 0.9rem; font-weight: 800;" id="pwaSheetBarbero">-</div>
            </div>
            <div style="background: #F9F9F9; padding: 10px; border-radius: 8px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase;">Estado</div>
                <div style="font-size: 0.9rem; font-weight: 800;" id="pwaSheetEstado">-</div>
            </div>
            <div style="background: #F9F9F9; padding: 10px; border-radius: 8px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase;">Total</div>
                <div style="font-size: 0.9rem; font-weight: 800;" id="pwaSheetPrecio">-</div>
            </div>
        </div>

        <div id="pwaSheetActions" style="display: flex; flex-direction: column; gap: 10px;"></div>
    </div>
</div>

<!-- Modal Nueva Cita Rápida -->
<div class="pwa-action-sheet" id="pwaNuevaCitaSheet" onclick="if(event.target===this) cerrarModalNuevaCitaPwa()">
    <div class="pwa-sheet-box">
        <div style="width: 40px; height: 4px; background: #DDD; border-radius: 4px; margin: 0 auto 16px auto;"></div>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div style="font-size: 1.15rem; font-weight: 800;">Nueva Cita Rápida</div>
                <div style="font-size: 0.8rem; color: var(--text-gray);">Agendamiento directo administrativo</div>
            </div>
            <button onclick="cerrarModalNuevaCitaPwa()" style="background: #F4F4F4; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer;">✕</button>
        </div>

        <form id="formPwaNuevaCita" onsubmit="guardarCitaPwa(event)" style="display: flex; flex-direction: column; gap: 12px;">
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Nombre del Cliente</label>
                <input type="text" name="cliente_nombre" required placeholder="Ej: Juan Pérez" style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Teléfono / WhatsApp</label>
                <input type="tel" name="cliente_telefono" placeholder="Ej: 0991234567" style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Barbero</label>
                <select name="barbero_id" id="pwaInputBarbero" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
                    <?php foreach ($barberosList as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['nombre']); ?> (<?php echo htmlspecialchars($b['sucursal_nombre'] ?? 'Kortzen'); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Servicio</label>
                <select name="servicio_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
                    <?php foreach ($serviciosList as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?> - $<?php echo number_format($s['precio'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Fecha</label>
                    <input type="date" name="fecha" id="pwaInputFecha" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
                </div>
                <div>
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Hora</label>
                    <input type="time" name="hora" id="pwaInputHora" value="10:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border-pwa); font-weight: 700; margin-top: 4px;">
                </div>
            </div>

            <button type="submit" id="btnSubmitPwaCita" class="pwa-btn-main" style="margin-top: 8px;">Agendar Cita</button>
        </form>
    </div>
</div>

<!-- Modal Ganancias Netas Históricas -->
<div class="pwa-action-sheet" id="pwaModalNetas" onclick="if(event.target===this) cerrarModalNetasPwa()">
    <div class="pwa-sheet-box">
        <div style="width: 40px; height: 4px; background: #DDD; border-radius: 4px; margin: 0 auto 16px auto;"></div>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div style="font-size: 1.15rem; font-weight: 900; color: #047857;">Ganancias Netas Negocio</div>
                <div style="font-size: 0.78rem; color: var(--text-gray);">Desglose mensual de comisiones y margen real</div>
            </div>
            <button onclick="cerrarModalNetasPwa()" style="background: #F4F4F4; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer;">✕</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($mesesHistoricosMap as $mKey => $mData): 
                $brutoTotal = $mData['bruto_servicios'] + $mData['bruto_productos'];
                $comisionTotal = $mData['comision_servicios'] + $mData['comision_productos'];
                $netoTotal = $mData['neto_servicios'] + $mData['neto_productos'];
                $margenMes = $brutoTotal > 0 ? (($netoTotal / $brutoTotal) * 100) : 100;
                $esMesActual = ($mKey === date('Y-m'));
            ?>
                <div style="background: #F9FAFB; border: 1.5px solid <?php echo $esMesActual ? '#10B981' : '#E5E7EB'; ?>; border-radius: 12px; padding: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-weight: 900; font-size: 0.95rem; color: #111;">
                            <?php echo htmlspecialchars($mData['mes_nombre']); ?>
                            <?php if ($esMesActual): ?>
                                <span style="background: #10B981; color: #fff; font-size: 0.6rem; padding: 1px 5px; border-radius: 4px; margin-left: 4px;">ACTUAL</span>
                            <?php endif; ?>
                        </span>
                        <span style="background: #E5E7EB; color: #374151; font-weight: 800; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">
                            <?php echo number_format($margenMes, 1); ?>% Margen
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #555; margin-bottom: 2px;">
                        <span>Bruto Facturado:</span>
                        <span style="font-weight: 700;">$<?php echo number_format($brutoTotal, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #D97706; margin-bottom: 4px;">
                        <span>Comisiones Barberos:</span>
                        <span style="font-weight: 700;">-$<?php echo number_format($comisionTotal, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: 900; color: #047857; border-top: 1px dashed #D1D5DB; padding-top: 6px;">
                        <span>Neto Negocio:</span>
                        <span>+$<?php echo number_format($netoTotal, 2); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
let startWeekStr = '<?php echo date('Y-m-d', strtotime('monday this week')); ?>';
let endWeekStr = '<?php echo date('Y-m-d', strtotime('sunday this week')); ?>';
let selectedAgendaDate = '<?php echo date('Y-m-d'); ?>';
let selectedAgendaBarber = 0;
let agendaJsonData = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarDatosAgendaPwa();
});

function cambiarVistaPwa(vistaName) {
    document.querySelectorAll('.pwa-view-panel').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.pwa-tab-btn').forEach(b => b.classList.remove('active'));

    const viewEl = document.getElementById('view' + vistaName.charAt(0).toUpperCase() + vistaName.slice(1));
    if (viewEl) viewEl.classList.add('active');

    const btn = Array.from(document.querySelectorAll('.pwa-tab-btn')).find(b => b.innerText.toLowerCase().includes(vistaName.toLowerCase()));
    if (btn) btn.classList.add('active');

    if (vistaName === 'agenda') {
        cargarDatosAgendaPwa();
    } else if (vistaName === 'citas') {
        renderizarCitasTab();
    }
}

function cargarDatosAgendaPwa() {
    const feed = document.getElementById('pwaAgendaFeed');
    feed.innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--text-light);">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 10px; font-weight: 600;">Cargando disponibilidad...</p>
        </div>
    `;

    fetch(`api/get_agenda_admin.php?start_date=${startWeekStr}&end_date=${endWeekStr}&barbero_id=${selectedAgendaBarber}&sucursal_id=<?php echo $filterSucursalId; ?>`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                agendaJsonData = data;
                actualizarMesHeader(data.rango.mes_nombre);
                renderizarDateStripPwa(data);
                renderizarFeedAgendaPwa(data);
                actualizarDisponibilidadEquipo(data);
                if (data.metricas) {
                    document.getElementById('txtIngresosSemana').innerText = '$' + data.metricas.ingresos_semana;
                }
            } else {
                feed.innerHTML = `<div style="text-align: center; padding: 40px; color: red;">Error: ${data.error}</div>`;
            }
        })
        .catch(err => {
            feed.innerHTML = `<div style="text-align: center; padding: 40px; color: red;">Error al consultar agenda.</div>`;
        });
}

function actualizarMesHeader(mesStr) {
    if (!mesStr) return;
    const parts = mesStr.split(' ');
    document.getElementById('txtMes').innerText = parts[0] || 'septiembre';
    document.getElementById('txtAnio').innerText = parts[1] || '2026';
}

function renderizarDateStripPwa(data) {
    const strip = document.getElementById('pwaDateStripDays');
    strip.innerHTML = '';

    const letras = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    const today = new Date().toISOString().split('T')[0];

    let curr = new Date(startWeekStr + 'T00:00:00');
    for (let i = 0; i < 7; i++) {
        const f = curr.toISOString().split('T')[0];
        const dayIdx = curr.getDay();
        const num = curr.getDate();
        const isToday = (f === today);
        const isSel = (f === selectedAgendaDate);

        const col = document.createElement('div');
        col.className = `pwa-day-col ${isToday ? 'is-today' : ''} ${isSel ? 'is-selected' : ''}`;
        col.onclick = () => {
            selectedAgendaDate = f;
            document.querySelectorAll('.pwa-day-col').forEach(c => c.classList.remove('is-selected'));
            col.classList.add('is-selected');
            const target = document.getElementById('pwa-day-' + f);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        col.innerHTML = `
            <div class="pwa-day-letter">${letras[dayIdx]}</div>
            <div class="pwa-day-num">
                ${num}
                ${isToday ? '<div class="pwa-dot"></div>' : ''}
            </div>
        `;

        strip.appendChild(col);
        curr.setDate(curr.getDate() + 1);
    }
}

function renderizarFeedAgendaPwa(data) {
    const feed = document.getElementById('pwaAgendaFeed');
    feed.innerHTML = '';

    const diasNombres = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const mesesNombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const today = new Date().toISOString().split('T')[0];

    let curr = new Date(startWeekStr + 'T00:00:00');

    for (let i = 0; i < 7; i++) {
        const f = curr.toISOString().split('T')[0];
        const dayIdx = curr.getDay();
        const num = curr.getDate();
        const mIdx = curr.getMonth();

        const title = `${diasNombres[dayIdx]}, ${num} ${mesesNombres[mIdx]}`;
        const dayCitas = (data.citas_por_fecha && data.citas_por_fecha[f]) ? data.citas_por_fecha[f] : [];
        const dayDisp = (data.disponibilidad && data.disponibilidad[f]) ? data.disponibilidad[f] : null;

        let totalLibres = 0;
        if (dayDisp && dayDisp.barberos) {
            dayDisp.barberos.forEach(b => { totalLibres += (b.total_slots_libres || 0); });
        }

        const group = document.createElement('div');
        group.className = 'pwa-day-group';
        group.id = 'pwa-day-' + f;

        let citasHtml = '';
        if (dayCitas.length > 0) {
            dayCitas.forEach(c => {
                const hIn = formatHoraPwa(c.fecha_hora);
                const statusClass = 'status-' + (c.estado || 'pendiente');

                citasHtml += `
                    <div class="pwa-cita-card ${statusClass}" onclick="abrirModalDetalleCitaPwa(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="pwa-cita-header">
                            <div class="pwa-cita-client">${escapeHtml(c.cliente_nombre)}</div>
                            <div class="pwa-cita-service">${escapeHtml(c.servicio_nombre)}</div>
                        </div>
                        <div class="pwa-cita-meta">
                            <div>${hIn}</div>
                            <div class="pwa-barber-tag">${escapeHtml(c.barbero_nombre)}</div>
                        </div>
                    </div>
                `;
            });
        } else {
            citasHtml = `<div class="pwa-day-empty">Nada planeado</div>`;
        }

        let liveLineHtml = (f === today) ? `<div class="pwa-live-time"><div class="pwa-live-dot"></div><div class="pwa-live-line"></div></div>` : '';

        let availBoxHtml = '';
        if (dayDisp && dayDisp.barberos && dayDisp.barberos.length > 0) {
            let slotsContent = '';
            dayDisp.barberos.forEach(b => {
                if (b.labora && b.slots_libres && b.slots_libres.length > 0) {
                    let pills = b.slots_libres.map(s => `<button type="button" class="pwa-slot-pill" onclick="agendarSlotPwa('${f}', '${s.hora_inicio}', ${b.barbero_id})">${s.label}</button>`).join('');
                    slotsContent += `
                        <div style="margin-bottom: 8px;">
                            <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px;">${escapeHtml(b.barbero_nombre)} (${b.slots_libres.length} libres):</div>
                            <div>${pills}</div>
                        </div>
                    `;
                }
            });

            availBoxHtml = `
                <div class="pwa-avail-box" id="pwa-avail-${f}">
                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--green-pwa); margin-bottom: 6px;">
                        <i class="fas fa-clock"></i> Horarios Disponibles en Tiempo Real
                    </div>
                    ${slotsContent}
                </div>
            `;
        }

        group.innerHTML = `
            <div class="pwa-day-title">
                <div>${title}</div>
                <button type="button" class="pwa-avail-badge" onclick="document.getElementById('pwa-avail-${f}').classList.toggle('open')">
                    <i class="fas fa-clock"></i> ${totalLibres} libres
                </button>
            </div>
            ${liveLineHtml}
            ${citasHtml}
            ${availBoxHtml}
        `;

        feed.appendChild(group);
        curr.setDate(curr.getDate() + 1);
    }
}

function actualizarDisponibilidadEquipo(data) {
    const today = new Date().toISOString().split('T')[0];
    if (data.disponibilidad && data.disponibilidad[today]) {
        const barberosHoy = data.disponibilidad[today].barberos || [];
        barberosHoy.forEach(b => {
            const container = document.getElementById('barber-slots-' + b.barbero_id);
            if (container) {
                if (b.labora && b.slots_libres && b.slots_libres.length > 0) {
                    let html = b.slots_libres.slice(0, 8).map(s => `
                        <span class="pwa-slot-pill" onclick="agendarSlotPwa('${today}', '${s.hora_inicio}', ${b.barbero_id})">${s.label}</span>
                    `).join('');
                    if (b.slots_libres.length > 8) {
                        html += `<span style="font-size: 0.72rem; font-weight: 700; color: var(--text-gray); padding: 4px;">+${b.slots_libres.length - 8} más</span>`;
                    }
                    container.innerHTML = html;
                } else if (!b.labora) {
                    container.innerHTML = `<span style="font-size: 0.75rem; color: var(--red-pwa); font-weight: 700;">Descanso / No labora hoy</span>`;
                } else {
                    container.innerHTML = `<span style="font-size: 0.75rem; color: var(--text-gray);">Sin turnos disponibles hoy</span>`;
                }
            }
        });
    }
}

function copiarDisponibilidadBarbero(bId, bNombre) {
    const today = new Date().toISOString().split('T')[0];
    if (agendaJsonData && agendaJsonData.disponibilidad && agendaJsonData.disponibilidad[today]) {
        const bData = (agendaJsonData.disponibilidad[today].barberos || []).find(b => b.barbero_id === bId);
        if (bData && bData.slots_libres && bData.slots_libres.length > 0) {
            const lista = bData.slots_libres.map(s => `• ${s.label}`).join('\n');
            const txt = `👋 ¡Hola! Estos son los turnos libres de hoy para *${bNombre}*:\n\n${lista}\n\n¿Cuál horario te gustaría agendar?`;
            navigator.clipboard.writeText(txt).then(() => alert('¡Horarios copiados para WhatsApp!')).catch(() => prompt('Copia estos horarios:', txt));
            return;
        }
    }
    alert('No hay horarios disponibles para copiar hoy.');
}

function renderizarCitasTab() {
    const cont = document.getElementById('pwaCitasListContainer');
    if (!agendaJsonData || !agendaJsonData.citas) {
        cont.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-light);">Cargando citas...</div>';
        return;
    }

    if (agendaJsonData.citas.length === 0) {
        cont.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-light);">No hay citas registradas en este período.</div>';
        return;
    }

    let html = '';
    agendaJsonData.citas.forEach(c => {
        html += `
            <div class="pwa-item-card" onclick="abrirModalDetalleCitaPwa(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.95rem;">${escapeHtml(c.cliente_nombre)}</div>
                        <div style="font-size: 0.78rem; color: var(--text-gray);">${escapeHtml(c.servicio_nombre)} • $${parseFloat(c.precio_servicio||0).toFixed(2)}</div>
                    </div>
                    <span style="font-size: 0.7rem; font-weight: 800; padding: 3px 8px; border-radius: 10px; text-transform: uppercase; background: #F4F4F4;">${c.estado}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.78rem; color: #666; font-weight: 600;">
                    <div>📅 ${c.fecha_hora}</div>
                    <div>✂️ ${escapeHtml(c.barbero_nombre)}</div>
                </div>
            </div>
        `;
    });
    cont.innerHTML = html;
}

function formatHoraPwa(dateTimeStr) {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr.replace(/-/g, '/'));
    let h = date.getHours();
    let m = date.getMinutes();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    m = m < 10 ? '0' + m : m;
    return `${h}:${m}${ampm}`;
}

function cambiarSemanaAgenda(delta) {
    let curr = new Date(startWeekStr + 'T00:00:00');
    curr.setDate(curr.getDate() + (delta * 7));
    startWeekStr = curr.toISOString().split('T')[0];

    let end = new Date(curr);
    end.setDate(end.getDate() + 6);
    endWeekStr = end.toISOString().split('T')[0];

    selectedAgendaDate = startWeekStr;
    cargarDatosAgendaPwa();
}

function irAHoyAgenda() {
    let now = new Date();
    let day = now.getDay();
    let diff = now.getDate() - day + (day === 0 ? -6 : 1);
    let monday = new Date(now.setDate(diff));

    startWeekStr = monday.toISOString().split('T')[0];
    let end = new Date(monday);
    end.setDate(end.getDate() + 6);
    endWeekStr = end.toISOString().split('T')[0];

    selectedAgendaDate = new Date().toISOString().split('T')[0];
    cargarDatosAgendaPwa();
}

function filtrarBarberoAgenda(bId, el) {
    selectedAgendaBarber = bId;
    document.querySelectorAll('.pwa-chip').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    cargarDatosAgendaPwa();
}

function cambiarSucursalPwa(sId) {
    window.location.href = 'pwa-admin.php?sucursal_id=' + sId;
}

function abrirDrawer() {
    document.getElementById('pwaDrawerMask').classList.add('open');
    document.getElementById('pwaDrawerContent').classList.add('open');
}

function cerrarDrawer() {
    document.getElementById('pwaDrawerMask').classList.remove('open');
    document.getElementById('pwaDrawerContent').classList.remove('open');
}

function abrirModalDetalleCitaPwa(c) {
    document.getElementById('pwaSheetCliNombre').innerText = c.cliente_nombre;
    document.getElementById('pwaSheetServNombre').innerText = c.servicio_nombre;
    document.getElementById('pwaSheetHora').innerText = c.fecha_hora;
    document.getElementById('pwaSheetBarbero').innerText = c.barbero_nombre;
    document.getElementById('pwaSheetEstado').innerText = (c.estado || 'pendiente').toUpperCase();
    document.getElementById('pwaSheetPrecio').innerText = '$' + parseFloat(c.precio_final > 0 ? c.precio_final : c.precio_servicio || 0).toFixed(2);

    const act = document.getElementById('pwaSheetActions');
    act.innerHTML = '';

    if (c.cliente_telefono) {
        act.innerHTML += `
            <a href="https://wa.me/${c.cliente_telefono.replace(/\D/g,'')}" target="_blank" class="pwa-btn-main" style="background: #25D366; color: #fff;">
                <i class="fab fa-whatsapp"></i> WhatsApp Cliente
            </a>
        `;
    }

    if (c.estado !== 'completada') {
        act.innerHTML += `
            <button type="button" class="pwa-btn-main" onclick="completarCitaPwa(${c.id})">
                <i class="fas fa-check-circle"></i> Confirmar y Terminar
            </button>
            <button type="button" class="pwa-btn-main" style="background: #FEE2E2; color: #DC2626;" onclick="cancelarCitaPwa(${c.id})">
                <i class="fas fa-times-circle"></i> Cancelar Cita
            </button>
        `;
    }

    document.getElementById('pwaCitaSheet').style.display = 'flex';
}

function cerrarModalCitaPwa() {
    document.getElementById('pwaCitaSheet').style.display = 'none';
}

function abrirModalNuevaCitaPwa() {
    document.getElementById('pwaNuevaCitaSheet').style.display = 'flex';
}

function cerrarModalNuevaCitaPwa() {
    document.getElementById('pwaNuevaCitaSheet').style.display = 'none';
}

function abrirModalNetasPwa() {
    document.getElementById('pwaModalNetas').style.display = 'flex';
}

function cerrarModalNetasPwa() {
    document.getElementById('pwaModalNetas').style.display = 'none';
}

function agendarSlotPwa(fecha, hora, barberoId) {
    document.getElementById('pwaInputFecha').value = fecha;
    document.getElementById('pwaInputHora').value = hora;
    if (barberoId) document.getElementById('pwaInputBarbero').value = barberoId;
    abrirModalNuevaCitaPwa();
}

function guardarCitaPwa(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitPwaCita');
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData(document.getElementById('formPwaNuevaCita'));
    formData.append('action', 'crear_manual');

    fetch('api/citas_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cerrarModalNuevaCitaPwa();
            document.getElementById('formPwaNuevaCita').reset();
            cargarDatosAgendaPwa();
        } else {
            alert('Error: ' + (data.error || data.message || 'Error en servidor'));
            btn.disabled = false;
            btn.innerText = 'Agendar Cita';
        }
    })
    .catch(err => {
        cerrarModalNuevaCitaPwa();
        cargarDatosAgendaPwa();
    });
}

function completarCitaPwa(id) {
    if (confirm('¿Completar esta cita?')) {
        const formData = new FormData();
        formData.append('action', 'completar');
        formData.append('id', id);
        formData.append('ajax', '1');

        fetch('api/citas_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(() => {
                cerrarModalCitaPwa();
                cargarDatosAgendaPwa();
            })
            .catch(() => {
                cerrarModalCitaPwa();
                cargarDatosAgendaPwa();
            });
    }
}

function cancelarCitaPwa(id) {
    if (confirm('¿Cancelar esta cita?')) {
        const formData = new FormData();
        formData.append('action', 'cancelar_barbero');
        formData.append('id', id);
        formData.append('ajax', '1');

        fetch('api/citas_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(() => {
                cerrarModalCitaPwa();
                cargarDatosAgendaPwa();
            })
            .catch(() => {
                cerrarModalCitaPwa();
                cargarDatosAgendaPwa();
            });
    }
}

function guardarEstadoOverviewPwa(id) {
    const sel = document.getElementById('select_estado_overview_' + id);
    if (!sel) return;
    const nuevoEstado = sel.value;

    const formData = new FormData();
    formData.append('action', 'cambiar_estado');
    formData.append('id', id);
    formData.append('estado', nuevoEstado);
    formData.append('ajax', '1');

    fetch('api/citas_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Estado actualizado correctamente.');
        } else {
            alert('Error al actualizar estado: ' + (data.error || data.message || 'Error'));
        }
    })
    .catch(err => {
        alert('Estado actualizado.');
    });
}

function cargarDetalleDiaCalendario(day) {
    const year = <?php echo $calYear; ?>;
    const month = "<?php echo str_pad($calMonth, 2, '0', STR_PAD_LEFT); ?>";
    const dayStr = String(day).padStart(2, '0');
    const fullDate = `${year}-${month}-${dayStr}`;
    const branchId = "<?php echo $filterSucursalId > 0 ? $filterSucursalId : ''; ?>";

    const box = document.getElementById('pwaCalDayDetails');
    const title = document.getElementById('pwaCalDayTitle');
    const content = document.getElementById('pwaCalDayContent');
    const summary = document.getElementById('pwaCalDaySummary');

    box.style.display = 'block';
    title.innerText = `Citas del ${dayStr}/${month}/${year}`;
    content.innerHTML = '<div style="color: #888; text-align: center; padding: 10px;">Cargando citas...</div>';
    summary.innerText = '';

    let url = `api/get_citas_dia.php?fecha=${fullDate}`;
    if (branchId) url += `&sucursal_id=${branchId}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.citas && data.citas.length > 0) {
                    let html = '<div style="display: flex; flex-direction: column; gap: 6px;">';
                    data.citas.forEach(c => {
                        const timePart = c.fecha_hora ? c.fecha_hora.split(' ')[1].substring(0, 5) : '--:--';
                        html += `
                            <div style="background: #FFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 8px 10px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 800; color: #111;">${timePart} • ${escapeHtml(c.cliente)}</div>
                                    <div style="font-size: 0.72rem; color: #666;">✂️ ${escapeHtml(c.servicio)} (💈 ${escapeHtml(c.barbero)})</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 800; color: #10B981;">$${parseFloat(c.precio_final).toFixed(2)}</div>
                                    <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #666;">${c.estado}</span>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                    summary.innerText = `${data.total_citas} citas • Recaudado: $${parseFloat(data.total_recaudado).toFixed(2)}`;
                } else {
                    content.innerHTML = '<div style="color: #888; text-align: center; padding: 10px;">No hubo citas registradas este día.</div>';
                    summary.innerText = 'Recaudado: $0.00';
                }
            } else {
                content.innerHTML = `<div style="color: red; text-align: center;">Error: ${data.message}</div>`;
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="color: red; text-align: center;">Error de conexión.</div>';
        });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

</body>
</html>
