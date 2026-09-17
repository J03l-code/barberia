<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

if ($currentUser['rol'] === 'barbero') {
    header('Location: barber-dashboard.php');
    exit;
}

// Obtener estadísticas generales (solo si es Admin o Admin Local, mantenemos la lógica original)
// ... (código original de admins omitido/mantenido igual, nos enfocamos en mejorar la vista de BARBERO)

$pageTitle = 'Overview Completo';
include 'includes/header.php';
?>
<?php
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$userSucursalesList = [];
$filterSucursalId = intval($_GET['sucursal_id'] ?? 0);

if ($currentUser['rol'] === 'admin_local') {
    $userSucursalesList = getUsuarioSucursales($currentUser['id']);
    if ($filterSucursalId > 0 && in_array($filterSucursalId, $userSucursalesIds)) {
        $scopedBranchIds = [$filterSucursalId];
    } else {
        $scopedBranchIds = !empty($userSucursalesIds) ? $userSucursalesIds : [-1];
        $filterSucursalId = 0;
    }
} else {
    // Admin técnico
    try {
        $userSucursalesList = query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
    } catch (Exception $e) {
        $userSucursalesList = [];
    }
    if ($filterSucursalId > 0) {
        $scopedBranchIds = [$filterSucursalId];
    } else {
        $scopedBranchIds = [];
    }
}
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="margin: 0;">Bienvenido, <?php echo htmlspecialchars($currentUser['nombre']); ?></h1>
        <p class="page-subtitle" style="margin-top: 4px;">Overview y Métricas en Tiempo Real</p>
    </div>
    <?php if (count($userSucursalesList) > 1 || $currentUser['rol'] === 'admin'): ?>
    <div>
        <form method="GET" action="dashboard.php" style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 11px; font-weight: 700; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Filtro Sucursal:</label>
            <select name="sucursal_id" onchange="this.form.submit()" style="padding: 8px 14px; border-radius: 8px; border: 1.5px solid #EAEAEA; background: #FFFFFF; font-weight: 800; font-size: 0.85rem; cursor: pointer; color: #111111; outline: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                <option value="0" <?php echo ($filterSucursalId == 0) ? 'selected' : ''; ?>>
                    <?php echo $currentUser['rol'] === 'admin_local' ? '🏢 Mis Sucursales Asignadas' : '🏢 Todas las Sucursales (Global)'; ?>
                </option>
                <?php foreach ($userSucursalesList as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($filterSucursalId == $s['id']) ? 'selected' : ''; ?>>📍 <?php echo htmlspecialchars($s['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php elseif (count($userSucursalesList) === 1): ?>
    <div style="background: #111111; color: #FFFFFF; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 700;">
        📍 Sucursal: <?php echo htmlspecialchars($userSucursalesList[0]['nombre']); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Estilos Específicos para Dashboard Mejorado -->
<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .earnings-card {
        background: linear-gradient(135deg, #1A1A1A 0%, #252525 100%);
        border: 1px solid rgba(255, 215, 0, 0.1);
        padding: 24px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }

    .earnings-card::after {
        content: '$';
        position: absolute;
        right: -10px;
        bottom: -20px;
        font-size: 120px;
        color: rgba(255, 215, 0, 0.03);
        font-family: var(--font-heading);
        font-weight: 700;
        pointer-events: none;
    }

    .earnings-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .earnings-amount {
        font-size: 32px;
        font-weight: 700;
        color: var(--primary-gold);
        font-family: var(--font-heading);
    }

    .trend-indicator {
        font-size: 12px;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .trend-up {
        color: #2ECC71;
    }

    /* Next Up Widget */
    .next-client-card {
        background: var(--bg-sidebar);
        border-left: 4px solid var(--primary-gold);
        padding: 24px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .next-time {
        text-align: center;
        min-width: 80px;
    }

    .next-hour {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    .next-label {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--primary-gold);
        margin-top: 4px;
    }

    .client-details h3 {
        font-size: 18px;
        margin-bottom: 4px;
        color: var(--text-primary);
    }

    .service-badge {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(201, 169, 110, 0.15);
        color: var(--primary-gold);
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 6px;
    }

    .action-buttons-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .quick-action-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        padding: 16px;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--text-secondary);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .quick-action-btn:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: var(--primary-gold);
        color: var(--primary-gold);
    }

    /* Modal Styles override/addition */
    .history-item {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .history-date {
        font-size: 12px;
        color: var(--text-muted);
    }

    .history-service {
        font-weight: 500;
        color: var(--text-primary);
        font-size: 14px;
    }

    .history-price {
        color: var(--primary-gold);
        font-weight: 600;
    }
</style>

<?php if (in_array($currentUser['rol'], ['admin', 'admin_local'])): ?>
    <!-- VISTA ADMIN / ADMIN LOCAL (OVERVIEW Y MÉTRICAS COMPLETAS) -->
    <?php
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

    // GANANCIAS NETAS REALES DEL NEGOCIO (Descontando comisiones de barberos)
    // 1. Servicios Mes Neto Negocio
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

    // 2. Ventas Productos Mes Neto Negocio
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

    // 3. Ganancias Netas Hoy (Negocio)
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

    // 4. HISTORIAL MENSUAL COMPLETO DE GANANCIAS NETAS (TODOS LOS MESES)
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

    // Totales Históricos Acumulados
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

    // Ticket Promedio del Mes
    $ticketPromedioMes = $citasCompletadasMes > 0 ? ($vCitasMes / $citasCompletadasMes) : 0;

    // 3. Valor Stock Global / Sede
    $invStats = query("SELECT SUM(cantidad * precio) as total_valor, SUM(cantidad) as total_items FROM inventario WHERE 1=1 $whereInvDirect")[0] ?? [];
    $valorInventario = floatval($invStats['total_valor'] ?? 0);
    $totalItems = floatval($invStats['total_items'] ?? 0);

    // 4. Ranking Barberos
    $topBarberos = query("SELECT u.id, u.nombre, s.nombre as sucursal, COUNT(c.id) as citas, SUM(c.precio_final) as total
                          FROM usuarios u
                          JOIN citas c ON u.id = c.barbero_id
                          LEFT JOIN sucursales s ON c.sucursal_id = s.id
                          WHERE c.estado = 'completada' AND DATE(c.fecha_hora) BETWEEN ? AND ? $whereCitasAliased
                          GROUP BY u.id ORDER BY total DESC LIMIT 5", [$mesInicio, $mesFin]);

    // 5. Inventario Bajo / Alertas
    $lowStock = query("SELECT i.producto, i.cantidad, i.stock_minimo, s.nombre as sucursal 
                       FROM inventario i
                       JOIN sucursales s ON i.sucursal_id = s.id
                       WHERE i.cantidad <= i.stock_minimo $whereInvAliased
                       ORDER BY i.cantidad ASC LIMIT 5");

    // 6. Agenda de Hoy
    $agendaGlobal = query("SELECT c.*, u.nombre as barbero, s.nombre as servicio, suc.nombre as sucursal_nombre, cli.nombre as cliente, cli.telefono, cli.id as cliente_id 
                           FROM citas c
                           JOIN usuarios u ON c.barbero_id = u.id
                           JOIN servicios s ON c.servicio_id = s.id
                           JOIN sucursales suc ON c.sucursal_id = suc.id
                           JOIN clientes cli ON c.cliente_id = cli.id
                           WHERE DATE(c.fecha_hora) = ? $whereCitasAliased
                           ORDER BY c.fecha_hora ASC", [$hoy]);

    // 7. Retención
    $totalHoyCitas = count($agendaGlobal);
    $recurrentes = 0;
    foreach ($agendaGlobal as $c) {
        $historial = query("SELECT COUNT(*) as n FROM citas WHERE cliente_id = ? AND estado = 'completada'", [$c['cliente_id']])[0]['n'] ?? 0;
        if ($historial > 1) $recurrentes++;
    }
    $retentionRate = $totalHoyCitas > 0 ? round(($recurrentes / $totalHoyCitas) * 100) : 0;

    // 8. Gráfica 7 Días
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

    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    $mesActual = $meses[date('F')] ?? date('F');
    ?>

    <style>
        .earnings-card {
            background: #FFFFFF !important;
            border: 1px solid #E5E5E5 !important;
            color: #111 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border-radius: 12px !important;
            padding: 20px !important;
        }
        .earnings-card .earnings-title {
            color: #666;
            font-size: 0.82em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .earnings-card .earnings-amount {
            color: #111;
            font-weight: 800;   
            font-size: 1.8rem;
            margin: 6px 0;
        }
        .earnings-card .trend-indicator {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 0.82rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .modal-overlay.active {
            display: flex !important;
        }
        .btn-ver-todos-meses {
            background: #10B981;
            color: #FFFFFF !important;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.72rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .btn-ver-todos-meses:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35);
        }
    </style>

    <script>
        // Función global accesible inmediatamente para abrir el modal de ganancias netas
        window.abrirModalMetricasNetas = function(e) {
            if (e) {
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
                if (typeof e.preventDefault === 'function') e.preventDefault();
            }
            const modal = document.getElementById('modalMetricasNetasHistoricas');
            if (modal) {
                modal.style.setProperty('display', 'flex', 'important');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        };

        window.cerrarModalMetricasNetas = function(e) {
            if (e && e.target && e.target.id !== 'modalMetricasNetasHistoricas' && !e.target.closest('.btn-cerrar-modal-netas')) {
                return;
            }
            const modal = document.getElementById('modalMetricasNetasHistoricas');
            if (modal) {
                modal.style.setProperty('display', 'none', 'important');
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        };
    </script>

    <div class="dashboard-grid">
        <!-- Venta Día (Citas + Productos) -->
        <div class="earnings-card">
            <div class="earnings-title">Venta Total (Hoy)</div>
            <div class="earnings-amount">$<?php echo number_format($ventaTotalHoy, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $citasCompletadasHoy; ?> citas • <?php echo $itemsVendidosHoy; ?> prods</span>
            </div>
        </div>

        <!-- Recaudación Mensual (Citas + Productos) -->
        <div class="earnings-card">
            <div class="earnings-title">Recaudación Total (Mes)</div>
            <div class="earnings-amount">$<?php echo number_format($recaudacionMesTotal, 2); ?></div>
            <div class="trend-indicator trend-up">
                <span><?php echo $mesActual; ?> (<?php echo $citasCompletadasMes; ?> citas)</span>
            </div>
        </div>

        <!-- GANANCIAS NETAS DEL NEGOCIO (Descontando comisiones de barberos) -->
        <div id="cardGananciasNetas" class="earnings-card" onclick="window.abrirModalMetricasNetas(event)" style="border: 1.5px solid #10B981 !important; background: #F0FDF4 !important; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.08); cursor: pointer; transition: transform 0.18s ease, box-shadow 0.18s ease;" title="Haz clic para ver el desglose histórico de todos los meses">
            <div class="earnings-title" style="color: #047857 !important; font-weight: 800; display: flex; justify-content: space-between; align-items: center;">
                <span>Ganancias Netas (Mes)</span>
                <span style="background: #10B981; color: #FFFFFF; font-size: 0.65rem; padding: 2px 7px; border-radius: 4px; font-weight: 900; letter-spacing: 0.5px;">NEGOCIO</span>
            </div>
            <div class="earnings-amount" style="color: #065F46 !important; font-weight: 900; font-size: 1.85rem;">$<?php echo number_format($gananciaNetaMesTotal, 2); ?></div>
            <div class="trend-indicator" style="color: #047857; font-weight: 700; display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 0.78rem;">Cortes: $<?php echo number_format($gananciaNetaMesServicios, 2); ?> • Ventas: $<?php echo number_format($gananciaNetaMesProd, 2); ?></span>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; border-top: 1px dashed rgba(16, 185, 129, 0.3); padding-top: 6px;">
                    <span style="font-size: 0.72rem; color: #059669; font-weight: 600;">Hoy neto: +$<?php echo number_format($gananciaNetaHoyTotal, 2); ?></span>
                    <button type="button" id="btnVerTodosMeses" class="btn-ver-todos-meses" onclick="event.stopPropagation(); window.abrirModalMetricasNetas(event);">
                        📊 Ver Todos los Meses →
                    </button>
                </div>
            </div>
        </div>

        <!-- Ticket Promedio (Mes) -->
        <div class="earnings-card">
            <div class="earnings-title">Ticket Promedio</div>
            <div class="earnings-amount">$<?php echo number_format($ticketPromedioMes, 2); ?></div>
            <div class="trend-indicator">
                <span>Por cita este mes</span>
            </div>
        </div>

        <!-- Propinas Recaudadas (Mes) -->
        <div class="earnings-card">
            <div class="earnings-title">Propinas (Mes)</div>
            <div class="earnings-amount" style="color: #27AE60 !important;">$<?php echo number_format($totalPropinasMes, 2); ?></div>
            <div class="trend-indicator" style="color: #27AE60;">
                <span>Total propinas barberos</span>
            </div>
        </div>

        <!-- Valor Inventario -->
        <div class="earnings-card">
            <div class="earnings-title">Valor Stock Productos</div>
            <div class="earnings-amount">$<?php echo number_format($valorInventario, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $totalItems; ?> unidades stock</span>
            </div>
        </div>

        <!-- Fidelización -->
        <div class="earnings-card" style="background: linear-gradient(135deg, #111111 0%, #2A2A2A 100%) !important; color: #FFF !important;">
            <div class="earnings-title" style="color: #AAA !important;">Fidelización Hoy</div>
            <div class="earnings-amount" style="color: #FFF !important;"><?php echo $retentionRate; ?>%</div>
            <div class="trend-indicator" style="color: #10B981;">
                <span><?php echo $recurrentes; ?> de <?php echo $totalHoyCitas; ?> citas son recurrentes</span>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE BARRAS SEMANAL -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title">Tendencia de Ventas (7 Días) - <?php echo ($filterSucursalId > 0 ? 'Sede Seleccionada' : 'Global'); ?></div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 150px; padding-top: 20px;">
            <?php foreach ($last7Days as $day):
                $height = ($day['val'] / $maxVal) * 100;
                $color = $day['val'] > 0 ? 'var(--primary-gold)' : '#333';
                ?>
                <div style="text-align: center; width: 100%;">
                    <div style="font-size: 10px; color: #BBB; margin-bottom: 5px;">$<?php echo (int) $day['val']; ?></div>
                    <div style="height: <?php echo $height; ?>%; background: <?php echo $color; ?>; width: 60%; margin: 0 auto; border-radius: 4px 4px 0 0; min-height: 4px;"></div>
                    <div style="margin-top: 8px; font-size: 11px; color: #888;"><?php echo $day['date']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- Ranking Barberos -->
        <div style="flex: 1; min-width: 300px;">
            <div class="card">
                <div class="card-title">🏆 Top Barberos Global (Mes)</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barbero / Sede</th>
                                <th style="text-align: right;">Ventas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($topBarberos): ?>
                                <?php foreach ($topBarberos as $b): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?php echo htmlspecialchars($b['nombre']); ?></div>
                                            <div style="font-size: 10px; color: #888;"><?php echo htmlspecialchars($b['sucursal']); ?></div>
                                        </td>
                                        <td style="text-align: right; color: var(--primary-gold); font-weight: bold;">
                                            $<?php echo number_format($b['total'], 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center; color: #666;">Sin datos aún</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventario Bajo -->
        <div style="flex: 1; min-width: 300px;">
            <div class="card">
                <div class="card-title">⚠️ Alerta Stock Global</div>
                 <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto / Sede</th>
                                <th>Cant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lowStock): ?>
                                <?php foreach ($lowStock as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?php echo htmlspecialchars($p['producto']); ?></div>
                                            <div style="font-size: 10px; color: #888;"><?php echo htmlspecialchars($p['sucursal']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge-cancelada" style="background: rgba(231, 76, 60, 0.2); color: #e74c3c;">
                                                <?php echo $p['cantidad']; ?> / <?php echo $p['stock_minimo']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center;">Todo en orden</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- AGENDA GENERAL DE HOY (VISTA DE ANCHO COMPLETO Y CONTROL INTERACTIVO) -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>Agenda General de Hoy (<?php echo date('d/m/Y'); ?>)</span>
            </div>
            <a href="citas.php" style="font-size: 11px; color: var(--primary-gold); text-decoration: none; font-weight: 700;">Ver Todas las Citas →</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Barbero</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Sucursal</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($agendaGlobal): ?>
                        <?php foreach ($agendaGlobal as $cita): ?>
                            <tr>
                                <td style="font-weight: bold; color: var(--primary-gold);">
                                    <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($cita['barbero']); ?></td>
                                <td>
                                    <div style="font-weight:bold;"><?php echo htmlspecialchars($cita['cliente']); ?></div>
                                    <?php if (!empty($cita['telefono'])): 
                                        $wa_phone = formatPhoneForWhatsapp($cita['telefono']);
                                        $wa_msg = urlencode("Hola " . explode(' ', $cita['cliente'])[0] . ", te escribo de Kortzen sobre tu cita.");
                                        $raw_phone = preg_replace('/[^0-9+]/', '', $cita['telefono']);
                                    ?>
                                        <div style="font-size: 11px; margin-top: 4px; display: flex; gap: 8px; align-items: center;">
                                            <span style="color:#888;"><?php echo htmlspecialchars($cita['telefono']); ?></span>
                                            
                                            <!-- WA Button -->
                                            <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank" title="Enviar WhatsApp" style="color: #25D366; text-decoration: none;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" /></svg>
                                            </a>

                                            <!-- Call Button -->
                                            <a href="tel:<?php echo $raw_phone; ?>" title="Llamar" style="color: var(--primary-gold); text-decoration: none;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cita['servicio']); ?></td>
                                <td><span style="font-size: 11px; color: #888888; font-weight: 700;"><?php echo htmlspecialchars($cita['sucursal_nombre'] ?? 'Kortzen'); ?></span></td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <select id="select_estado_<?php echo $cita['id']; ?>" onchange="prepararGuardarEstado(<?php echo $cita['id']; ?>)" style="padding: 6px 10px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; border: 1px solid currentColor; outline: none; background: <?php echo ($cita['estado'] === 'completada' ? 'rgba(46, 204, 113, 0.15)' : ($cita['estado'] === 'en_atencion' ? 'rgba(52, 152, 219, 0.15)' : ($cita['estado'] === 'confirmada' ? 'rgba(241, 196, 15, 0.15)' : ($cita['estado'] === 'cancelada' ? 'rgba(231, 76, 60, 0.15)' : 'rgba(149, 165, 166, 0.15)')))); ?>; color: <?php echo ($cita['estado'] === 'completada' ? '#27ae60' : ($cita['estado'] === 'en_atencion' ? '#2980b9' : ($cita['estado'] === 'confirmada' ? '#d35400' : ($cita['estado'] === 'cancelada' ? '#c0392b' : '#7f8c8d')))); ?>;">
                                            <option value="pendiente" <?php echo $cita['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                            <option value="confirmada" <?php echo $cita['estado'] === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                            <option value="en_atencion" <?php echo $cita['estado'] === 'en_atencion' ? 'selected' : ''; ?>>En Atención</option>
                                            <option value="completada" <?php echo $cita['estado'] === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                            <option value="cancelada" <?php echo $cita['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                        </select>
                                        <button type="button" id="btn_guardar_<?php echo $cita['id']; ?>" onclick="guardarEstadoDirecto(<?php echo $cita['id']; ?>, '<?php echo htmlspecialchars(addslashes($cita['cliente'])); ?>')" style="padding: 6px 14px; border-radius: 6px; background: #111111; color: #FFFFFF; border: 1px solid rgba(255,255,255,0.15); font-weight: 800; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: all 0.2s;" title="Guardar cambio de estado en toda la plataforma">
                                            <i class="fas fa-check-circle" style="font-size: 11px; color: var(--primary-gold);"></i> Guardar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">No hay citas registradas para el día de hoy.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CALENDARIO DE OCUPACIÓN -->
    <?php
    // Lógica del Calendario
    $calMonth = date('m');
    $calYear = date('Y');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
    $firstDayOfMonth = date('N', strtotime("$calYear-$calMonth-01")); // 1 (Mon) to 7 (Sun)

    // Obtener días con citas (delimitado a sucursales asignadas)
    $bookings = query("SELECT DATE(fecha_hora) as d, COUNT(*) as c FROM citas WHERE MONTH(fecha_hora) = ? AND YEAR(fecha_hora) = ? $whereCitasDirect GROUP BY d", [$calMonth, $calYear]);

    // Mapear citas por día
    $bookingsMap = [];
    foreach ($bookings as $b) {
        $dayNum = (int) date('d', strtotime($b['d']));
        $bookingsMap[$dayNum] = $b['c'];
    }
    ?>
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 16px;
        }

        .cal-day-header {
            text-align: center;
            font-size: 11px;
            color: #888;
            padding-bottom: 8px;
            text-transform: uppercase;
        }

        .cal-day {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            height: 80px;
            padding: 8px;
            position: relative;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .cal-day:hover {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }

        .cal-number {
            font-size: 14px;
            font-weight: 600;
            color: #DDD;
        }

        .cal-dots {
            display: flex;
            gap: 3px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .cal-dot {
            width: 6px;
            height: 6px;
            background: var(--primary-gold);
            border-radius: 50%;
        }

        .cal-badge {
            font-size: 10px;
            background: #333;
            padding: 2px 6px;
            border-radius: 4px;
            color: #AAA;
            margin-top: 4px;
            display: inline-block;
        }

        .has-bookings {
            background: rgba(201, 169, 110, 0.05);
            border-color: rgba(201, 169, 110, 0.2);
        }

        .is-today {
            border: 1px solid var(--primary-gold);
        }
    </style>

    <div class="card" style="margin-top: 24px;">
        <div class="card-title">Calendario de Ocupación - <?php echo $mesActual . ' ' . $calYear; ?></div>

        <div class="calendar-grid">
            <div class="cal-day-header">Lun</div>
            <div class="cal-day-header">Mar</div>
            <div class="cal-day-header">Mie</div>
            <div class="cal-day-header">Jue</div>
            <div class="cal-day-header">Vie</div>
            <div class="cal-day-header">Sab</div>
            <div class="cal-day-header">Dom</div>

            <?php
            // Espacios vacíos antes del día 1
            for ($i = 1; $i < $firstDayOfMonth; $i++) {
                echo "<div></div>";
            }

            // Días del mes
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $count = $bookingsMap[$day] ?? 0;
                $class = $count > 0 ? 'cal-day has-bookings' : 'cal-day';
                if ($day == date('d'))
                    $class .= ' is-today';

                $onclick = "onclick=\"loadDayDetails($day)\"";
                $style = "cursor: pointer;";

                echo "<div class='$class' $onclick style='$style'>";
                echo "<div class='cal-number'>$day</div>";

                if ($count > 0) {
                    echo "<div class='cal-badge'>$count citas</div>";
                    echo "<div class='cal-dots'>";
                    // Mostrar hasta 5 puntitos visuales
                    for ($k = 0; $k < min($count, 5); $k++)
                        echo "<div class='cal-dot'></div>";
                    if ($count > 5)
                        echo "<span style='font-size:10px;color:#666;'>+</span>";
                    echo "</div>";
                }

                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- DETALLES DEL DÍA SELECCIONADO (Oculto por defecto) -->
    <div id="day-details-container" class="card" style="margin-top: 24px; display: none; transition: opacity 0.3s ease;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="day-details-title">Detalles del Día</span>
            <button onclick="document.getElementById('day-details-container').style.display='none'"
                style="background:none; border:none; color: #888; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>
        <div id="day-details-content">
            <!-- Table dynamically populated -->
        </div>
        <div id="day-details-summary"
            style="margin-top: 15px; text-align: right; font-size: 1.2rem; color: var(--primary-gold); font-weight: bold; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <!-- Total revenue -->
        </div>
    </div>

    <script>
        function loadDayDetails(day) {
            // Construct date string YYYY-MM-DD
            const year = <?php echo $calYear; ?>;
            // Ensure month is 2 digits for consistency
            const month = "<?php echo str_pad($calMonth, 2, '0', STR_PAD_LEFT); ?>";
            const dayStr = String(day).padStart(2, '0');
            const fullDate = `${year}-${month}-${dayStr}`;
            const branchId = "<?php echo $filterSucursalId > 0 ? $filterSucursalId : ''; ?>";

            const container = document.getElementById('day-details-container');
            const content = document.getElementById('day-details-content');
            const summary = document.getElementById('day-details-summary');
            const title = document.getElementById('day-details-title');

            // Show container with loading state
            container.style.display = 'block';
            title.innerHTML = `Detalles del <span style="color: #111111; font-weight: 800;">${dayStr}/${month}/${year}</span>`;
            content.innerHTML = '<p style="text-align:center; color:#888; padding: 20px;">Cargando citas...</p>';
            summary.innerHTML = '';

            // Scroll to details
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Fetch
            let url = `api/get_citas_dia.php?fecha=${fullDate}`;
            if (branchId) url += `&sucursal_id=${branchId}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.citas && data.citas.length > 0) {
                            let html = `
                            <div class="table-container" style="margin: 0; overflow-x: auto;">
                                <table class="table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                    <thead>
                                        <tr style="background: #F8FAFC; border-bottom: 1.5px solid #E2E8F0; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748B;">
                                            <th style="padding: 10px 14px;">Hora</th>
                                            <th style="padding: 10px 14px;">Cliente</th>
                                            <th style="padding: 10px 14px;">Servicio</th>
                                            <th style="padding: 10px 14px;">Barbero</th>
                                            <th style="padding: 10px 14px;">Sucursal</th>
                                            <th style="padding: 10px 14px;">Precio</th>
                                            <th style="padding: 10px 14px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;

                            data.citas.forEach(cita => {
                                const timePart = cita.fecha_hora ? cita.fecha_hora.split(' ')[1].substring(0, 5) : '--:--';
                                let badgeBg = '#E5E7EB';
                                let badgeColor = '#374151';
                                if (cita.estado === 'completada') { badgeBg = '#DCFCE7'; badgeColor = '#166534'; }
                                else if (cita.estado === 'confirmada') { badgeBg = '#DBEAFE'; badgeColor = '#1E40AF'; }
                                else if (cita.estado === 'pendiente') { badgeBg = '#FEF3C7'; badgeColor = '#92400E'; }
                                else if (cita.estado === 'cancelada') { badgeBg = '#FEE2E2'; badgeColor = '#991B1B'; }

                                html += `
                                <tr style="border-bottom: 1px solid #F1F5F9;">
                                    <td style="padding: 10px 14px; font-weight: 800; color: #111111;">${timePart}</td>
                                    <td style="padding: 10px 14px; font-weight: 600; color: #111111;">${cita.cliente}</td>
                                    <td style="padding: 10px 14px; color: #4B5563;">${cita.servicio}</td>
                                    <td style="padding: 10px 14px; color: #4B5563;">${cita.barbero}</td>
                                    <td style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #6B7280;">📍 ${cita.sucursal_nombre || ''}</td>
                                    <td style="padding: 10px 14px; font-weight: 800; color: #10B981;">$${parseFloat(cita.precio_final).toFixed(2)}</td>
                                    <td style="padding: 10px 14px;"><span style="background: ${badgeBg}; color: ${badgeColor}; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase;">${cita.estado}</span></td>
                                </tr>
                                `;
                            });

                            html += `</tbody></table></div>`;
                            content.innerHTML = html;
                            summary.innerHTML = `<span style="color: #64748B; font-size: 0.95rem; font-weight: 700; margin-right: 12px;">${data.total_citas} citas</span> Recaudación: <span style="color: #10B981; font-weight: 900;">$${parseFloat(data.total_recaudado).toFixed(2)}</span>`;
                        } else {
                            content.innerHTML = '<p style="text-align:center; padding: 20px; color: #888;">No hubo citas registradas este día.</p>';
                            summary.innerHTML = 'Recaudación: $0.00';
                        }
                    } else {
                        content.innerHTML = `<p style="color:red; text-align:center;">Error: ${data.message}</p>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<p style="color:red; text-align:center;">Error de conexión.</p>';
                });
        }
    </script>

    <!-- MODAL GENERAL: MÉTRICAS HISTÓRICAS Y GANANCIAS NETAS DE TODOS LOS MESES -->
    <div id="modalMetricasNetasHistoricas" class="modal-overlay" style="display: none; position: fixed; inset: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999999; justify-content: center; align-items: center; padding: 16px; box-sizing: border-box;" onclick="if(event.target === this) window.cerrarModalMetricasNetas(event)">
        <div class="modal-content" style="background: #FFFFFF; width: 100%; max-width: 960px; max-height: 90vh; overflow-y: auto; padding: 28px; border-radius: 18px; box-shadow: 0 25px 50px rgba(0,0,0,0.35); color: #111; position: relative;" onclick="event.stopPropagation()">
            
            <!-- Botón Cerrar Superior -->
            <button type="button" class="btn-cerrar-modal-netas" onclick="window.cerrarModalMetricasNetas(event)" style="position: absolute; top: 20px; right: 20px; background: #F3F4F6; border: none; width: 36px; height: 36px; border-radius: 50%; font-size: 1.4rem; color: #4B5563; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                &times;
            </button>

            <!-- Encabezado Modal -->
            <div style="margin-bottom: 24px; padding-right: 40px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; color: #047857; font-weight: 900; font-size: 0.72rem; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    <i class="fas fa-chart-line"></i> Reporte Financiero Histórico
                </div>
                <h2 style="margin: 0; font-size: 1.45rem; font-weight: 900; color: #111111; line-height: 1.2;">
                    Ganancias Netas del Negocio — Todos los Meses
                </h2>
                <p style="margin: 6px 0 0 0; color: #64748B; font-size: 0.88rem;">
                    Desglose histórico consolidado mes a mes deduciendo automáticamente las comisiones pagadas a barberos en servicios y productos.
                </p>
            </div>

            <!-- KPIs Globales Históricos -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 26px;">
                <div style="background: #111111; color: #FFFFFF; border-radius: 12px; padding: 16px; border: 1.5px solid #111111;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px;">Ganancia Neta Total Histórica</div>
                    <div style="font-size: 1.6rem; font-weight: 900; color: #10B981; margin: 4px 0;">$<?php echo number_format($histTotalNetoNegocio, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #CBD5E1;">Ingreso real retenido por el negocio</div>
                </div>

                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Facturación Bruta Total</div>
                    <div style="font-size: 1.6rem; font-weight: 900; color: #0F172A; margin: 4px 0;">$<?php echo number_format($histTotalBruto, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #64748B;">Total cobrado a clientes</div>
                </div>

                <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #92400E; text-transform: uppercase; letter-spacing: 0.5px;">Comisiones a Barberos</div>
                    <div style="font-size: 1.6rem; font-weight: 900; color: #B45309; margin: 4px 0;">-$<?php echo number_format($histTotalComisiones, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #92400E;">Repartido al personal</div>
                </div>

                <div style="background: #F0FDF4; border: 1px solid #A7F3D0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px;">Margen Neto Negocio</div>
                    <div style="font-size: 1.6rem; font-weight: 900; color: #047857; margin: 4px 0;"><?php echo number_format($margenHistoricoNegocio, 1); ?>%</div>
                    <div style="font-size: 0.75rem; color: #065F46;"><?php echo number_format($histTotalCitas); ?> cortes • <?php echo number_format($histTotalVentasProd); ?> prods</div>
                </div>
            </div>

            <!-- Tabla Desglose Mes a Mes -->
            <div style="border: 1px solid #E2E8F0; border-radius: 14px; overflow: hidden; margin-bottom: 20px;">
                <div style="background: #F8FAFC; padding: 14px 18px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; font-weight: 900; color: #1E293B; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-calendar-alt"></i> Historial Desglosado Mes a Mes
                    </span>
                    <span style="font-size: 0.78rem; font-weight: 700; color: #64748B;">
                        <?php echo count($mesesHistoricosMap); ?> meses registrados
                    </span>
                </div>

                <div class="table-container" style="max-height: 380px; overflow-y: auto; margin: 0;">
                    <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #FFFFFF; border-bottom: 1.5px solid #E2E8F0; font-size: 0.75rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 12px 16px;">Mes / Año</th>
                                <th style="padding: 12px 16px; text-align: center;">Cortes & Ventas</th>
                                <th style="padding: 12px 16px; text-align: right;">Facturación Bruta</th>
                                <th style="padding: 12px 16px; text-align: right;">Comisiones Barberos</th>
                                <th style="padding: 12px 16px; text-align: right; background: #F0FDF4; color: #047857;">Ganancia Neta Negocio</th>
                                <th style="padding: 12px 16px; text-align: center;">Margen %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mesesHistoricosMap)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #94A3B8;">
                                        No hay registros de citas completadas ni ventas aún.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mesesHistoricosMap as $mKey => $mData): 
                                    $brutoTotal = $mData['bruto_servicios'] + $mData['bruto_productos'];
                                    $comisionTotal = $mData['comision_servicios'] + $mData['comision_productos'];
                                    $netoTotal = $mData['neto_servicios'] + $mData['neto_productos'];
                                    $margenMes = $brutoTotal > 0 ? (($netoTotal / $brutoTotal) * 100) : 100;
                                    $esMesActual = ($mKey === date('Y-m'));
                                ?>
                                    <tr style="border-bottom: 1px solid #F1F5F9; <?php echo $esMesActual ? 'background: #FAFDFB;' : ''; ?>">
                                        <td style="padding: 14px 16px; font-weight: 800; color: #0F172A;">
                                            <?php echo htmlspecialchars($mData['mes_nombre']); ?>
                                            <?php if ($esMesActual): ?>
                                                <span style="background: #10B981; color: #FFFFFF; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: 800;">EN CURSO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: center; font-size: 0.85rem; color: #475569;">
                                            <strong><?php echo $mData['citas_completadas']; ?></strong> cortes • <strong><?php echo $mData['ventas_productos']; ?></strong> prods
                                        </td>
                                        <td style="padding: 14px 16px; text-align: right; font-weight: 700; color: #334155; font-size: 0.95rem;">
                                            $<?php echo number_format($brutoTotal, 2); ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: right; font-weight: 700; color: #D97706; font-size: 0.95rem;">
                                            -$<?php echo number_format($comisionTotal, 2); ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: right; font-weight: 900; color: #047857; font-size: 1.05rem; background: #F0FDF4;">
                                            +$<?php echo number_format($netoTotal, 2); ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: center;">
                                            <span style="background: #E2E8F0; color: #334155; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 6px;">
                                                <?php echo number_format($margenMes, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Botón de Cerrar Modal -->
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cerrar-modal-netas" onclick="window.cerrarModalMetricasNetas(event)" style="padding: 10px 24px; background: #111111; color: #FFFFFF; border: none; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; text-transform: uppercase;">
                    Cerrar Reporte
                </button>
            </div>
        </div>
    </div>

    <script>
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (typeof window.cerrarModalMetricasNetas === 'function') {
                    window.cerrarModalMetricasNetas();
                }
            }
        });
    </script>

<?php else: ?>
    <!-- ========================================== -->
    <!-- VISTA BARBERO MEJORADA (SUPER DASHBOARD) -->
    <!-- ========================================== -->

    <?php
    $barbero_id = $currentUser['id'];
    $hoy = date('Y-m-d');

    // 1. CALCULAR GANANCIAS CON DOBLE TASA (Diaria vs Fin de Semana)
    $com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
    $com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);

    // Lógica SQL para calcular ganancia basada en el día de la cita
    // DAYOFWEEK: 1=Domingo, 7=Sábado => IN (1, 7) = Fin de Semana
    $sqlGanancia = "
        SUM(
            (IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100) 
            + 
            (IFNULL((SELECT SUM(vp.cantidad * vp.precio_unitario) FROM ventas_productos vp WHERE vp.cita_id = citas.id), 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)
        ) as total_ganancia
    ";

    // 1.1 Ganancia HOY
    $gananciaHoy = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
    ", [$barbero_id]);
    $miGananciaDia = $gananciaHoy[0]['total_ganancia'] ?? 0;

    // 1.2 Ganancia SEMANA
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    $gananciaSemana = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monday, $sunday]);
    $miGananciaSemana = $gananciaSemana[0]['total_ganancia'] ?? 0;

    // 1.3 Ganancia MES
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $gananciaMes = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monthStart, $monthEnd]);
    $miGananciaMes = $gananciaMes[0]['total_ganancia'] ?? 0;

    // 2. PRÓXIMO CLIENTE...
    // ... (restcode kept)
    $nextClient = query("SELECT c.*, s.nombre as servicio, cli.nombre as cliente, cli.foto_perfil
                         FROM citas c
                         JOIN servicios s ON c.servicio_id = s.id
                         JOIN clientes cli ON c.cliente_id = cli.id
                         WHERE c.barbero_id = ? 
                         AND c.fecha_hora >= NOW()
                         AND c.estado IN ('pendiente', 'confirmada')
                         ORDER BY c.fecha_hora ASC 
                         LIMIT 1",
        [$barbero_id]
    );

    $proximo = $nextClient ? $nextClient[0] : null;

    // 3. CITAS DE HOY (Lista Completa)
    $misCitasHoy = query("SELECT c.*, s.nombre as servicio, cli.nombre as cliente, cli.telefono, cli.id as cliente_id
                          FROM citas c
                          JOIN servicios s ON c.servicio_id = s.id
                          JOIN clientes cli ON c.cliente_id = cli.id
                          WHERE c.barbero_id = ? 
                          AND DATE(c.fecha_hora) = ?
                          AND c.estado != 'cancelada'
                          ORDER BY c.fecha_hora ASC",
        [$barbero_id, $hoy]
    );
    ?>

    <!-- SECCIÓN DE GANANCIAS -->
    <div class="dashboard-grid">
        <div class="earnings-card">
            <!-- Si quieres mostrar el %, podrías poner "Var" o ambos ej: 50% / 60% -->
            <div class="earnings-title">Tu Ganancia Hoy (Dinámica)</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaDia, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsTodayTotal, 2); ?></div>
            <div class="trend-indicator trend-up">
                <span style="display:inline-flex; align-items:center; gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> <?php echo date('d M'); ?></span>
            </div>
        </div>
        <div class="earnings-card" style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="earnings-title">Tu Ganancia Semana</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaSemana, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsWeekTotal, 2); ?></div>
            <div class="trend-indicator">
                <span>Semana <?php echo date('W'); ?></span>
            </div>
        </div>
        <div class="earnings-card" style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="earnings-title">Tu Ganancia Mes</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaMes, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsMonthTotal, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo date('F Y'); ?></span>
            </div>
        </div>
    </div>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- COLUMNA IZQUIERDA: Next Up + Acciones -->
        <div style="flex: 1; min-width: 300px;">

            <?php if ($proximo): ?>
                <div class="next-client-card">
                    <div class="next-time">
                        <div class="next-hour"><?php echo date('H:i', strtotime($proximo['fecha_hora'])); ?></div>
                        <div class="next-label">Próximo</div>
                    </div>
                    <div class="client-details">
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($proximo['cliente']); ?></h3>
                        <span class="service-badge"><?php echo htmlspecialchars($proximo['servicio']); ?></span>
                        <div style="margin-top: 8px; font-size: 12px; color: #888;">
                            <?php
                            $minutos = round((strtotime($proximo['fecha_hora']) - time()) / 60);
                            if ($minutos < 0)
                                echo "En curso (hace " . abs($minutos) . " min)";
                            else
                                echo "En $minutos minutos";
                            ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>¡Todo listo! No tienes más clientes pendientes por hoy.</span>
                </div>
            <?php endif; ?>

            <!-- ACCIONES RÁPIDAS -->
            <h3 class="card-title">Acciones Rápidas</h3>
            <div class="action-buttons-grid">
                <div class="quick-action-btn" onclick="crearCitaRapida()">
                    <span style="display: flex; justify-content: center; align-items: center; height: 28px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </span>
                    <span>Agendar Cita</span>
                </div>
                <div class="quick-action-btn" onclick="bloquearHora()">
                    <span style="display: flex; justify-content: center; align-items: center; height: 28px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                    </span>
                    <span>Bloqueo 1h</span>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Lista de Hoy -->
        <div style="flex: 2; min-width: 400px;">
            <div class="card">
                <div class="card-title">
                    Agenda de Hoy
                    <span style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"><?php echo count($misCitasHoy); ?>
                        citas</span>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($misCitasHoy as $cita): ?>
                                <tr>
                                    <td style="font-weight: bold; color: var(--primary-gold);">
                                        <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($cita['cliente']); ?></div>
                                        <div
                                            style="font-size: 0.85em; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                            <?php echo htmlspecialchars($cita['telefono']); ?>
                                        </div>
                                        <a href="#"
                                            onclick="verHistorial(<?php echo $cita['cliente_id']; ?>, '<?php echo htmlspecialchars($cita['cliente']); ?>')"
                                            style="font-size: 11px; color: var(--text-muted); text-decoration: underline;">Ver
                                            Historial</a>
                                    </td>
                                    <td><?php echo htmlspecialchars($cita['servicio']); ?></td>
                                    <td>
                                        <span
                                            class="badge badge-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if ($cita['estado'] === 'pendiente' || $cita['estado'] === 'confirmada'): ?>
                                            <button onclick="abrirModalTerminar(<?php echo $cita['id']; ?>)"
                                                class="btn btn-sm btn-primary" style="margin-right: 5px;">Terminar</button>
                                            <button onclick="confirmarCancelar(<?php echo $cita['id']; ?>)"
                                                class="btn btn-sm btn-delete"
                                                style="background:transparent; border: 1px solid #E74C3C; color: #E74C3C;">Cancelar</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL HISTORIAL CLIENTE -->
    <div id="modalHistorial" class="modal-overlay"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: #1A1A1A; width: 500px; padding: 30px; border-radius: 12px; border: 1px solid #333;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalClientName" style="color: var(--primary-gold); margin: 0;">Historial</h3>
                <button onclick="document.getElementById('modalHistorial').style.display='none'"
                    style="background: none; border: none; color: #FFF; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="historialContent" style="max-height: 400px; overflow-y: auto;">
                <p style="text-align: center; color: #666;">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- MODAL TERMINAR CITA (Añadido para funcionalidad de botones) -->
    <div id="modalTerminar" class="modal-overlay"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: #1A1A1A; width: 500px; max-width: 90%; padding: 30px; border-radius: 12px; border: 1px solid #333;">
            <h2 style="margin-bottom: 20px; color: var(--primary-gold);">Terminar Cita</h2>
            <p style="margin-bottom: 20px; color: #AAA;">Confirma que has completado el servicio.</p>

            <form id="formTerminar" method="POST" action="api/citas_action.php">
                <input type="hidden" name="action" value="completar">
                <input type="hidden" name="id" id="citaIdTerminar">
                <input type="hidden" name="redirect_source" value="dashboard">

                <!-- Para futura expansión de inventario -->
                <!-- <div id="materialesList"></div> -->

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" onclick="document.getElementById('modalTerminar').style.display='none'"
                        class="btn-secondary"
                        style="flex: 1; padding: 12px; border-radius: 6px; cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-primary"
                        style="flex: 1; padding: 12px; border-radius: 6px; border: none; cursor: pointer;">Confirmar
                        Completado</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funciones de Cita (Terminar/Cancelar)
        function confirmarCancelar(id) {
            if (confirm('¿Realmente deseas cancelar esta cita?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'api/citas_action.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'cancelar_barbero';

                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_source';
                redirectInput.value = 'dashboard';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;

                form.appendChild(actionInput);
                form.appendChild(redirectInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function abrirModalTerminar(id) {
            document.getElementById('citaIdTerminar').value = id;
            document.getElementById('modalTerminar').style.display = 'flex';
        }

        // Funciones del Dashboard original
        function verHistorial(clientId, clientName) {
            document.getElementById('modalHistorial').style.display = 'flex';
            document.getElementById('modalClientName').textContent = 'Historial: ' + clientName;
            document.getElementById('historialContent').innerHTML = '<p style="text-align: center; color: #666;">Cargando datos...</p>';

            fetch(`api/get_client_history.php?client_id=${clientId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historialContent');
                    if (data.success && data.history.length > 0) {
                        let html = '';
                        data.history.forEach(item => {
                            html += `
                                <div class="history-item">
                                    <div>
                                        <div class="history-service">${item.servicio_nombre}</div>
                                        <div class="history-date">${new Date(item.fecha_hora).toLocaleDateString()} - ${item.notas || 'Sin notas'}</div>
                                    </div>
                                    <div class="history-price">$${parseFloat(item.precio_final).toFixed(2)}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p style="text-align: center; color: #888;">No hay historial previo disponible.</p>';
                    }
                })
                .catch(err => {
                    document.getElementById('historialContent').innerHTML = '<p style="color: red;">Error al cargar.</p>';
                });
        }

        function bloquearHora() {
            if (!confirm('¿Bloquear la próxima hora disponible en tu agenda para descanso/gestión?')) return;

            fetch('api/block_time_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=quick_block_next_hour'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.error);
                    }
                });
        }

        function crearCitaRapida() {
            window.location.href = 'citas_crear.php';
        }

        let citaEstadoPendienteId = 0;
        let citaEstadoPendienteEstado = '';

        function prepararGuardarEstado(id) {
            const select = document.getElementById('select_estado_' + id);
            if (!select) return;
            const nuevoEstado = select.value;

            const bgMap = {
                'completada': 'rgba(46, 204, 113, 0.15)',
                'en_atencion': 'rgba(52, 152, 219, 0.15)',
                'confirmada': 'rgba(241, 196, 15, 0.15)',
                'cancelada': 'rgba(231, 76, 60, 0.15)',
                'pendiente': 'rgba(149, 165, 166, 0.15)'
            };
            const colorMap = {
                'completada': '#27ae60',
                'en_atencion': '#2980b9',
                'confirmada': '#d35400',
                'cancelada': '#c0392b',
                'pendiente': '#7f8c8d'
            };
            select.style.background = bgMap[nuevoEstado] || 'rgba(149, 165, 166, 0.15)';
            select.style.color = colorMap[nuevoEstado] || '#7f8c8d';
        }

        function guardarEstadoDirecto(id, clienteNombre) {
            const select = document.getElementById('select_estado_' + id);
            if (!select) return;
            const nuevoEstado = select.value;

            citaEstadoPendienteId = id;
            citaEstadoPendienteEstado = nuevoEstado;

            // Si es completada, abrir modal para propina opcional
            if (nuevoEstado === 'completada') {
                const modal = document.getElementById('modalGuardarEstadoOverview');
                const desc = document.getElementById('modalGuardarEstadoDesc');
                const propinaSec = document.getElementById('modalPropinaSection');
                const propinaInput = document.getElementById('modalPropinaInput');

                desc.innerHTML = 'Vas a marcar la cita del cliente <strong>' + clienteNombre + '</strong> como <span style="color:#27ae60; font-weight:800;">COMPLETADA</span>.<br><br>Se acreditarán los puntos de fidelidad al cliente y se enviará la notificación instantánea PWA.';
                propinaSec.style.display = 'block';
                propinaInput.value = '0.00';
                modal.style.display = 'flex';
            } else {
                // Guardar directamente vía AJAX para otros estados
                const btn = document.getElementById('btn_guardar_' + id);
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                }

                ejecutarGuardadoEstadoAjax(id, nuevoEstado, 0.00, btn);
            }
        }

        function cerrarModalGuardarEstado() {
            document.getElementById('modalGuardarEstadoOverview').style.display = 'none';
        }

        function confirmarModalGuardarEstado() {
            if (!citaEstadoPendienteId || !citaEstadoPendienteEstado) return;
            const btn = document.getElementById('btnConfirmarGuardarEstado');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            }
            const propinaVal = parseFloat(document.getElementById('modalPropinaInput').value || 0);

            ejecutarGuardadoEstadoAjax(citaEstadoPendienteId, citaEstadoPendienteEstado, propinaVal, btn);
        }

        function ejecutarGuardadoEstadoAjax(id, estado, propina, btnElement) {
            const formData = new FormData();
            formData.append('action', 'cambiar_estado');
            formData.append('id', id);
            formData.append('estado', estado);
            formData.append('propina', propina);
            formData.append('ajax', '1');
            formData.append('redirect_source', 'dashboard');

            fetch('api/citas_action.php', {
                method: 'POST',
                body: formData,
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error al guardar: ' + (data.error || data.message || 'Ocurrió un problema en el servidor.'));
                    if (btnElement) {
                        btnElement.disabled = false;
                        btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Guardar';
                    }
                }
            })
            .catch(err => {
                window.location.reload();
            });
        }
    </script>

    <!-- Modal Guardar Estado Cita Overview -->
    <div id="modalGuardarEstadoOverview" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: #FFFFFF; width: 90%; max-width: 440px; padding: 24px; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); color: #111;">
            <h3 id="modalGuardarEstadoTitulo" style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: #111; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-check-circle" style="color: #10B981;"></i> Finalizar Cita y Registrar Estado
            </h3>
            <p id="modalGuardarEstadoDesc" style="color: #555; font-size: 0.88rem; margin-bottom: 16px; line-height: 1.4;"></p>
            
            <div id="modalPropinaSection" style="display: none; margin-bottom: 16px; background: #F9FAFB; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 14px;">
                <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #111; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                    Propina para el Barbero ($)
                </label>
                <input type="number" step="0.01" min="0" id="modalPropinaInput" value="0.00" style="width: 100%; padding: 10px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 1.05rem; font-weight: 800; box-sizing: border-box;">
                <span style="font-size: 0.75rem; color: #6B7280; margin-top: 4px; display: block;">Opcional. Se registrará la propina e incrementarán los puntos de fidelidad del cliente.</span>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="cerrarModalGuardarEstado()" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #CCC; background: #FFF; font-weight: 700; cursor: pointer; color: #444;">Cancelar</button>
                <button type="button" id="btnConfirmarGuardarEstado" onclick="confirmarModalGuardarEstado()" style="flex: 1.2; padding: 12px; border-radius: 8px; border: none; background: #111111; color: #FFF; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fas fa-check" style="color: var(--primary-gold);"></i> Guardar Estado
                </button>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
    // Check for URL parameters for alerts
    window.onload = function () {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const error = urlParams.get('error');

        if (success) {
            alert('✅ ' + success);
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (error) {
            alert('❌ Error: ' + error);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
