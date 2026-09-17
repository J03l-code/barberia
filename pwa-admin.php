<?php
/**
 * KORTZEN - PWA Exclusiva para Administradores
 * Interfaz 100% nativa móvil aislada de la plataforma web de escritorio.
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
        $sucursalesList = query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
    } catch (Exception $e) {
        $sucursalesList = [];
    }
    if ($filterSucursalId > 0) {
        $scopedBranchIds = [$filterSucursalId];
    } else {
        $scopedBranchIds = [];
    }
}

// Obtener barberos de las sucursales permitidas
$barberosList = [];
try {
    if ($userRol === 'admin_local') {
        if (!empty($scopedBranchIds)) {
            $inList = implode(',', array_fill(0, count($scopedBranchIds), '?'));
            $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, s.nombre AS sucursal_nombre 
                                    FROM usuarios u 
                                    LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                    WHERE u.rol IN ('barbero', 'admin_local') 
                                      AND (u.sucursal_id IN ($inList) OR EXISTS (SELECT 1 FROM usuarios_sucursales us WHERE us.usuario_id = u.id AND us.sucursal_id IN ($inList)))
                                    ORDER BY u.nombre ASC");
            $stmt->execute(array_merge($scopedBranchIds, $scopedBranchIds));
            $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        if ($filterSucursalId > 0) {
            $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, s.nombre AS sucursal_nombre 
                                    FROM usuarios u 
                                    LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                    WHERE u.rol IN ('barbero', 'admin_local') AND u.sucursal_id = ?
                                    ORDER BY u.nombre ASC");
            $stmt->execute([$filterSucursalId]);
            $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT u.id, u.nombre, u.email, COALESCE(u.foto_url, '') AS foto_url, u.sucursal_id, s.nombre AS sucursal_nombre 
                                 FROM usuarios u 
                                 LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                                 WHERE u.rol IN ('barbero', 'admin_local') 
                                 ORDER BY u.nombre ASC");
            $barberosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $barberosList = [];
}

// Servicios para modal de agendamiento rápido
$serviciosList = [];
try {
    $serviciosList = query("SELECT id, nombre, duracion_minutos, precio FROM servicios WHERE activo = 1 ORDER BY nombre ASC");
} catch (Exception $e) {
    $serviciosList = [];
}

// Métricas de Overview para la pestaña Overview
$kpiHoy = ['ventas' => 0.00, 'citas' => 0, 'prods' => 0];
$kpiMes = ['ventas' => 0.00, 'citas' => 0];
$kpiPropinasMes = 0.00;
$kpiReferidosMes = ['descuentos' => 0.00, 'total_referidos' => 0];

try {
    // Venta Hoy
    $sqlVH = "SELECT SUM(COALESCE(c.precio_final, s.precio, 0)) as total_ventas, COUNT(c.id) as total_citas 
              FROM citas c 
              LEFT JOIN servicios s ON c.servicio_id = s.id 
              WHERE DATE(c.fecha_hora) = CURDATE() AND c.estado = 'completada'";
    if (!empty($scopedBranchIds)) {
        $inB = implode(',', array_map('intval', $scopedBranchIds));
        $sqlVH .= " AND c.sucursal_id IN ($inB)";
    }
    $resVH = query($sqlVH);
    if (!empty($resVH)) {
        $kpiHoy['ventas'] = floatval($resVH[0]['total_ventas'] ?? 0);
        $kpiHoy['citas'] = intval($resVH[0]['total_citas'] ?? 0);
    }

    // Recaudación Mes
    $sqlVM = "SELECT SUM(COALESCE(c.precio_final, s.precio, 0)) as total_ventas, COUNT(c.id) as total_citas, SUM(COALESCE(c.propina, 0)) as total_propinas 
              FROM citas c 
              LEFT JOIN servicios s ON c.servicio_id = s.id 
              WHERE MONTH(c.fecha_hora) = MONTH(CURDATE()) AND YEAR(c.fecha_hora) = YEAR(CURDATE()) AND c.estado = 'completada'";
    if (!empty($scopedBranchIds)) {
        $inB = implode(',', array_map('intval', $scopedBranchIds));
        $sqlVM .= " AND c.sucursal_id IN ($inB)";
    }
    $resVM = query($sqlVM);
    if (!empty($resVM)) {
        $kpiMes['ventas'] = floatval($resVM[0]['total_ventas'] ?? 0);
        $kpiMes['citas'] = intval($resVM[0]['total_citas'] ?? 0);
        $kpiPropinasMes = floatval($resVM[0]['total_propinas'] ?? 0);
    }

    // Descuentos Referidos Mes
    $sqlRef = "SELECT SUM(COALESCE(descuento_aplicado, 0)) as total_desc, COUNT(id) as total_ref 
               FROM citas 
               WHERE MONTH(fecha_hora) = MONTH(CURDATE()) AND YEAR(fecha_hora) = YEAR(CURDATE()) 
                 AND codigo_promocional IS NOT NULL AND TRIM(codigo_promocional) != '' AND estado != 'cancelada'";
    if (!empty($scopedBranchIds)) {
        $inB = implode(',', array_map('intval', $scopedBranchIds));
        $sqlRef .= " AND sucursal_id IN ($inB)";
    }
    $resRef = query($sqlRef);
    if (!empty($resRef)) {
        $kpiReferidosMes['descuentos'] = floatval($resRef[0]['total_desc'] ?? 0);
        $kpiReferidosMes['total_referidos'] = intval($resRef[0]['total_ref'] ?? 0);
    }
} catch (Exception $eKpi) {}

$activeTab = $_GET['tab'] ?? 'agenda';
$nombreAdmin = $currentUser['nombre'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>KORTZEN Admin App</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-pwa: #FAF9F6;
            --bg-card: #FFFFFF;
            --border-pwa: #EAEAEA;
            --text-dark: #111111;
            --text-gray: #777777;
            --text-light: #999999;
            --gold-pwa: #C0A062;
            --green-pwa: #10B981;
            --red-pwa: #EF4444;
            --orange-pwa: #F59E0B;
            --shadow-pwa: 0 4px 20px rgba(0,0,0,0.05);
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
            padding-bottom: calc(75px + var(--safe-bottom));
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .pwa-app-container {
            max-width: 540px;
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
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: calc(var(--safe-top) + 8px) 16px 12px 16px;
            border-bottom: 1px solid var(--border-pwa);
            z-index: 80;
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
            font-size: 1.15rem;
            transition: background 0.15s;
        }

        .pwa-icon-btn:active {
            background: rgba(0,0,0,0.05);
        }

        .pwa-month-selector {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            text-transform: capitalize;
            user-select: none;
        }

        .pwa-month-selector span {
            color: var(--text-gray);
            font-weight: 400;
        }

        .pwa-top-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pwa-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--text-dark);
            color: #FFFFFF;
            font-size: 0.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border-pwa);
            cursor: pointer;
        }

        /* --- Date Strip (Week Carousel) --- */
        .pwa-date-strip-container {
            background: #FFFFFF;
            padding: 12px 16px 14px 16px;
            border-bottom: 1px solid var(--border-pwa);
        }

        .pwa-date-strip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .pwa-branch-pill {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-gray);
            background: #F4F4F4;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .pwa-strip-nav {
            display: flex;
            gap: 6px;
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
            font-size: 0.75rem;
            cursor: pointer;
            color: var(--text-dark);
        }

        .pwa-nav-btn:active {
            background: #E5E5E5;
        }

        .pwa-date-strip {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            text-align: center;
        }

        .pwa-day-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            padding: 6px 0;
            border-radius: 12px;
            transition: all 0.15s;
        }

        .pwa-day-col:active {
            background: #F9F9F9;
        }

        .pwa-day-col .pwa-day-letter {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-gray);
            text-transform: uppercase;
        }

        .pwa-day-col .pwa-day-num {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            position: relative;
            transition: all 0.2s;
        }

        .pwa-day-col.is-today .pwa-day-num {
            background: #F4EFE6;
            color: var(--gold-pwa);
            font-weight: 800;
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

        /* --- Barber Chips --- */
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

        /* --- Feed & Cards (Image 1) --- */
        .pwa-tab-content {
            display: none;
            padding: 16px;
            animation: fadeIn 0.2s ease-in-out;
        }

        .pwa-tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pwa-day-group {
            margin-bottom: 24px;
        }

        .pwa-day-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pwa-avail-badge {
            font-size: 0.72rem;
            font-weight: 700;
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

        /* Live current time line */
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

        /* Availability Accordion */
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

        /* --- Overview KPI Cards --- */
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
            padding: 16px;
            box-shadow: var(--shadow-pwa);
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
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--text-dark);
        }

        .pwa-kpi-sub {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 4px;
            font-weight: 600;
        }

        /* --- Floating Button (+) --- */
        .pwa-fab {
            position: fixed;
            bottom: calc(85px + var(--safe-bottom));
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #111111;
            color: #FFFFFF;
            border: none;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 40;
            transition: transform 0.15s;
        }

        .pwa-fab:active {
            transform: scale(0.93);
        }

        /* --- Bottom Summary Card (Image 1) --- */
        .pwa-summary-pill {
            position: fixed;
            bottom: calc(65px + var(--safe-bottom));
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 500px;
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

        /* --- Bottom Tab Bar --- */
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
            z-index: 70;
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

        /* --- Side Drawer (Image 2) --- */
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
            padding: 10px 16px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }

        .pwa-drawer-item:active {
            background: #F4F4F4;
        }

        /* Bottom Action Sheet Modal */
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
            max-width: 540px;
            border-radius: 20px 20px 0 0;
            padding: 20px 20px calc(var(--safe-bottom) + 20px) 20px;
            animation: slideUp 0.2s ease-out;
            max-height: 85vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .pwa-btn-main {
            background: #111111;
            color: #FFFFFF;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            width: 100%;
        }
    </style>
</head>
<body>

<div class="pwa-app-container">
    <!-- Top Bar -->
    <header class="pwa-top-bar">
        <button class="pwa-icon-btn" onclick="abrirDrawer()" aria-label="Menú">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div class="pwa-month-selector" id="pwaTopTitle" onclick="abrirDrawer()">
            <span id="txtMes">septiembre</span> <span id="txtAnio">2026</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>

        <div class="pwa-top-right">
            <button class="pwa-icon-btn" onclick="cambiarPwaTab('citas')" aria-label="Notificaciones">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
            <div class="pwa-avatar" onclick="cambiarPwaTab('equipo')">
                <?php echo strtoupper(substr($nombreAdmin, 0, 2)); ?>
            </div>
        </div>
    </header>

    <!-- TAB 1: AGENDA & DISPONIBILIDAD (Image 1 & 2) -->
    <section id="tabAgenda" class="pwa-tab-content <?php echo $activeTab === 'agenda' ? 'active' : ''; ?>">
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

            <div class="pwa-date-strip" id="pwaDateStripDays">
                <!-- Días cargados por JS -->
            </div>
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
                <p style="margin-top: 10px; font-weight: 600;">Cargando agenda en tiempo real...</p>
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

    <!-- TAB 2: OVERVIEW & MÉTRICAS -->
    <section id="tabOverview" class="pwa-tab-content <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
        <div style="margin-bottom: 16px;">
            <h2 style="font-size: 1.25rem; font-weight: 900; color: var(--text-dark);">Overview General</h2>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-top: 2px;">Rendimiento del negocio en tiempo real</p>
        </div>

        <div class="pwa-kpi-grid">
            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Venta Total (Hoy)</div>
                <div class="pwa-kpi-value">$<?php echo number_format($kpiHoy['ventas'], 2); ?></div>
                <div class="pwa-kpi-sub"><?php echo $kpiHoy['citas']; ?> citas completadas</div>
            </div>

            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Recaudación (Mes)</div>
                <div class="pwa-kpi-value">$<?php echo number_format($kpiMes['ventas'], 2); ?></div>
                <div class="pwa-kpi-sub"><?php echo $kpiMes['citas']; ?> citas en el mes</div>
            </div>

            <div class="pwa-kpi-card">
                <div class="pwa-kpi-title">Propinas (Mes)</div>
                <div class="pwa-kpi-value" style="color: var(--green-pwa);">+$<?php echo number_format($kpiPropinasMes, 2); ?></div>
                <div class="pwa-kpi-sub">Directas a barberos</div>
            </div>

            <div class="pwa-kpi-card" style="background: #F5F3FF; border-color: #DDD6FE;">
                <div class="pwa-kpi-title" style="color: #7C3AED;">Desc. Referidos</div>
                <div class="pwa-kpi-value" style="color: #6D28D9;">-$<?php echo number_format($kpiReferidosMes['descuentos'], 2); ?></div>
                <div class="pwa-kpi-sub" style="color: #7C3AED;"><?php echo $kpiReferidosMes['total_referidos']; ?> citas con código</div>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1.5px solid var(--border-pwa); border-radius: 14px; padding: 16px; margin-bottom: 20px;">
            <div style="font-weight: 800; font-size: 0.95rem; margin-bottom: 12px; display: flex; justify-content: space-between;">
                <span>Accesos Rápidos</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button onclick="cambiarPwaTab('agenda')" style="background: #111; color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                    📅 Ver Agenda
                </button>
                <button onclick="abrirModalNuevaCitaPwa()" style="background: #F4F4F4; color: #111; border: none; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                    + Nueva Cita
                </button>
            </div>
        </div>
    </section>

    <!-- TAB 3: GESTIÓN DE CITAS -->
    <section id="tabCitas" class="pwa-tab-content <?php echo $activeTab === 'citas' ? 'active' : ''; ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 900; color: var(--text-dark);">Citas Agendadas</h2>
                <p style="font-size: 0.8rem; color: var(--text-gray);">Historial y reservas en vivo</p>
            </div>
            <button onclick="abrirModalNuevaCitaPwa()" style="background: #111; color: #fff; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer;">
                + CITA
            </button>
        </div>

        <div id="pwaCitasListContainer">
            <!-- Citas cargadas dinámicamente -->
        </div>
    </section>

    <!-- TAB 4: EQUIPO & DISPONIBILIDAD -->
    <section id="tabEquipo" class="pwa-tab-content <?php echo $activeTab === 'equipo' ? 'active' : ''; ?>">
        <div style="margin-bottom: 16px;">
            <h2 style="font-size: 1.25rem; font-weight: 900; color: var(--text-dark);">Equipo de Barberos</h2>
            <p style="font-size: 0.8rem; color: var(--text-gray);">Consulta turnos libres de cada profesional</p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($barberosList as $b): ?>
                <div style="background: #FFFFFF; border: 1.5px solid var(--border-pwa); border-radius: 14px; padding: 14px; box-shadow: var(--shadow-pwa);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 42px; height: 42px; border-radius: 50%; background: #111; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                <?php echo strtoupper(substr($b['nombre'], 0, 2)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-dark);"><?php echo htmlspecialchars($b['nombre']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-gray);"><?php echo htmlspecialchars($b['sucursal_nombre'] ?? 'Kortzen'); ?></div>
                            </div>
                        </div>
                        <button onclick="verDisponibilidadBarberoModal(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nombre']); ?>')" style="background: #10B981; color: #FFFFFF; border: none; padding: 7px 12px; border-radius: 8px; font-size: 0.78rem; font-weight: 800; cursor: pointer;">
                            Ver Horarios
                        </button>
                    </div>
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
        <button class="pwa-tab-btn <?php echo $activeTab === 'agenda' ? 'active' : ''; ?>" onclick="cambiarPwaTab('agenda')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <span>Agenda</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" onclick="cambiarPwaTab('overview')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <span>Overview</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'citas' ? 'active' : ''; ?>" onclick="cambiarPwaTab('citas')">
            <div class="pwa-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            </div>
            <span>Citas</span>
        </button>
        <button class="pwa-tab-btn <?php echo $activeTab === 'equipo' ? 'active' : ''; ?>" onclick="cambiarPwaTab('equipo')">
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

<!-- Drawer / Sidebar (Image 2) -->
<div class="pwa-drawer-mask" id="pwaDrawerMask" onclick="cerrarDrawer()"></div>
<aside class="pwa-drawer-content" id="pwaDrawerContent">
    <div class="pwa-drawer-promo">
        <h3>KORTZEN PRO Admin</h3>
        <p>Control integral de disponibilidad y citas en tiempo real.</p>
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
        Gestión de la Plataforma
    </div>
    <a href="inventario.php" class="pwa-drawer-item">
        <i class="fas fa-boxes" style="width: 20px;"></i>
        <span>Inventario Central</span>
    </a>
    <a href="servicios.php" class="pwa-drawer-item">
        <i class="fas fa-cut" style="width: 20px;"></i>
        <span>Servicios & Precios</span>
    </a>
    <a href="sucursales.php" class="pwa-drawer-item">
        <i class="fas fa-store" style="width: 20px;"></i>
        <span>Sucursales & Sedes</span>
    </a>
    <a href="clientes.php" class="pwa-drawer-item">
        <i class="fas fa-user-friends" style="width: 20px;"></i>
        <span>Directorio de Clientes</span>
    </a>
    <a href="resenas.php" class="pwa-drawer-item">
        <i class="fas fa-star" style="width: 20px;"></i>
        <span>Reseñas & Feedback</span>
    </a>
    <a href="galeria_admin.php" class="pwa-drawer-item">
        <i class="fas fa-images" style="width: 20px;"></i>
        <span>Galería Web</span>
    </a>
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

<script>
let startWeekStr = '<?php echo date('Y-m-d', strtotime('monday this week')); ?>';
let endWeekStr = '<?php echo date('Y-m-d', strtotime('sunday this week')); ?>';
let selectedAgendaDate = '<?php echo date('Y-m-d'); ?>';
let selectedAgendaBarber = 0;
let agendaJsonData = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarDatosAgendaPwa();
});

function cambiarPwaTab(tabName) {
    document.querySelectorAll('.pwa-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pwa-tab-btn').forEach(b => b.classList.remove('active'));

    const tabEl = document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
    if (tabEl) tabEl.classList.add('active');

    const btn = Array.from(document.querySelectorAll('.pwa-tab-btn')).find(b => b.innerText.toLowerCase().includes(tabName.toLowerCase()));
    if (btn) btn.classList.add('active');

    if (tabName === 'agenda') {
        cargarDatosAgendaPwa();
    } else if (tabName === 'citas') {
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
                const hOut = c.hora_fin ? formatHoraPwa(f + ' ' + c.hora_fin) : '';
                const timeRange = hOut ? `${hIn} - ${hOut}` : hIn;
                const statusClass = 'status-' + (c.estado || 'pendiente');

                citasHtml += `
                    <div class="pwa-cita-card ${statusClass}" onclick="abrirModalDetalleCitaPwa(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="pwa-cita-header">
                            <div class="pwa-cita-client">${escapeHtml(c.cliente_nombre)}</div>
                            <div class="pwa-cita-service">${escapeHtml(c.servicio_nombre)}</div>
                        </div>
                        <div class="pwa-cita-meta">
                            <div>${timeRange}</div>
                            <div class="pwa-barber-tag">${escapeHtml(c.barbero_nombre)}</div>
                        </div>
                    </div>
                `;
            });
        } else {
            citasHtml = `<div class="pwa-day-empty">Nada planeado</div>`;
        }

        let liveLineHtml = (f === today) ? `<div class="pwa-live-time"><div class="pwa-live-dot"></div><div class="pwa-live-line"></div></div>` : '';

        // Acordeón de Slots
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
            <div style="background: #FFFFFF; border: 1.5px solid var(--border-pwa); border-radius: 12px; padding: 14px; margin-bottom: 10px; box-shadow: var(--shadow-pwa);" onclick="abrirModalDetalleCitaPwa(${JSON.stringify(c).replace(/"/g, '&quot;')})">
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

function verDisponibilidadBarberoModal(barberoId, nombre) {
    selectedAgendaBarber = barberoId;
    cambiarPwaTab('agenda');
    // Activar chip correspondiente
    document.querySelectorAll('.pwa-chip').forEach(c => {
        if (parseInt(c.getAttribute('data-barbero-id')) === barberoId) {
            c.classList.add('active');
        } else {
            c.classList.remove('active');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

</body>
</html>
