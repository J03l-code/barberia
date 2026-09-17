<?php
/**
 * KORTZEN - Agenda & Visualizador Gráfico de Disponibilidad en Tiempo Real
 * Interfaz PWA y Web Nativa para Administradores (estilo Setmore / Fresha)
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
    $pdo = getConnection();
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

$pageTitle = 'Agenda & Disponibilidad';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $pageTitle; ?> - KORTZEN</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-app: #FAF9F6;
            --bg-card: #FFFFFF;
            --border-color: #EAEAEA;
            --text-primary: #111111;
            --text-secondary: #777777;
            --text-muted: #999999;
            --accent-gold: #C0A062;
            --accent-green: #10B981;
            --accent-red: #EF4444;
            --accent-orange: #F59E0B;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.06);
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
            background-color: var(--bg-app);
            color: var(--text-primary);
            padding-bottom: calc(75px + var(--safe-bottom));
            overflow-x: hidden;
        }

        /* Container Limit for Desktop Preview while matching native app */
        .app-layout {
            max-width: 600px;
            margin: 0 auto;
            min-height: 100vh;
            background: #FFFFFF;
            position: relative;
            box-shadow: 0 0 30px rgba(0,0,0,0.03);
        }

        @media (min-width: 993px) {
            .app-layout {
                max-width: 800px;
                margin: 20px auto;
                border-radius: 24px;
                overflow: hidden;
                border: 1px solid #EAEAEA;
                box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            }
        }

        /* --- Top Bar --- */
        .top-bar {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: calc(var(--safe-top) + 8px) 16px 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            z-index: 50;
        }

        .top-bar-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            font-size: 1.15rem;
            transition: background 0.15s;
        }

        .top-bar-btn:active {
            background: rgba(0,0,0,0.05);
        }

        .month-selector-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            cursor: pointer;
            user-select: none;
            text-transform: capitalize;
        }

        .month-selector-title span {
            color: var(--text-secondary);
            font-weight: 400;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #111111;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            overflow: hidden;
            border: 2px solid #EAEAEA;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- Horizontal Week Date Strip --- */
        .date-strip-container {
            background: #FFFFFF;
            padding: 12px 16px 16px 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .date-strip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .strip-branch-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-secondary);
            background: #F4F4F4;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .strip-nav-arrows {
            display: flex;
            gap: 6px;
        }

        .strip-arrow-btn {
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
            color: var(--text-primary);
        }

        .strip-arrow-btn:active {
            background: #E5E5E5;
        }

        .date-strip {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            text-align: center;
        }

        .date-day-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            padding: 6px 0;
            border-radius: 12px;
            transition: all 0.15s;
        }

        .date-day-col:active {
            background: #F9F9F9;
        }

        .date-day-col .day-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .date-day-col .day-num-box {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            position: relative;
            transition: all 0.2s;
        }

        .date-day-col.is-today .day-num-box {
            background: #F4EFE6;
            color: var(--accent-gold);
            font-weight: 800;
        }

        .date-day-col.is-selected .day-num-box {
            background: #111111 !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .date-day-col .today-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--accent-gold);
            position: absolute;
            bottom: 3px;
        }

        .date-day-col.is-selected .today-dot {
            background: #FFFFFF;
        }

        /* --- Barber Filter Strip --- */
        .barber-filter-bar {
            padding: 10px 16px;
            background: #FAFAFA;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .barber-filter-bar::-webkit-scrollbar {
            display: none;
        }

        .barber-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s;
        }

        .barber-chip.active {
            background: #111111;
            color: #FFFFFF;
            border-color: #111111;
        }

        .barber-chip-avatar {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #CCCCCC;
            display: inline-block;
            font-size: 9px;
            line-height: 18px;
            text-align: center;
            color: #111;
            font-weight: 700;
        }

        .barber-chip.active .barber-chip-avatar {
            background: #FFFFFF;
            color: #111;
        }

        /* --- Agenda Feed (Image 1) --- */
        .agenda-feed {
            padding: 16px;
            min-height: 500px;
        }

        .agenda-day-group {
            margin-bottom: 24px;
            position: relative;
        }

        .agenda-day-header {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .agenda-day-header .avail-quick-btn {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--accent-green);
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 3px 8px;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .agenda-day-empty {
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 0 12px 0;
        }

        /* Appointment Card (Image 1) */
        .cita-card {
            background: #FFF5F2; /* Soft warm background like image */
            border-left: 4px solid #E05638; /* Warm orange/red accent */
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.1s;
            position: relative;
        }

        .cita-card.status-completada {
            background: #F0FDF4;
            border-left-color: #10B981;
        }

        .cita-card.status-pendiente {
            background: #FFFBEB;
            border-left-color: #F59E0B;
        }

        .cita-card.status-confirmada {
            background: #FFF5F2;
            border-left-color: #E05638;
        }

        .cita-card:active {
            transform: scale(0.99);
        }

        .cita-card-header {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }

        .cita-client-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .cita-service-name {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .cita-time-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: #555555;
        }

        .cita-barber-badge {
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(0,0,0,0.06);
            padding: 2px 7px;
            border-radius: 4px;
            color: #333333;
        }

        /* Live Current Time Line */
        .live-time-indicator {
            position: relative;
            display: flex;
            align-items: center;
            margin: 14px 0;
            z-index: 10;
        }

        .live-time-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #111111;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .live-time-line {
            flex: 1;
            height: 1.5px;
            background: #111111;
        }

        /* Availability Accordion Slots Box */
        .avail-slots-box {
            background: #F9FAFB;
            border: 1px dashed #D1D5DB;
            border-radius: 10px;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 14px;
            display: none;
        }

        .avail-slots-box.open {
            display: block;
        }

        .avail-barber-section {
            margin-bottom: 10px;
        }

        .avail-barber-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .slots-pills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .slot-pill {
            background: #FFFFFF;
            border: 1px solid #10B981;
            color: #065F46;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .slot-pill:hover, .slot-pill:active {
            background: #10B981;
            color: #FFFFFF;
        }

        /* --- Floating Action Button (+) --- */
        .fab-btn {
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

        .fab-btn:active {
            transform: scale(0.93);
        }

        /* --- Bottom Summary Pill (Image 1) --- */
        .bottom-summary-pill {
            position: fixed;
            bottom: calc(65px + var(--safe-bottom));
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 500px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
            z-index: 35;
        }

        .summary-pill-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .summary-pill-amount {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* --- Bottom Navigation Bar --- */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 4px calc(var(--safe-bottom) + 4px) 4px;
            z-index: 60;
        }

        .nav-tab-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.68rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 8px;
            transition: color 0.15s;
        }

        .nav-tab-item.active {
            color: var(--text-primary);
            font-weight: 800;
        }

        .nav-tab-icon {
            font-size: 1.15rem;
            position: relative;
        }

        .nav-tab-badge {
            background: #111111;
            color: #FFFFFF;
            font-size: 0.55rem;
            padding: 1px 4px;
            border-radius: 10px;
            position: absolute;
            top: -2px;
            right: -6px;
        }

        /* --- Drawer / Sidebar Menu (Image 2) --- */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s, visibility 0.25s;
        }

        .drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .drawer-panel {
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

        .drawer-panel.open {
            transform: translateX(0);
        }

        .drawer-header-card {
            background: #E8F5E9; /* Soft light mint banner like image 2 */
            margin: 16px;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
        }

        .drawer-header-card h3 {
            font-size: 0.95rem;
            font-weight: 800;
            color: #111111;
            margin-bottom: 4px;
        }

        .drawer-header-card p {
            font-size: 0.75rem;
            color: #4B5563;
            line-height: 1.3;
            margin-bottom: 12px;
        }

        .drawer-header-card .btn-drawer-pro {
            background: #111111;
            color: #FFFFFF;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }

        .drawer-section {
            padding: 12px 16px;
        }

        .drawer-section-title {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .drawer-mode-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            cursor: pointer;
            margin-bottom: 4px;
            transition: background 0.15s;
        }

        .drawer-mode-item.active {
            background: #F4F4F4;
        }

        .drawer-team-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s;
            margin-bottom: 4px;
        }

        .drawer-team-item:active, .drawer-team-item.active {
            background: #F4F4F4;
        }

        .drawer-team-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #EAEAEA;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: #111111;
            flex-shrink: 0;
            overflow: hidden;
        }

        .drawer-team-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .drawer-team-name {
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .drawer-team-meta {
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        /* --- Modal Detalle / Acción Rápida --- */
        .modal-action-sheet {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 120;
            display: none;
            align-items: flex-end;
            justify-content: center;
        }

        .action-sheet-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 550px;
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

        .sheet-drag-handle {
            width: 40px;
            height: 4px;
            background: #DDDDDD;
            border-radius: 4px;
            margin: 0 auto 16px auto;
        }

        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .sheet-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .sheet-subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .sheet-close-btn {
            background: #F4F4F4;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .sheet-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .sheet-info-card {
            background: #F9F9F9;
            padding: 12px;
            border-radius: 10px;
        }

        .sheet-info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .sheet-info-value {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .sheet-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-sheet-primary {
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
        }

        .btn-sheet-secondary {
            background: #F4F4F4;
            color: #111111;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-sheet-danger {
            background: #FEE2E2;
            color: #DC2626;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Top Bar -->
    <header class="top-bar">
        <button class="top-bar-btn" id="btnOpenDrawer" onclick="abrirDrawer()" aria-label="Menú">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div class="month-selector-title" id="topMonthTitle" onclick="toggleMonthPicker()">
            <span id="labelMes">septiembre</span> <span id="labelAnio">2026</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>

        <div class="top-bar-right">
            <button class="top-bar-btn" onclick="window.location.href='citas.php'" aria-label="Notificaciones">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
            <div class="admin-avatar" onclick="window.location.href='usuarios.php'">
                <?php 
                $nombreAdmin = $currentUser['nombre'] ?? 'Admin';
                echo strtoupper(substr($nombreAdmin, 0, 2));
                ?>
            </div>
        </div>
    </header>

    <!-- Horizontal Date Strip (Week Carousel) -->
    <section class="date-strip-container">
        <div class="date-strip-header">
            <div class="strip-branch-badge" id="badgeSucursal">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <span id="nombreSucursalActiva">
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
            <div class="strip-nav-arrows">
                <button class="strip-arrow-btn" onclick="cambiarSemana(-1)" title="Semana anterior">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <button class="strip-arrow-btn" onclick="irAHoy()" title="Ir a hoy" style="font-size: 0.7rem; font-weight: 800; width: auto; padding: 0 8px; border-radius: 12px;">Hoy</button>
                <button class="strip-arrow-btn" onclick="cambiarSemana(1)" title="Semana siguiente">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </div>

        <div class="date-strip" id="dateStripDays">
            <!-- Renderizado dinámico vía JavaScript -->
        </div>
    </section>

    <!-- Barber Filter Chips Bar -->
    <section class="barber-filter-bar" id="barberFilterBar">
        <div class="barber-chip active" data-barbero-id="0" onclick="seleccionarBarberoFiltro(0, this)">
            <span class="barber-chip-avatar">ALL</span>
            <span>Todos los Horarios</span>
        </div>
        <?php foreach ($barberosList as $b): ?>
            <div class="barber-chip" data-barbero-id="<?php echo $b['id']; ?>" onclick="seleccionarBarberoFiltro(<?php echo $b['id']; ?>, this)">
                <span class="barber-chip-avatar"><?php echo strtoupper(substr($b['nombre'], 0, 2)); ?></span>
                <span><?php echo htmlspecialchars($b['nombre']); ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- Main Agenda Feed (Image 1) -->
    <main class="agenda-feed" id="agendaFeed">
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 10px; font-weight: 600;">Cargando horarios y disponibilidad...</p>
        </div>
    </main>

    <!-- Bottom Summary Card (Image 1) -->
    <div class="bottom-summary-pill" id="bottomSummaryPill">
        <div class="summary-pill-left">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                <line x1="6" y1="12" x2="18" y2="12"></line>
            </svg>
            <span>Ingresos de esta semana</span>
        </div>
        <div class="summary-pill-amount" id="totalIngresosSemana">$0.00</div>
    </div>

    <!-- Floating Action Button (+) -->
    <button class="fab-btn" onclick="abrirModalNuevaCita()" aria-label="Añadir cita rápida">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </button>

    <!-- Bottom Navigation App Bar -->
    <nav class="bottom-nav">
        <a href="admin-agenda.php" class="nav-tab-item active">
            <div class="nav-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <span>Calendario</span>
        </a>
        <a href="citas.php" class="nav-tab-item">
            <div class="nav-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <span>Connect</span>
        </a>
        <a href="citas.php" class="nav-tab-item">
            <div class="nav-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            </div>
            <span>Servicios</span>
        </a>
        <a href="usuarios.php" class="nav-tab-item">
            <div class="nav-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
            </div>
            <span>Clientes</span>
        </a>
        <a href="dashboard.php" class="nav-tab-item">
            <div class="nav-tab-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <span>Configuración</span>
        </a>
    </nav>
</div>

<!-- Drawer / Sidebar Modal (Image 2) -->
<div class="drawer-overlay" id="drawerOverlay" onclick="cerrarDrawer()"></div>
<aside class="drawer-panel" id="drawerPanel">
    <div class="drawer-header-card">
        <h3>KORTZEN PRO Admin</h3>
        <p>Sincronización en tiempo real de disponibilidad, turnos y citas.</p>
        <span class="btn-drawer-pro">Modo Administrador</span>
    </div>

    <div class="drawer-section">
        <div class="drawer-section-title">Modo de Vista</div>
        <div class="drawer-mode-item active" onclick="cambiarModoVista('agenda')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            <span>Agenda</span>
        </div>
        <div class="drawer-mode-item" onclick="cambiarModoVista('dia')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>
            <span>Día</span>
        </div>
        <div class="drawer-mode-item" onclick="cambiarModoVista('3dias')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="18" rx="1"></rect><rect x="10" y="3" width="5" height="18" rx="1"></rect><rect x="17" y="3" width="5" height="18" rx="1"></rect></svg>
            <span>3 Días</span>
        </div>
    </div>

    <?php if (count($sucursalesList) > 1 || $userRol === 'admin'): ?>
    <div class="drawer-section">
        <div class="drawer-section-title">Sucursales</div>
        <select id="drawerSucursalSelect" onchange="cambiarSucursal(this.value)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 700;">
            <option value="0" <?php echo ($filterSucursalId == 0) ? 'selected' : ''; ?>>Todas las Sucursales</option>
            <?php foreach ($sucursalesList as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo ($filterSucursalId == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="drawer-section">
        <div class="drawer-section-title">Mis Calendarios</div>
        <div class="drawer-team-item">
            <div class="drawer-team-avatar">
                <?php echo strtoupper(substr($nombreAdmin, 0, 2)); ?>
            </div>
            <div>
                <div class="drawer-team-name"><?php echo htmlspecialchars($nombreAdmin); ?></div>
                <div class="drawer-team-meta"><?php echo ucfirst(str_replace('_', ' ', $userRol)); ?></div>
            </div>
        </div>
    </div>

    <div class="drawer-section">
        <div class="drawer-section-title">Equipo</div>
        <div class="drawer-team-item active" onclick="seleccionarBarberoDrawer(0)">
            <div class="drawer-team-avatar" style="background: #111; color: #fff;">TODOS</div>
            <div>
                <div class="drawer-team-name">Todos los horarios</div>
                <div class="drawer-team-meta">Vista global combinada</div>
            </div>
        </div>
        <?php foreach ($barberosList as $b): ?>
            <div class="drawer-team-item" onclick="seleccionarBarberoDrawer(<?php echo $b['id']; ?>)">
                <div class="drawer-team-avatar">
                    <?php echo strtoupper(substr($b['nombre'], 0, 2)); ?>
                </div>
                <div>
                    <div class="drawer-team-name"><?php echo htmlspecialchars($b['nombre']); ?></div>
                    <div class="drawer-team-meta"><?php echo htmlspecialchars($b['sucursal_nombre'] ?? 'Kortzen'); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</aside>

<!-- Modal Detalle Cita / Action Sheet -->
<div class="modal-action-sheet" id="modalCitaSheet" onclick="if(event.target===this) cerrarModalCita()">
    <div class="action-sheet-content">
        <div class="sheet-drag-handle"></div>
        <div class="sheet-header">
            <div>
                <div class="sheet-title" id="sheetClienteNombre">Cliente</div>
                <div class="sheet-subtitle" id="sheetServicioNombre">Servicio</div>
            </div>
            <button class="sheet-close-btn" onclick="cerrarModalCita()">✕</button>
        </div>

        <div class="sheet-info-grid">
            <div class="sheet-info-card">
                <div class="sheet-info-label">Fecha y Hora</div>
                <div class="sheet-info-value" id="sheetFechaHora">-</div>
            </div>
            <div class="sheet-info-card">
                <div class="sheet-info-label">Barbero Asignado</div>
                <div class="sheet-info-value" id="sheetBarberoNombre">-</div>
            </div>
            <div class="sheet-info-card">
                <div class="sheet-info-label">Estado</div>
                <div class="sheet-info-value" id="sheetEstadoBadge">-</div>
            </div>
            <div class="sheet-info-card">
                <div class="sheet-info-label">Precio / Cobro</div>
                <div class="sheet-info-value" id="sheetPrecioFinal">-</div>
            </div>
        </div>

        <div class="sheet-actions" id="sheetActionsContainer">
            <!-- Botones dinámicos según estado de la cita -->
        </div>
    </div>
</div>

<!-- Modal Nueva Cita Rápida -->
<div class="modal-action-sheet" id="modalNuevaCitaSheet" onclick="if(event.target===this) cerrarModalNuevaCita()">
    <div class="action-sheet-content">
        <div class="sheet-drag-handle"></div>
        <div class="sheet-header">
            <div>
                <div class="sheet-title">Nueva Cita Rápida</div>
                <div class="sheet-subtitle">Agendamiento directo para el administrador</div>
            </div>
            <button class="sheet-close-btn" onclick="cerrarModalNuevaCita()">✕</button>
        </div>

        <form id="formNuevaCitaRapida" onsubmit="guardarNuevaCita(event)" style="display: flex; flex-direction: column; gap: 14px;">
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Nombre del Cliente</label>
                <input type="text" name="cliente_nombre" id="inputCliNombre" required placeholder="Ej: Juan Pérez" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 600; font-size: 0.95rem; margin-top: 4px;">
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Teléfono / WhatsApp</label>
                <input type="tel" name="cliente_telefono" id="inputCliTel" placeholder="Ej: 0991234567" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 600; font-size: 0.95rem; margin-top: 4px;">
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Barbero</label>
                <select name="barbero_id" id="inputBarberoId" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 700; font-size: 0.95rem; margin-top: 4px;">
                    <?php foreach ($barberosList as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['nombre']); ?> (<?php echo htmlspecialchars($b['sucursal_nombre'] ?? 'Kortzen'); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Servicio</label>
                <select name="servicio_id" id="inputServicioId" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 700; font-size: 0.95rem; margin-top: 4px;">
                    <?php foreach ($serviciosList as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?> - $<?php echo number_format($s['precio'], 2); ?> (<?php echo $s['duracion_minutos']; ?> min)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Fecha</label>
                    <input type="date" name="fecha" id="inputFechaCita" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 700; font-size: 0.9rem; margin-top: 4px;">
                </div>
                <div>
                    <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Hora</label>
                    <input type="time" name="hora" id="inputHoraCita" value="10:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 700; font-size: 0.9rem; margin-top: 4px;">
                </div>
            </div>

            <button type="submit" id="btnSubmitNuevaCita" class="btn-sheet-primary" style="margin-top: 10px;">Agendar Cita</button>
        </form>
    </div>
</div>

<script>
let currentStartDate = '<?php echo date('Y-m-d', strtotime('monday this week')); ?>';
let currentEndDate = '<?php echo date('Y-m-d', strtotime('sunday this week')); ?>';
let selectedDate = '<?php echo date('Y-m-d'); ?>';
let selectedBarberoId = 0;
let selectedSucursalId = <?php echo $filterSucursalId; ?>;
let agendaData = null;
let currentViewMode = 'agenda';

document.addEventListener('DOMContentLoaded', () => {
    cargarAgenda();
});

function cargarAgenda() {
    const feed = document.getElementById('agendaFeed');
    feed.innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 10px; font-weight: 600;">Cargando disponibilidad...</p>
        </div>
    `;

    const url = `api/get_agenda_admin.php?start_date=${currentStartDate}&end_date=${currentEndDate}&barbero_id=${selectedBarberoId}&sucursal_id=${selectedSucursalId}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                agendaData = data;
                actualizarHeaderMes(data.rango.mes_nombre);
                renderizarDateStrip(data);
                renderizarAgenda(data);
                if (data.metricas) {
                    document.getElementById('totalIngresosSemana').innerText = '$' + data.metricas.ingresos_semana;
                }
            } else {
                feed.innerHTML = `<div style="text-align: center; padding: 40px; color: red;">Error: ${data.error}</div>`;
            }
        })
        .catch(err => {
            feed.innerHTML = `<div style="text-align: center; padding: 40px; color: red;">Error al cargar datos.</div>`;
        });
}

function actualizarHeaderMes(mesNombre) {
    if (!mesNombre) return;
    const parts = mesNombre.split(' ');
    document.getElementById('labelMes').innerText = parts[0] || 'septiembre';
    document.getElementById('labelAnio').innerText = parts[1] || '2026';
}

function renderizarDateStrip(data) {
    const strip = document.getElementById('dateStripDays');
    strip.innerHTML = '';

    const diasNombresLetras = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    const todayStr = new Date().toISOString().split('T')[0];

    // Iterar 7 días desde currentStartDate
    let curr = new Date(currentStartDate + 'T00:00:00');
    for (let i = 0; i < 7; i++) {
        const fStr = curr.toISOString().split('T')[0];
        const dayOfWeek = curr.getDay();
        const dayLetter = diasNombresLetras[dayOfWeek];
        const dayNum = curr.getDate();

        const isToday = (fStr === todayStr);
        const isSelected = (fStr === selectedDate);

        const col = document.createElement('div');
        col.className = `date-day-col ${isToday ? 'is-today' : ''} ${isSelected ? 'is-selected' : ''}`;
        col.setAttribute('data-fecha', fStr);
        col.onclick = () => seleccionarDia(fStr);

        col.innerHTML = `
            <div class="day-name">${dayLetter}</div>
            <div class="day-num-box">
                ${dayNum}
                ${isToday ? '<div class="today-dot"></div>' : ''}
            </div>
        `;

        strip.appendChild(col);
        curr.setDate(curr.getDate() + 1);
    }
}

function seleccionarDia(fecha) {
    selectedDate = fecha;
    document.querySelectorAll('.date-day-col').forEach(el => {
        if (el.getAttribute('data-fecha') === fecha) {
            el.classList.add('is-selected');
        } else {
            el.classList.remove('is-selected');
        }
    });

    // Scroll to the day element in agenda feed
    const targetEl = document.getElementById('day-group-' + fecha);
    if (targetEl) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderizarAgenda(data) {
    const feed = document.getElementById('agendaFeed');
    feed.innerHTML = '';

    const diasNombresCompletos = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const mesesNombresCompletos = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const todayStr = new Date().toISOString().split('T')[0];

    let curr = new Date(currentStartDate + 'T00:00:00');

    for (let i = 0; i < 7; i++) {
        const fStr = curr.toISOString().split('T')[0];
        const dayOfWeek = curr.getDay();
        const dayNum = curr.getDate();
        const monthNum = curr.getMonth();

        const headerTitle = `${diasNombresCompletos[dayOfWeek]}, ${dayNum} ${mesesNombresCompletos[monthNum]}`;
        const dayCitas = (data.citas_por_fecha && data.citas_por_fecha[fStr]) ? data.citas_por_fecha[fStr] : [];
        const dayDisp = (data.disponibilidad && data.disponibilidad[fStr]) ? data.disponibilidad[fStr] : null;

        // Calcular total slots libres de todos los barberos en este día
        let totalLibresDia = 0;
        if (dayDisp && dayDisp.barberos) {
            dayDisp.barberos.forEach(b => {
                totalLibresDia += (b.total_slots_libres || 0);
            });
        }

        const groupDiv = document.createElement('div');
        groupDiv.className = 'agenda-day-group';
        groupDiv.id = 'day-group-' + fStr;

        let citasHtml = '';
        if (dayCitas.length > 0) {
            dayCitas.forEach(c => {
                const horaInicioFormatted = formatHora(c.fecha_hora);
                const horaFinFormatted = c.hora_fin ? formatHora(fStr + ' ' + c.hora_fin) : '';
                const timeRange = horaFinFormatted ? `${horaInicioFormatted} - ${horaFinFormatted}` : horaInicioFormatted;
                const statusClass = 'status-' + (c.estado || 'pendiente');

                citasHtml += `
                    <div class="cita-card ${statusClass}" onclick="abrirModalDetalleCita(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="cita-card-header">
                            <div class="cita-client-name">${escapeHtml(c.cliente_nombre)}</div>
                            <div class="cita-service-name">${escapeHtml(c.servicio_nombre)}</div>
                        </div>
                        <div class="cita-time-row">
                            <div>${timeRange}</div>
                            <div class="cita-barber-badge">${escapeHtml(c.barbero_nombre)}</div>
                        </div>
                    </div>
                `;
            });
        } else {
            citasHtml = `<div class="agenda-day-empty">Nada planeado</div>`;
        }

        // Live time indicator if today
        let liveTimeHtml = '';
        if (fStr === todayStr) {
            liveTimeHtml = `
                <div class="live-time-indicator">
                    <div class="live-time-dot"></div>
                    <div class="live-time-line"></div>
                </div>
            `;
        }

        // Accordion de Horarios Disponibles
        let availAccordionHtml = '';
        if (dayDisp && dayDisp.barberos && dayDisp.barberos.length > 0) {
            let barberSlotsHtml = '';
            dayDisp.barberos.forEach(b => {
                if (b.labora && b.slots_libres && b.slots_libres.length > 0) {
                    let pills = b.slots_libres.map(s => `<button type="button" class="slot-pill" onclick="agendarEnSlot('${fStr}', '${s.hora_inicio}', ${b.barbero_id})">${s.label}</button>`).join('');
                    barberSlotsHtml += `
                        <div class="avail-barber-section">
                            <div class="avail-barber-title">
                                <i class="fas fa-user-circle"></i>
                                <span>${escapeHtml(b.barbero_nombre)} (${b.slots_libres.length} disponibles)</span>
                            </div>
                            <div class="slots-pills-grid">${pills}</div>
                        </div>
                    `;
                } else if (!b.labora) {
                    barberSlotsHtml += `
                        <div class="avail-barber-section">
                            <div class="avail-barber-title" style="color: #999;">
                                <span>${escapeHtml(b.barbero_nombre)}: ${escapeHtml(b.motivo_no_labora || 'No labora')}</span>
                            </div>
                        </div>
                    `;
                }
            });

            availAccordionHtml = `
                <div class="avail-slots-box" id="avail-box-${fStr}">
                    <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--accent-green); margin-bottom: 8px;">
                        <i class="fas fa-clock"></i> Horarios Disponibles en Tiempo Real
                    </div>
                    ${barberSlotsHtml}
                </div>
            `;
        }

        groupDiv.innerHTML = `
            <div class="agenda-day-header">
                <div>${headerTitle}</div>
                <button type="button" class="avail-quick-btn" onclick="toggleAvailBox('${fStr}')">
                    <i class="fas fa-clock"></i> ${totalLibresDia} libres
                </button>
            </div>
            ${liveTimeHtml}
            ${citasHtml}
            ${availAccordionHtml}
        `;

        feed.appendChild(groupDiv);
        curr.setDate(curr.getDate() + 1);
    }
}

function toggleAvailBox(fStr) {
    const box = document.getElementById('avail-box-' + fStr);
    if (box) {
        box.classList.toggle('open');
    }
}

function formatHora(dateTimeStr) {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr.replace(/-/g, '/'));
    let hours = date.getHours();
    let minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    return `${hours}:${minutes}${ampm}`;
}

function cambiarSemana(delta) {
    let curr = new Date(currentStartDate + 'T00:00:00');
    curr.setDate(curr.getDate() + (delta * 7));
    currentStartDate = curr.toISOString().split('T')[0];

    let end = new Date(curr);
    end.setDate(end.getDate() + 6);
    currentEndDate = end.toISOString().split('T')[0];

    selectedDate = currentStartDate;
    cargarAgenda();
}

function irAHoy() {
    let now = new Date();
    let day = now.getDay();
    let diff = now.getDate() - day + (day === 0 ? -6 : 1); // lunes
    let monday = new Date(now.setDate(diff));

    currentStartDate = monday.toISOString().split('T')[0];
    let end = new Date(monday);
    end.setDate(end.getDate() + 6);
    currentEndDate = end.toISOString().split('T')[0];

    selectedDate = new Date().toISOString().split('T')[0];
    cargarAgenda();
}

function seleccionarBarberoFiltro(barberoId, el) {
    selectedBarberoId = barberoId;
    document.querySelectorAll('.barber-chip').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    cargarAgenda();
}

function seleccionarBarberoDrawer(barberoId) {
    selectedBarberoId = barberoId;
    cerrarDrawer();
    // Activar chip correspondiente
    document.querySelectorAll('.barber-chip').forEach(c => {
        if (parseInt(c.getAttribute('data-barbero-id')) === barberoId) {
            c.classList.add('active');
        } else {
            c.classList.remove('active');
        }
    });
    cargarAgenda();
}

function cambiarSucursal(sucursalId) {
    selectedSucursalId = parseInt(sucursalId);
    window.location.href = `admin-agenda.php?sucursal_id=${selectedSucursalId}`;
}

function abrirDrawer() {
    document.getElementById('drawerOverlay').classList.add('open');
    document.getElementById('drawerPanel').classList.add('open');
}

function cerrarDrawer() {
    document.getElementById('drawerOverlay').classList.remove('open');
    document.getElementById('drawerPanel').classList.remove('open');
}

function abrirModalDetalleCita(c) {
    document.getElementById('sheetClienteNombre').innerText = c.cliente_nombre;
    document.getElementById('sheetServicioNombre').innerText = c.servicio_nombre + (c.precio_servicio ? ' ($' + parseFloat(c.precio_servicio).toFixed(2) + ')' : '');
    document.getElementById('sheetFechaHora').innerText = c.fecha_hora;
    document.getElementById('sheetBarberoNombre').innerText = c.barbero_nombre + (c.sucursal_nombre ? ' (' + c.sucursal_nombre + ')' : '');
    
    const estadoBadge = document.getElementById('sheetEstadoBadge');
    estadoBadge.innerText = (c.estado || 'pendiente').toUpperCase();
    
    const precio = parseFloat(c.precio_final > 0 ? c.precio_final : c.precio_servicio || 0);
    const propina = parseFloat(c.propina || 0);
    document.getElementById('sheetPrecioFinal').innerText = '$' + (precio + propina).toFixed(2) + (propina > 0 ? ' (incluye $' + propina.toFixed(2) + ' propina)' : '');

    const actions = document.getElementById('sheetActionsContainer');
    actions.innerHTML = '';

    if (c.cliente_telefono) {
        actions.innerHTML += `
            <a href="https://wa.me/${c.cliente_telefono.replace(/\D/g,'')}" target="_blank" class="btn-sheet-primary" style="background: #25D366; color: #fff;">
                <i class="fab fa-whatsapp"></i> Enviar WhatsApp al Cliente
            </a>
        `;
    }

    if (c.estado !== 'completada') {
        actions.innerHTML += `
            <button type="button" class="btn-sheet-primary" onclick="marcarCompletada(${c.id})">
                <i class="fas fa-check-circle"></i> Confirmar y Terminar Cita
            </button>
            <button type="button" class="btn-sheet-danger" onclick="cancelarCita(${c.id})">
                <i class="fas fa-times-circle"></i> Cancelar Cita
            </button>
        `;
    } else {
        actions.innerHTML += `
            <div style="text-align: center; font-weight: 700; color: #10B981; padding: 8px;">
                <i class="fas fa-check-circle"></i> Cita Completada
            </div>
        `;
    }

    document.getElementById('modalCitaSheet').style.display = 'flex';
}

function cerrarModalCita() {
    document.getElementById('modalCitaSheet').style.display = 'none';
}

function abrirModalNuevaCita() {
    document.getElementById('modalNuevaCitaSheet').style.display = 'flex';
}

function cerrarModalNuevaCita() {
    document.getElementById('modalNuevaCitaSheet').style.display = 'none';
}

function agendarEnSlot(fecha, hora, barberoId) {
    document.getElementById('inputFechaCita').value = fecha;
    document.getElementById('inputHoraCita').value = hora;
    if (barberoId) {
        document.getElementById('inputBarberoId').value = barberoId;
    }
    abrirModalNuevaCita();
}

function guardarNuevaCita(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitNuevaCita');
    btn.disabled = true;
    btn.innerText = 'Guardando cita...';

    const form = document.getElementById('formNuevaCitaRapida');
    const formData = new FormData(form);
    formData.append('action', 'crear_manual');

    fetch('api/citas_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cerrarModalNuevaCita();
            form.reset();
            cargarAgenda();
        } else {
            alert('Error al agendar cita: ' + (data.error || data.message || 'Error en servidor'));
            btn.disabled = false;
            btn.innerText = 'Agendar Cita';
        }
    })
    .catch(err => {
        cerrarModalNuevaCita();
        cargarAgenda();
    });
}

function marcarCompletada(id) {
    if (confirm('¿Deseas marcar esta cita como completada?')) {
        const formData = new FormData();
        formData.append('action', 'completar');
        formData.append('id', id);
        formData.append('ajax', '1');

        fetch('api/citas_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            cerrarModalCita();
            cargarAgenda();
        })
        .catch(err => {
            cerrarModalCita();
            cargarAgenda();
        });
    }
}

function cancelarCita(id) {
    if (confirm('¿Realmente deseas cancelar esta cita?')) {
        const formData = new FormData();
        formData.append('action', 'cancelar_barbero');
        formData.append('id', id);
        formData.append('ajax', '1');

        fetch('api/citas_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            cerrarModalCita();
            cargarAgenda();
        })
        .catch(err => {
            cerrarModalCita();
            cargarAgenda();
        });
    }
}

function cambiarModoVista(modo) {
    currentViewMode = modo;
    document.querySelectorAll('.drawer-mode-item').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    cerrarDrawer();
    // Recargar o cambiar vista
    cargarAgenda();
}

function toggleMonthPicker() {
    abrirDrawer();
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

</body>
</html>
