<?php
/**
 * KORTZEN - Dashboard Exclusivo para Barberos
 * Interfaz nativa blanca y elegante para barberos (sistema de diseño idéntico al cliente, sin emojis)
 */

require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['rol'] !== 'barbero' && $currentUser['rol'] !== 'admin_local' && $currentUser['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$barbero_id = $currentUser['id'];
$sucursal_id = $currentUser['sucursal_id'] ?? 1;

// Asegurar columnas y tablas requeridas en la BD live
try {
    $pdo = getConnection();
    
    // Tabla bloqueos_horas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bloqueos_horas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barbero_id INT NOT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            motivo VARCHAR(255) NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_barbero (barbero_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Tabla ventas_productos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ventas_productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cita_id INT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            sucursal_id INT NOT NULL DEFAULT 1,
            usuario_id INT NOT NULL,
            INDEX idx_usuario (usuario_id),
            INDEX idx_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Columnas en citas
    try {
        $colsCitas = $pdo->query("SHOW COLUMNS FROM citas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('precio_final', $colsCitas)) {
            $pdo->exec("ALTER TABLE citas ADD COLUMN precio_final DECIMAL(10,2) DEFAULT NULL");
        }
        if (!in_array('propina', $colsCitas)) {
            $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) DEFAULT 0.00");
        }
        if (!in_array('asistencia_confirmada', $colsCitas)) {
            $pdo->exec("ALTER TABLE citas ADD COLUMN asistencia_confirmada TINYINT(1) DEFAULT 0");
        }
    } catch (Throwable $eCitas) {}

    // Columnas en clientes
    try {
        $colsClientes = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('notas_barbero', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN notas_barbero TEXT NULL");
        }
        if (!in_array('estilo_buscado', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN estilo_buscado VARCHAR(255) NULL");
        }
        if (!in_array('ambiente_preferido', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN ambiente_preferido VARCHAR(255) NULL");
        }
        if (!in_array('bebida_preferida', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN bebida_preferida VARCHAR(255) NULL");
        }
        if (!in_array('puntos', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0");
        }
        if (!in_array('foto_perfil', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN foto_perfil VARCHAR(500) NULL");
        }
    } catch (Throwable $eCli) {}

} catch (Throwable $exSchema) {}

// Mensajes de estado
$mensaje = '';
$tipoMensaje = '';

// 1. Ganancias por Servicios + Comisión por Ventas de Productos
$com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
$com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);
$com_productos = floatval($currentUser['comision_productos'] ?? 10.00);

// Ganancia Citas Hoy (Servicios)
$gananciaHoyServicios = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
    ", [$barbero_id]);
    $gananciaHoyServicios = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

// Ganancia Ventas Productos Hoy
$gananciaHoyVentas = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
        FROM ventas_productos 
        WHERE usuario_id = ? AND DATE(fecha) = CURDATE()
    ", [$barbero_id]);
    $gananciaHoyVentas = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

// Propinas Recibidas Hoy (Rubro Independiente)
$propinasHoy = 0;
try {
    $r = query("
        SELECT SUM(IFNULL(propina, 0)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
    ", [$barbero_id]);
    $propinasHoy = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$gananciaServiciosVentasHoy = floatval($gananciaHoyServicios) + floatval($gananciaHoyVentas);
$miGananciaDia = $gananciaServiciosVentasHoy + $propinasHoy;

// Ganancia Mes (Servicios + Ventas + Propinas)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$gananciaMesServicios = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monthStart, $monthEnd]);
    $gananciaMesServicios = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$gananciaMesVentas = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
        FROM ventas_productos 
        WHERE usuario_id = ? AND DATE(fecha) BETWEEN ? AND ?
    ", [$barbero_id, $monthStart, $monthEnd]);
    $gananciaMesVentas = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$propinasMes = 0;
try {
    $r = query("
        SELECT SUM(IFNULL(propina, 0)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monthStart, $monthEnd]);
    $propinasMes = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$gananciaServiciosVentasMes = floatval($gananciaMesServicios) + floatval($gananciaMesVentas);
$miGananciaMes = $gananciaServiciosVentasMes + $propinasMes;

// Ganancia Histórica Total Acumulada (Toda la historia del barbero)
$gananciaHistServicios = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada'
    ", [$barbero_id]);
    $gananciaHistServicios = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$gananciaHistVentas = 0;
try {
    $r = query("
        SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
        FROM ventas_productos 
        WHERE usuario_id = ?
    ", [$barbero_id]);
    $gananciaHistVentas = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$propinasHist = 0;
try {
    $r = query("
        SELECT SUM(IFNULL(propina, 0)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada'
    ", [$barbero_id]);
    $propinasHist = floatval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$citasCompletadasHist = 0;
try {
    $r = query("
        SELECT COUNT(*) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada'
    ", [$barbero_id]);
    $citasCompletadasHist = intval($r[0]['total'] ?? 0);
} catch (Throwable $e) {}

$miGananciaHistorica = floatval($gananciaHistServicios) + floatval($gananciaHistVentas) + floatval($propinasHist);

// Total de citas completadas hoy
$totalCitasHoy = 0;
try {
    $countHoy = query("
        SELECT COUNT(*) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
    ", [$barbero_id]);
    $totalCitasHoy = intval($countHoy[0]['total'] ?? 0);
} catch (Throwable $e) {}

// 2. Próximo Cliente (Garantiza incluir cualquier cita pendiente de HOY)
$nextClient = [];
try {
    $nextClient = query("
        SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil, cli.notas_barbero, cli.estilo_buscado, cli.ambiente_preferido, cli.bebida_preferida
        FROM citas c
        LEFT JOIN servicios s ON c.servicio_id = s.id
        LEFT JOIN clientes cli ON c.cliente_id = cli.id
        WHERE c.barbero_id = ? 
        AND c.estado IN ('pendiente', 'confirmada')
        AND (DATE(c.fecha_hora) = CURDATE() OR c.fecha_hora >= NOW())
        ORDER BY c.fecha_hora ASC 
        LIMIT 1
    ", [$barbero_id]);
} catch (Throwable $exNext) {
    try {
        $nextClient = query("
            SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            WHERE c.barbero_id = ? 
            AND c.estado IN ('pendiente', 'confirmada')
            AND (DATE(c.fecha_hora) = CURDATE() OR c.fecha_hora >= NOW())
            ORDER BY c.fecha_hora ASC 
            LIMIT 1
        ", [$barbero_id]);
    } catch (Throwable $exNext2) {
        $nextClient = [];
    }
}

$proximo = $nextClient ? $nextClient[0] : null;

// 3. Agenda de Citas: Hoy y Próximos Días
$citasAgenda = [];
try {
    $citasAgenda = query("
        SELECT c.*, 
               s.nombre as servicio, s.duracion_minutos, s.precio as servicio_precio,
               cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono, 
               cli.email as cliente_email, cli.foto_perfil, cli.notas_barbero, 
               cli.estilo_buscado, cli.ambiente_preferido, cli.bebida_preferida
        FROM citas c
        LEFT JOIN servicios s ON c.servicio_id = s.id
        LEFT JOIN clientes cli ON c.cliente_id = cli.id
        WHERE c.barbero_id = ? 
          AND DATE(c.fecha_hora) >= CURDATE()
          AND c.estado != 'cancelada'
        ORDER BY c.fecha_hora ASC
        LIMIT 60
    ", [$barbero_id]);
} catch (Throwable $exAgenda) {
    try {
        $citasAgenda = query("
            SELECT c.*, 
                   s.nombre as servicio, s.duracion_minutos,
                   cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            WHERE c.barbero_id = ? 
              AND DATE(c.fecha_hora) >= CURDATE()
              AND c.estado != 'cancelada'
            ORDER BY c.fecha_hora ASC
            LIMIT 60
        ", [$barbero_id]);
    } catch (Throwable $exAgenda2) {
        $citasAgenda = [];
    }
}

// Agrupar citas por fecha (Y-m-d)
$citasAgrupadasPorFecha = [];
$todayDateStr = date('Y-m-d');
$citasAgrupadasPorFecha[$todayDateStr] = [];

foreach ($citasAgenda as $c) {
    $fKey = date('Y-m-d', strtotime($c['fecha_hora']));
    $citasAgrupadasPorFecha[$fKey][] = $c;
}

// Reordenar dentro de cada día: no-completadas primero (por hora ASC), completadas abajo de todos
foreach ($citasAgrupadasPorFecha as $fKey => &$citasDelDia) {
    usort($citasDelDia, function($a, $b) {
        $aDone = (($a['estado'] ?? '') === 'completada') ? 1 : 0;
        $bDone = (($b['estado'] ?? '') === 'completada') ? 1 : 0;
        if ($aDone !== $bDone) {
            return $aDone - $bDone; // 0 (pendientes/confirmadas) arriba, 1 (completadas) al final
        }
        return strtotime($a['fecha_hora']) - strtotime($b['fecha_hora']);
    });
}
unset($citasDelDia);

if (!function_exists('formatearFechaEspanolAgenda')) {
    function formatearFechaEspanolAgenda($fechaStr) {
        $ts = strtotime($fechaStr);
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        
        $diaSemana = $dias[intval(date('w', $ts))];
        $diaNum = date('j', $ts);
        $mesNom = $meses[intval(date('n', $ts))];
        
        return "$diaSemana, $diaNum $mesNom";
    }
}

// 4. Inventario de la Sucursal
$inventarioItems = [];
try {
    $inventarioItems = query("SELECT id, producto, cantidad, precio FROM inventario WHERE sucursal_id = ? ORDER BY producto ASC", [$sucursal_id]);
} catch (Throwable $eInv) {}

$nombreBarbero = explode(' ', trim($currentUser['nombre'] ?? 'Barbero'))[0];
$inicial_barbero = strtoupper(substr($nombreBarbero, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Panel de Barbero - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=24">
    <link rel="stylesheet" href="/css/reset.css?v=24">
    <link rel="stylesheet" href="/css/pwa-native.css?v=52">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js?v=26200" defer></script>

    <style>
        .barber-stat-card {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .barber-stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .barber-stat-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #F4F4F4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111111;
        }
        .barber-stat-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #777777;
        }
        .barber-stat-val {
            font-size: 1.6rem;
            font-weight: 900;
            color: #111111;
            letter-spacing: -0.02em;
        }
        .barber-stat-sub {
            font-size: 0.78rem;
            color: #666666;
            margin-top: 4px;
        }
        .barber-section-card {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .barber-section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #111111;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .barber-icon-stroke {
            width: 20px;
            height: 20px;
            stroke: #111111;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .barber-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #D1D1D1;
            border-radius: 10px;
            background: #FAFAFA;
            color: #111111;
            font-size: 0.9rem;
            font-weight: 600;
            box-sizing: border-box;
            margin-bottom: 12px;
            transition: border-color 0.2s;
        }
        .barber-input:focus {
            outline: none;
            border-color: #000000;
            background: #FFFFFF;
        }
        .btn-action-black {
            width: 100%;
            background: #000000;
            color: #FFFFFF;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .btn-action-black:hover {
            background: #222222;
        }
        .badge-turno-clean {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .badge-pendiente-clean { background: #F4F4F4; color: #111111; border: 1px solid #D1D1D1; }
        .badge-completada-clean { background: #111111; color: #FFFFFF; }
        .badge-cancelada-clean { background: #FAFAFA; color: #888888; border: 1px solid #EAEAEA; }

        .pwa-container {
            padding-top: calc(env(safe-area-inset-top, 24px) + 16px) !important;
        }

        /* Responsive Barber Header & Grids */
        .barber-header-bar {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            gap: 12px;
        }
        .barber-header-logo {
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 2px;
            color: #111111;
            white-space: nowrap;
        }
        .barber-logout-btn {
            background: #111111;
            color: #FFFFFF;
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .barber-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .barber-forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .pwa-container {
                padding-top: calc(env(safe-area-inset-top, 28px) + 24px) !important;
            }
            .barber-header-bar {
                margin-top: 14px;
                padding: 12px 14px;
                border-radius: 14px;
                margin-bottom: 16px;
            }
            .barber-header-logo {
                font-size: 1rem;
                letter-spacing: 1px;
            }
            .barber-logout-btn {
                padding: 7px 11px;
                font-size: 0.76rem;
            }
            .barber-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 16px;
            }
            .barber-stat-card {
                padding: 10px 8px;
                border-radius: 12px;
            }
            .barber-stat-label {
                font-size: 0.62rem;
                letter-spacing: 0;
            }
            .barber-stat-icon-box {
                width: 28px;
                height: 28px;
            }
            .barber-stat-val {
                font-size: 1.15rem;
            }
            .barber-stat-sub {
                font-size: 0.65rem;
            }
            .barber-forms-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-bottom: 16px;
            }
            .barber-forms-grid .barber-section-card {
                margin-bottom: 0 !important;
            }
        }
        /* ESTILOS DE AGENDA DE CITAS (EXACTO A LA IMAGEN) */
        .barber-agenda-section {
            margin-bottom: 28px;
        }
        .barber-agenda-group {
            margin-bottom: 22px;
        }
        .barber-agenda-date-header {
            font-size: 1.05rem;
            font-weight: 700;
            color: #4B5563;
            margin: 0 0 10px 0;
            letter-spacing: -0.01em;
            text-transform: lowercase;
        }
        .barber-agenda-date-header::first-letter {
            text-transform: uppercase;
        }
        .barber-agenda-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .barber-appointment-card {
            background: #E0F2FE; /* Azul pastel suave idéntico a la referencia */
            border: 1px solid rgba(186, 230, 253, 0.7);
            border-left: 4.5px solid #0284C7; /* Barra azul viva en el borde izquierdo */
            border-radius: 8px;
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.18s ease;
            display: flex;
            flex-direction: column;
            gap: 3px;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .barber-appointment-card:hover {
            background: #D7EDFD;
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);
        }
        .barber-appointment-card:active {
            transform: scale(0.99);
        }
        /* Tarjeta de cita FINALIZADA (En verde y abajo de todos) */
        .barber-appointment-card.is-completed {
            background: #ECFDF5 !important;
            border: 1px solid #A7F3D0 !important;
            border-left: 4.5px solid #10B981 !important;
        }
        .barber-appointment-card.is-completed:hover {
            background: #D1FAE5 !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.18) !important;
        }
        .barber-appointment-card.is-completed .bac-client-name {
            color: #064E3B !important;
        }
        .barber-appointment-card.is-completed .bac-service-name {
            color: #047857 !important;
        }
        .barber-appointment-card.is-completed .bac-time-range {
            color: #065F46 !important;
        }
        .bac-row-top {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 6px;
        }
        .bac-client-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.2;
        }
        .bac-service-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .bac-row-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .bac-time-range {
            font-size: 0.92rem;
            font-weight: 600;
            color: #475569;
        }
        .bac-badge-confirmed {
            font-size: 0.72rem;
            font-weight: 800;
            color: #0369A1;
            background: #BAE6FD;
            padding: 2px 8px;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }
        .bac-badge-completed {
            font-size: 0.72rem;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 2px 8px;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }

        /* MODAL PERFIL DEL CLIENTE */
        .barber-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        .barber-modal-content {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            position: relative;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            box-sizing: border-box;
            animation: barberModalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes barberModalPop {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }
        .barber-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #F3F4F6;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            font-size: 1.4rem;
            line-height: 1;
            color: #4B5563;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .barber-modal-close:hover {
            background: #111111;
            color: #FFFFFF;
        }
    </style>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <!-- Header Exclusivo de Barbero -->
        <header class="barber-header-bar">
            <div class="barber-header-logo">KORTZEN</div>
            <div>
                <a href="logout.php" class="barber-logout-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </header>

        <!-- Saludo & Avatar Barbero -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; margin-top: 10px;">
            <div>
                <h1 class="pwa-greeting" style="font-size: 1.6rem; font-weight: 900; color: #111111; margin: 0;">Hola, <?php echo htmlspecialchars($nombreBarbero); ?></h1>
                <p style="color: #666666; margin-top: 4px; font-size: 0.9rem;">Barbero Profesional • KORTZEN</p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?php echo $inicial_barbero; ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div style="background: #111111; color: #FFFFFF; padding: 14px 18px; border-radius: 12px; font-size: 0.88rem; margin-bottom: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <svg class="barber-icon-stroke" style="stroke: #FFFFFF;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN 1: AGENDA DE CITAS (PRIMERO QUE APARECE) -->
        <div class="barber-agenda-section">
            <?php if (!empty($citasAgrupadasPorFecha)): ?>
                <?php foreach ($citasAgrupadasPorFecha as $fechaYMD => $citasDia): 
                    $esHoyFecha = ($fechaYMD === $todayDateStr);
                    $tituloFecha = formatearFechaEspanolAgenda($fechaYMD);
                    if ($esHoyFecha) {
                        $tituloFecha = "Hoy — " . $tituloFecha;
                    }
                ?>
                    <div class="barber-agenda-group">
                        <h2 class="barber-agenda-date-header"><?php echo htmlspecialchars($tituloFecha); ?></h2>
                        
                        <div class="barber-agenda-list">
                            <?php if (empty($citasDia)): ?>
                                <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 14px 16px; color: #64748B; font-size: 0.88rem; font-weight: 600;">
                                    No tienes citas agendadas para hoy.
                                </div>
                            <?php else: ?>
                                <?php foreach ($citasDia as $c): 
                                    $tsIni = strtotime($c['fecha_hora']);
                                    $dur = intval($c['duracion_minutos'] ?? 45);
                                    if ($dur <= 0) $dur = 45;
                                    $tsFin = $tsIni + ($dur * 60);
                                    
                                    // Formato 12 horas idéntico a la referencia: "3:00PM - 4:00PM"
                                    $horaInicio = date('g:iA', $tsIni);
                                    $horaFin = date('g:iA', $tsFin);
                                    $horaRango = $horaInicio . ' - ' . $horaFin;
                                    $fechaLegibleCita = formatearFechaEspanolAgenda($fechaYMD);
                                    $isCompletada = (($c['estado'] ?? '') === 'completada');
                                    $cardClass = $isCompletada ? 'barber-appointment-card is-completed' : 'barber-appointment-card';
                                ?>
                                <div class="<?php echo $cardClass; ?>"
                                     onclick="abrirPerfilClienteModal(this)"
                                     data-cliente-id="<?php echo htmlspecialchars($c['cliente_id_bd'] ?? $c['cliente_id'] ?? ''); ?>"
                                     data-cliente-nombre="<?php echo htmlspecialchars($c['cliente'] ?? 'Cliente'); ?>"
                                     data-cliente-email="<?php echo htmlspecialchars($c['cliente_email'] ?? ''); ?>"
                                     data-cliente-foto="<?php echo htmlspecialchars($c['foto_perfil'] ?? ''); ?>"
                                     data-servicio="<?php echo htmlspecialchars($c['servicio'] ?? 'CORTE'); ?>"
                                     data-servicio-precio="<?php echo htmlspecialchars($c['servicio_precio'] ?? $c['precio_final'] ?? '0.00'); ?>"
                                     data-hora-rango="<?php echo htmlspecialchars($horaRango); ?>"
                                     data-fecha-legible="<?php echo htmlspecialchars($fechaLegibleCita); ?>"
                                     data-estado="<?php echo htmlspecialchars($c['estado'] ?? 'pendiente'); ?>"
                                     data-confirmado="<?php echo !empty($c['asistencia_confirmada']) ? '1' : '0'; ?>"
                                     data-estilo="<?php echo htmlspecialchars($c['estilo_buscado'] ?? ''); ?>"
                                     data-ambiente="<?php echo htmlspecialchars($c['ambiente_preferido'] ?? ''); ?>"
                                     data-bebida="<?php echo htmlspecialchars($c['bebida_preferida'] ?? ''); ?>"
                                     data-notas="<?php echo htmlspecialchars($c['notas_barbero'] ?? ''); ?>"
                                     data-cita-id="<?php echo $c['id']; ?>"
                                     title="Haz clic para ver la ficha del cliente"
                                >
                                    <div class="bac-row-top">
                                        <span class="bac-client-name"><?php echo htmlspecialchars($c['cliente'] ?? 'Cliente'); ?></span>
                                        <span class="bac-service-name"><?php echo htmlspecialchars($c['servicio'] ?? 'CORTE'); ?></span>
                                    </div>
                                    <div class="bac-row-bottom">
                                        <span class="bac-time-range"><?php echo $horaRango; ?></span>
                                        <?php if ($isCompletada): ?>
                                            <span class="bac-badge-completed" style="background: #10B981; color: #FFFFFF; font-weight: 800; padding: 2px 8px; border-radius: 6px;">✓ Corte Finalizado</span>
                                        <?php elseif (!empty($c['asistencia_confirmada'])): ?>
                                            <span class="bac-badge-confirmed">✓ Confirmado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="barber-agenda-group">
                    <h2 class="barber-agenda-date-header"><?php echo formatearFechaEspanolAgenda(date('Y-m-d')); ?></h2>
                    <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 18px; text-align: center; color: #64748B;">
                        No tienes citas agendadas por el momento.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECCIÓN 2: TARJETAS DE MÉTRICAS DE GANANCIAS -->
        <div class="barber-stats-grid">
            
            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Ganancias Totales Hoy</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                </div>
                <div class="barber-stat-val">$<?php echo number_format($miGananciaDia, 2); ?></div>
                <div class="barber-stat-sub">Servicios + Ventas + Propinas</div>
            </div>

            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Ganancias Mes</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                </div>
                <div class="barber-stat-val">$<?php echo number_format($miGananciaMes, 2); ?></div>
                <div class="barber-stat-sub">Total acumulado del mes</div>
            </div>

            <!-- CUADRO DE INGRESOS HISTÓRICOS TOTALES -->
            <div class="barber-stat-card" style="border: 1.5px solid #111111; background: #111111; color: #FFFFFF;">
                <div class="barber-stat-header">
                    <span class="barber-stat-label" style="color: #E2E8F0; font-weight: 800;">Ingresos Históricos</span>
                    <div class="barber-stat-icon-box" style="color: #FFFFFF; background: rgba(255, 255, 255, 0.15);">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                </div>
                <div class="barber-stat-val" style="color: #FFFFFF; font-size: 1.55rem; font-weight: 900;">$<?php echo number_format($miGananciaHistorica, 2); ?></div>
                <div class="barber-stat-sub" style="color: #CBD5E1;"><?php echo number_format($citasCompletadasHist); ?> servicios completados en total</div>
            </div>

            <!-- RUBRO APARTE DE PROPINAS -->
            <div class="barber-stat-card" style="border: 1.5px solid #10B981; background: #F0FDF4;">
                <div class="barber-stat-header">
                    <span class="barber-stat-label" style="color: #047857; font-weight: 800;">Propinas (Rubro Aparte)</span>
                    <div class="barber-stat-icon-box" style="color: #047857;">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                </div>
                <div class="barber-stat-val" style="color: #065F46;">+$<?php echo number_format($propinasHoy, 2); ?></div>
                <div class="barber-stat-sub" style="color: #047857; font-weight: 700;">Acumulado Mes: +$<?php echo number_format($propinasMes, 2); ?></div>
            </div>

            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Cortes de Hoy</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.47" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>
                    </div>
                </div>
                <div class="barber-stat-val"><?php echo $totalCitasHoy; ?></div>
                <div class="barber-stat-sub">Servicios completados</div>
            </div>

        </div>

        <!-- SECCIÓN 3: HERRAMIENTAS Y ACCIONES DEL BARBERO (2 CAJAS) -->
        <div class="barber-forms-grid">
            
            <!-- Caja 1: Registrar Venta de Producto -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    <span>Registrar Venta de Producto</span>
                </h3>
                <p style="font-size: 0.85rem; color: #666666; margin-bottom: 16px; line-height: 1.4;">
                    Suma comisiones instantáneas al vender productos de barbería a tus clientes.
                </p>

                <form id="formVentaProducto" onsubmit="registrarVentaProductoBarbero(event)">
                    <label class="config-label" style="font-size: 0.78rem;">Producto Vendido</label>
                    <select name="producto_id" class="barber-input" required>
                        <option value="">-- Seleccionar producto del inventario --</option>
                        <?php foreach ($inventarioItems as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']); ?> (Stock: <?php echo $item['cantidad']; ?>) - $<?php echo number_format($item['precio'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="display: flex; gap: 12px;">
                        <div style="flex: 1;">
                            <label class="config-label" style="font-size: 0.78rem;">Cantidad</label>
                            <input type="number" name="cantidad" value="1" min="1" class="barber-input" required>
                        </div>
                        <div style="flex: 2; display: flex; align-items: flex-end; margin-bottom: 12px;">
                            <button type="submit" class="btn-action-black">
                                <span>Registrar Venta</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Caja 2: Consumo de Insumos del Turno -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>Consumo de Insumos del Turno</span>
                </h3>
                <p style="font-size: 0.85rem; color: #666666; margin-bottom: 16px; line-height: 1.4;">
                    Registra los materiales gastados para mantener actualizado el inventario.
                </p>

                <form id="formConsumoInsumo" onsubmit="descontarInsumoBarbero(event)">
                    <label class="config-label" style="font-size: 0.78rem;">Insumo / Material Gastado</label>
                    <select name="producto_id" class="barber-input" required>
                        <option value="">-- Seleccionar insumo a debitar --</option>
                        <?php foreach ($inventarioItems as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']); ?> (Quedan: <?php echo $item['cantidad']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="display: flex; gap: 12px;">
                        <div style="flex: 1;">
                            <label class="config-label" style="font-size: 0.78rem;">Cantidad Gastada</label>
                            <input type="number" name="cantidad" value="1" min="1" class="barber-input" required>
                        </div>
                        <div style="flex: 2; display: flex; align-items: flex-end; margin-bottom: 12px;">
                            <button type="submit" class="btn-action-black" style="background: #FFFFFF; color: #111111; border: 1px solid #111111;">
                                <span>Debitar Insumo</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>

        <!-- MODAL PERFIL DEL CLIENTE AL HACER CLIC -->
        <div id="modalPerfilCliente" class="barber-modal-overlay" style="display: none;" onclick="cerrarPerfilClienteModal(event)">
            <div class="barber-modal-content" onclick="event.stopPropagation()">
                <!-- Botón Cerrar -->
                <button type="button" class="barber-modal-close" onclick="cerrarPerfilClienteModal()">&times;</button>
                
                <!-- Encabezado Cliente -->
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px; border-bottom: 1px solid #EEEEEE; padding-bottom: 16px;">
                    <div id="modalClientAvatar" style="width: 54px; height: 54px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; flex-shrink: 0; background-size: cover; background-position: center; border: 2px solid #C0A062;">
                        C
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <h2 id="modalClientName" style="margin: 0; font-size: 1.25rem; font-weight: 900; color: #111111; line-height: 1.2;">
                            Cliente
                        </h2>
                        <div style="font-size: 0.8rem; color: #888888; margin-top: 4px; font-weight: 600;">
                            Perfil del Cliente
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Turno / Cita -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px;">
                    <div style="font-size: 0.7rem; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px;">
                        DETALLES DEL TURNO
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong id="modalCitaServicio" style="font-size: 1rem; color: #0F172A; display: block;">CORTE</strong>
                            <span id="modalCitaHorario" style="font-size: 0.85rem; color: #475569; font-weight: 600;">Hora</span>
                        </div>
                        <div style="text-align: right;">
                            <span id="modalCitaPrecio" style="font-size: 1.05rem; font-weight: 900; color: #111111;">$0.00</span>
                            <div id="modalCitaEstadoBadge" style="margin-top: 2px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Preferencias de Estilo & Experiencia -->
                <div id="modalPreferenciasContainer" style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; display: none;">
                    <div style="font-size: 0.72rem; font-weight: 900; color: #92400E; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        ✨ PREFERENCIAS DEL CLIENTE
                    </div>
                    <div id="modalEstiloText" style="font-size: 0.82rem; color: #78350F; margin-bottom: 3px;"></div>
                    <div id="modalAmbienteText" style="font-size: 0.82rem; color: #78350F; margin-bottom: 3px;"></div>
                    <div id="modalBebidaText" style="font-size: 0.82rem; color: #78350F;"></div>
                </div>

                <!-- Formulario de Notas Privadas del Barbero -->
                <form action="api/clientes_action.php" method="POST" style="margin-bottom: 16px;">
                    <input type="hidden" name="action" value="guardar_notas_barbero">
                    <input type="hidden" name="cliente_id" id="modalFormClienteId" value="">
                    <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #374151; text-transform: uppercase; margin-bottom: 6px;">
                        📝 Notas Privadas del Barbero sobre este cliente
                    </label>
                    <textarea name="notas_barbero" id="modalNotasBarberoInput" rows="2" placeholder="Ej: Degradado bajo #1.5, raya izquierda, tijera arriba..." style="width: 100%; border: 1px solid #D1D5DB; border-radius: 8px; padding: 8px 10px; font-size: 0.85rem; font-family: inherit; resize: vertical; box-sizing: border-box; background: #FAFAFA;"></textarea>
                    <button type="submit" style="margin-top: 6px; background: #111111; color: #FFFFFF; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer; text-transform: uppercase;">
                        Guardar Notas
                    </button>
                </form>

                <!-- Botón Cerrar Modal -->
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="cerrarPerfilClienteModal()" style="width: 100%; background: #111111; color: #FFFFFF; border: none; padding: 11px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; cursor: pointer; text-transform: uppercase;">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        function abrirPerfilClienteModal(cardEl) {
            const d = cardEl.dataset;
            const modal = document.getElementById('modalPerfilCliente');
            if (!modal) return;

            // Nombre
            document.getElementById('modalClientName').textContent = d.clienteNombre || 'Cliente';

            // Avatar
            const avatarEl = document.getElementById('modalClientAvatar');
            if (d.clienteFoto && d.clienteFoto.length > 5) {
                avatarEl.style.backgroundImage = `url('${d.clienteFoto}')`;
                avatarEl.textContent = '';
            } else {
                avatarEl.style.backgroundImage = 'none';
                avatarEl.textContent = (d.clienteNombre || 'C').charAt(0).toUpperCase();
            }

            // Cita actual
            document.getElementById('modalCitaServicio').textContent = d.servicio || 'CORTE';
            document.getElementById('modalCitaHorario').textContent = `${d.fechaLegible} • ${d.horaRango}`;
            document.getElementById('modalCitaPrecio').textContent = `$${parseFloat(d.servicioPrecio || 0).toFixed(2)}`;

            // Badge estado
            const badgeDiv = document.getElementById('modalCitaEstadoBadge');
            if (d.estado === 'completada') {
                badgeDiv.innerHTML = '<span style="background:#ECFDF5; color:#047857; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:6px;">✓ CITA COMPLETADA</span>';
            } else if (d.confirmado === '1') {
                badgeDiv.innerHTML = '<span style="background:#ECFDF5; color:#047857; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:6px;">✓ ASISTENCIA CONFIRMADA</span>';
            } else {
                badgeDiv.innerHTML = '<span style="background:#FFFBEB; color:#B45309; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:6px;">PENDIENTE DE ASISTENCIA</span>';
            }

            // Preferencias del cliente
            const prefContainer = document.getElementById('modalPreferenciasContainer');
            let hasPrefs = false;
            if (d.estilo) {
                document.getElementById('modalEstiloText').innerHTML = `<strong>• Estilo buscado:</strong> ${d.estilo}`;
                document.getElementById('modalEstiloText').style.display = 'block';
                hasPrefs = true;
            } else {
                document.getElementById('modalEstiloText').style.display = 'none';
            }
            if (d.ambiente) {
                document.getElementById('modalAmbienteText').innerHTML = `<strong>• Ambiente preferido:</strong> ${d.ambiente}`;
                document.getElementById('modalAmbienteText').style.display = 'block';
                hasPrefs = true;
            } else {
                document.getElementById('modalAmbienteText').style.display = 'none';
            }
            if (d.bebida) {
                document.getElementById('modalBebidaText').innerHTML = `<strong>• Bebida deseada:</strong> ${d.bebida}`;
                document.getElementById('modalBebidaText').style.display = 'block';
                hasPrefs = true;
            } else {
                document.getElementById('modalBebidaText').style.display = 'none';
            }
            prefContainer.style.display = hasPrefs ? 'block' : 'none';

            // Formulario de notas del barbero
            document.getElementById('modalFormClienteId').value = d.clienteId || '';
            document.getElementById('modalNotasBarberoInput').value = d.notas || '';

            // Mostrar modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function cerrarPerfilClienteModal(e) {
            if (!e || e.target.id === 'modalPerfilCliente' || e.target.classList.contains('barber-modal-close') || e.target.closest('.barber-modal-close')) {
                const modal = document.getElementById('modalPerfilCliente');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }
        }

        async function registrarVentaProductoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'registrar_venta');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al registrar la venta');
            }
        }

        async function descontarInsumoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'descontar_insumo');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al debitar insumo');
            }
        }
    </script>

</body>
</html>
