<?php
/**
 * KORTZEN - Detalle Completo y Gestión de Perfil de Barbero / Staff (Panel Admin)
 * Incluye gestión de stock individual, comisiones, horarios e historial de servicios.
 */
require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();
$userRol = $currentUser['rol'] ?? '';
$isAllowed = isLoggedIn() && (isAdminTecnico() || canManageUsers() || in_array($userRol, ['admin', 'superadmin', 'administrador', 'admin_local']));

if (!$isAllowed) {
    header('Location: dashboard.php?error=' . urlencode('Solo la administración puede ver el perfil completo del barbero.'));
    exit;
}

$barbero_id = intval($_GET['id'] ?? 0);

if ($barbero_id <= 0) {
    header('Location: usuarios.php');
    exit;
}

$pdo = getConnection();

// Auto-migración segura de tablas y columnas requeridas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventario_barbero (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barbero_id INT NOT NULL,
            sucursal_id INT DEFAULT NULL,
            producto VARCHAR(255) NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            unidad VARCHAR(50) DEFAULT 'unidades',
            precio DECIMAL(10,2) DEFAULT 0.00,
            descripcion TEXT NULL,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_barbero (barbero_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e_invb) {}

try {
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
} catch (Exception $e_vp) {}

try {
    $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) DEFAULT 0.00 AFTER precio_final");
} catch (Exception $e_prop) {}

try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_fin_semana DECIMAL(5,2) DEFAULT 50.00");
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_productos DECIMAL(5,2) DEFAULT 10.00");
} catch (Exception $e_com) {}

// Obtener datos del barbero/usuario
$stmtB = $pdo->prepare("
    SELECT u.*, s.nombre as sucursal_nombre 
    FROM usuarios u 
    LEFT JOIN sucursales s ON u.sucursal_id = s.id 
    WHERE u.id = ?
");
$stmtB->execute([$barbero_id]);
$barbero = $stmtB->fetch(PDO::FETCH_ASSOC);

if (!$barbero) {
    header('Location: usuarios.php?error=' . urlencode('Usuario no encontrado.'));
    exit;
}

// 1. Obtener Stock e Inventario Asignado al Barbero
$stockBarbero = [];
try {
    $stmtStock = $pdo->prepare("SELECT * FROM inventario_barbero WHERE barbero_id = ? ORDER BY producto ASC");
    $stmtStock->execute([$barbero_id]);
    $stockBarbero = $stmtStock->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stockBarbero = [];
}

// Obtener inventario general central de la sucursal (para debitar automáticamente)
$inventarioCentral = [];
try {
    $stmtCentral = $pdo->prepare("SELECT * FROM inventario WHERE (sucursal_id = ? OR sucursal_id IS NULL) ORDER BY producto ASC");
    $stmtCentral->execute([$barbero['sucursal_id']]);
    $inventarioCentral = $stmtCentral->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventarioCentral = [];
}

// 2. Historial de Citas Atendidas por este Barbero
$historialCitas = [];
try {
    $stmtCitas = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre, cl.telefono as cliente_telefono, s.nombre as servicio_nombre, suc.nombre as sucursal_nombre
        FROM citas c
        INNER JOIN clientes cl ON c.cliente_id = cl.id
        INNER JOIN servicios s ON c.servicio_id = s.id
        LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
        WHERE c.barbero_id = ?
        ORDER BY c.fecha_hora DESC
    ");
    $stmtCitas->execute([$barbero_id]);
    $historialCitas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $historialCitas = [];
}

// 3. Cálculos Financieros del Barbero
$com_diaria = floatval($barbero['comision_porcentaje'] ?? 50);
$com_finde = floatval($barbero['comision_fin_semana'] ?? 50);
$com_productos = floatval($barbero['comision_productos'] ?? 10.00);

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Ganancia Servicios Mes
$gananciaMesServicios = 0.00;
try {
    $stmtGanMesServ = $pdo->prepare("
        SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN ? ELSE ? END) / 100)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ");
    $stmtGanMesServ->execute([$com_finde, $com_diaria, $barbero_id, $monthStart, $monthEnd]);
    $gananciaMesServicios = floatval($stmtGanMesServ->fetchColumn() ?? 0);
} catch (Exception $e) {
    $gananciaMesServicios = 0.00;
}

// Ganancia Ventas Productos Mes
$gananciaMesVentas = 0.00;
try {
    $stmtGanMesProd = $pdo->prepare("
        SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * ? / 100)) as total
        FROM ventas_productos 
        WHERE usuario_id = ? AND DATE(fecha) BETWEEN ? AND ?
    ");
    $stmtGanMesProd->execute([$com_productos, $barbero_id, $monthStart, $monthEnd]);
    $gananciaMesVentas = floatval($stmtGanMesProd->fetchColumn() ?? 0);
} catch (Exception $e) {
    $gananciaMesVentas = 0.00;
}

// Propinas Mes
$propinasMes = 0.00;
try {
    $stmtPropMes = $pdo->prepare("
        SELECT SUM(IFNULL(propina, 0)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ");
    $stmtPropMes->execute([$barbero_id, $monthStart, $monthEnd]);
    $propinasMes = floatval($stmtPropMes->fetchColumn() ?? 0);
} catch (Exception $e) {
    $propinasMes = 0.00;
}

$gananciaMesTotal = $gananciaMesServicios + $gananciaMesVentas + $propinasMes;

// Ganancias Históricas Totales Acumuladas
$gananciaHistServicios = 0.00;
try {
    $stmtGanHistServ = $pdo->prepare("
        SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN ? ELSE ? END) / 100)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada'
    ");
    $stmtGanHistServ->execute([$com_finde, $com_diaria, $barbero_id]);
    $gananciaHistServicios = floatval($stmtGanHistServ->fetchColumn() ?? 0);
} catch (Exception $e) {
    $gananciaHistServicios = 0.00;
}

$gananciaHistVentas = 0.00;
try {
    $stmtGanHistProd = $pdo->prepare("
        SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * ? / 100)) as total
        FROM ventas_productos 
        WHERE usuario_id = ?
    ");
    $stmtGanHistProd->execute([$com_productos, $barbero_id]);
    $gananciaHistVentas = floatval($stmtGanHistProd->fetchColumn() ?? 0);
} catch (Exception $e) {
    $gananciaHistVentas = 0.00;
}

$propinasHist = 0.00;
try {
    $stmtPropHist = $pdo->prepare("
        SELECT SUM(IFNULL(propina, 0)) as total
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada'
    ");
    $stmtPropHist->execute([$barbero_id]);
    $propinasHist = floatval($stmtPropHist->fetchColumn() ?? 0);
} catch (Exception $e) {
    $propinasHist = 0.00;
}

$gananciaHistoricaTotal = $gananciaHistServicios + $gananciaHistVentas + $propinasHist;

// Estadísticas de Citas
$totalCitas = count($historialCitas);
$citasCompletadas = 0;
$citasCanceladas = 0;
foreach ($historialCitas as $c) {
    if (($c['estado'] ?? '') === 'completada') $citasCompletadas++;
    if (($c['estado'] ?? '') === 'cancelada') $citasCanceladas++;
}

// 4. Horarios Semanales del Barbero
$horarios = [];
try {
    $stmtHorarios = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? ORDER BY dia_semana ASC");
    $stmtHorarios->execute([$barbero_id]);
    $horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $horarios = [];
}

$diasNombres = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];

$pageTitle = 'Perfil del Barbero: ' . htmlspecialchars($barbero['nombre']);
include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <a href="usuarios.php" style="color: #666; text-decoration: none; font-size: 0.88rem; font-weight: 700;">← Volver a Gestión de Usuarios</a>
        <h1 class="page-title" style="margin-top: 6px; color: #111111; font-weight: 900; font-size: 1.6rem; display: flex; align-items: center; gap: 10px;">
            <span><?php echo htmlspecialchars($barbero['nombre']); ?></span>
            <span style="background: #111111; color: #FFFFFF; font-size: 0.72rem; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; font-weight: 800;">
                <?php echo strtoupper(htmlspecialchars($barbero['rol'])); ?>
            </span>
        </h1>
    </div>

    <div style="display: flex; gap: 10px;">
        <button onclick="abrirModalStock()" class="btn btn-primary" style="background: #10B981; color: #FFFFFF; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 800; cursor: pointer;">
            + ASIGNAR STOCK AL BARBERO
        </button>
        <a href="usuarios_editar.php?id=<?php echo $barbero['id']; ?>" class="btn btn-secondary" style="background: #111111; color: #FFFFFF; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 800; text-decoration: none;">
            EDITAR USUARIO & COMISIONES
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 12px 16px; background: #E8F8F0; color: #1E7E45; border: 1px solid #C2EBCF; border-radius: 8px; margin-bottom: 20px; font-weight: 700;">
        ✅ <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="padding: 12px 16px; background: #FDF2F2; color: #9B1C1C; border: 1px solid #F8B4B4; border-radius: 8px; margin-bottom: 20px; font-weight: 700;">
        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>

<!-- Tarjetas KPI Principales -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div style="background: #111111; border-radius: 12px; padding: 18px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
        <div style="font-size: 0.75rem; font-weight: 800; color: #AAAAAA; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
            Ganancias Mes (Servicios + Ventas)
        </div>
        <div style="font-size: 1.6rem; font-weight: 900; color: #FFFFFF;">
            $<?php echo number_format($gananciaMesServicios + $gananciaMesVentas, 2); ?>
        </div>
        <div style="font-size: 0.78rem; color: #888888; margin-top: 4px;">
            Servicios: $<?php echo number_format($gananciaMesServicios, 2); ?> • Ventas: $<?php echo number_format($gananciaMesVentas, 2); ?>
        </div>
    </div>

    <!-- CUADRO DE INGRESOS HISTÓRICOS TOTALES -->
    <div style="background: #111111; border: 1.5px solid #333333; border-radius: 12px; padding: 18px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="font-size: 0.75rem; font-weight: 800; color: #E2E8F0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center;">
            <span>Ingresos Históricos</span>
            <span style="background: rgba(255,255,255,0.15); padding: 2px 7px; border-radius: 4px; font-size: 0.68rem; color: #FFFFFF; font-weight: 900;">HISTÓRICO</span>
        </div>
        <div style="font-size: 1.6rem; font-weight: 900; color: #FFFFFF;">
            $<?php echo number_format($gananciaHistoricaTotal, 2); ?>
        </div>
        <div style="font-size: 0.78rem; color: #CBD5E1; margin-top: 4px;">
            Servicios: $<?php echo number_format($gananciaHistServicios, 2); ?> • Ventas: $<?php echo number_format($gananciaHistVentas, 2); ?> • Propinas: $<?php echo number_format($propinasHist, 2); ?>
        </div>
    </div>

    <!-- RUBRO APARTE DE PROPINAS -->
    <div style="background: #F0FDF4; border: 1.5px solid #10B981; border-radius: 12px; padding: 18px;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
            Propinas Recibidas (Rubro Aparte)
        </div>
        <div style="font-size: 1.6rem; font-weight: 900; color: #065F46;">
            +$<?php echo number_format($propinasMes, 2); ?>
        </div>
        <div style="font-size: 0.78rem; color: #047857; font-weight: 700; margin-top: 4px;">
            Mes en curso (100% abonado al barbero)
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 12px; padding: 18px;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #555555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
            Citas Completadas
        </div>
        <div style="font-size: 1.6rem; font-weight: 900; color: #111111;">
            <?php echo $citasCompletadas; ?>
        </div>
        <div style="font-size: 0.78rem; color: #777777; margin-top: 4px;">
            De <?php echo $totalCitas; ?> citas agendadas
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 12px; padding: 18px;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #555555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
            Stock Personal Asignado
        </div>
        <div style="font-size: 1.6rem; font-weight: 900; color: #111111;">
            <?php echo count($stockBarbero); ?> ítems
        </div>
        <div style="font-size: 0.78rem; color: #777777; margin-top: 4px;">
            Productos/herramientas bajo su custodia
        </div>
    </div>
</div>

<!-- SECCIÓN 1: STOCK E INVENTARIO INDIVIDUAL DEL BARBERO -->
<div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 14px; padding: 24px; margin-bottom: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #EAEAEA; padding-bottom: 14px;">
        <div>
            <h2 style="font-size: 1.2rem; font-weight: 900; color: #111111; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-boxes"></i> Stock e Inventario del Barbero
            </h2>
            <p style="font-size: 0.82rem; color: #666666; margin-top: 2px;">Productos, pomadas, navajas y suministros asignados exclusivamente a este barbero.</p>
        </div>
        <button onclick="abrirModalStock()" class="btn" style="background: #111111; color: #FFFFFF; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer;">
            + Asignar Nuevo Producto
        </button>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>PRODUCTO / SUMINISTRO</th>
                    <th>CANTIDAD EN STOCK</th>
                    <th>UNIDAD</th>
                    <th>VALOR / PRECIO REF.</th>
                    <th>DESCRIPCIÓN / NOTAS</th>
                    <th>ÚLTIMA ACTUALIZACIÓN</th>
                    <th>ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stockBarbero)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #888888;">
                            Este barbero aún no tiene productos asignados en su stock personal.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stockBarbero as $st): ?>
                        <tr>
                            <td style="font-weight: 800; color: #111111;">
                                <?php echo htmlspecialchars($st['producto']); ?>
                            </td>
                            <td>
                                <strong style="font-size: 1rem; color: <?php echo floatval($st['cantidad']) > 0 ? '#10B981' : '#EF4444'; ?>;">
                                    <?php echo number_format($st['cantidad'], 2); ?>
                                </strong>
                            </td>
                            <td style="color: #666666; font-size: 0.85rem;">
                                <?php echo htmlspecialchars($st['unidad']); ?>
                            </td>
                            <td style="font-weight: 700; color: #111111;">
                                $<?php echo number_format($st['precio'], 2); ?>
                            </td>
                            <td style="color: #666666; font-size: 0.85rem; max-width: 200px;">
                                <?php echo htmlspecialchars($st['descripcion'] ?? '-'); ?>
                            </td>
                            <td style="color: #888888; font-size: 0.8rem;">
                                <?php echo date('d/m/Y H:i', strtotime($st['fecha_actualizacion'])); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <button onclick='editarStockItem(<?php echo json_encode($st); ?>)' style="background: #F3F4F6; border: 1px solid #D1D5DB; padding: 6px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer;">
                                        Editar
                                    </button>
                                    <form method="POST" action="api/barbero_stock_action.php" onsubmit="return confirm('¿Seguro que deseas retirar este producto del stock del barbero?');" style="display:inline;">
                                        <input type="hidden" name="action" value="eliminar_stock">
                                        <input type="hidden" name="barbero_id" value="<?php echo $barbero_id; ?>">
                                        <input type="hidden" name="stock_id" value="<?php echo $st['id']; ?>">
                                        <button type="submit" style="background: none; border: none; color: #EF4444; font-weight: 700; font-size: 0.75rem; cursor: pointer; padding: 6px;">
                                            Retirar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GRID DOS COLUMNAS: DATOS DE USUARIO & HORARIOS -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
    
    <!-- TARJETA INFORMACIÓN Y COMISIONES -->
    <div style="background: #181818; border: 1px solid #333333; border-radius: 14px; padding: 22px; color: #FFFFFF;">
        <h3 style="font-size: 1.1rem; font-weight: 900; color: #FFFFFF; margin-bottom: 16px; border-bottom: 1px solid #333333; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-user-cog"></i> Información General & Comisiones
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Nombre Completo:</span>
                <strong style="color: #FFFFFF;"><?php echo htmlspecialchars($barbero['nombre']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Email:</span>
                <strong style="color: #FFFFFF;"><?php echo htmlspecialchars($barbero['email']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Sucursal Asignada:</span>
                <strong style="color: #FFFFFF;"><?php echo $barbero['sucursal_nombre'] ? htmlspecialchars($barbero['sucursal_nombre']) : 'Todas / General'; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Comisión Diaria (Lun - Vie):</span>
                <strong style="color: #10B981;"><?php echo number_format($com_diaria, 1); ?>%</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Comisión Fin de Semana (Sáb - Dom):</span>
                <strong style="color: #10B981;"><?php echo number_format($com_finde, 1); ?>%</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Comisión Venta Productos:</span>
                <strong style="color: #10B981;"><?php echo number_format($com_productos, 1); ?>%</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #282828; padding-bottom: 8px;">
                <span style="color: #AAAAAA;">Almuerzo Fijo (Admin):</span>
                <strong style="color: #F59E0B;">
                    <?php if (($barbero['almuerzo_activo'] ?? 1) == 1): ?>
                        <?php echo substr($barbero['almuerzo_inicio'] ?? '13:00', 0, 5); ?> - <?php echo substr($barbero['almuerzo_fin'] ?? '14:00', 0, 5); ?> (Bloqueado)
                    <?php else: ?>
                        Desactivado
                    <?php endif; ?>
                </strong>
            </div>
        </div>

        <div style="margin-top: 18px; text-align: right;">
            <a href="usuarios_editar.php?id=<?php echo $barbero['id']; ?>" style="color: #FFFFFF; font-size: 0.8rem; font-weight: 800; text-decoration: underline;">
                Editar Datos y Comisiones →
            </a>
        </div>
    </div>

    <!-- TARJETA HORARIOS DEL BARBERO -->
    <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 14px; padding: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #EAEAEA; padding-bottom: 10px;">
            <h3 style="font-size: 1.1rem; font-weight: 900; color: #111111; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-calendar-alt"></i> Horarios de Atención del Barbero
            </h3>
            <a href="horarios.php?barbero=<?php echo $barbero['id']; ?>&from_profile=1" style="font-size: 0.78rem; font-weight: 800; color: #111111; text-decoration: underline;">
                Editar Horarios
            </a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php for ($d = 0; $d <= 6; $d++): 
                $hDia = array_filter($horarios, fn($h) => $h['dia_semana'] == $d);
                $hDia = !empty($hDia) ? array_values($hDia)[0] : null;
                $activo = $hDia ? $hDia['activo'] : 1;
                $hIn = $hDia ? substr($hDia['hora_inicio'], 0, 5) : '10:00';
                $hOut = $hDia ? substr($hDia['hora_fin'], 0, 5) : '20:00';
            ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #FAFAFA; border-radius: 6px; font-size: 0.85rem;">
                    <span style="font-weight: 700; color: #333333; width: 90px;"><?php echo $diasNombres[$d]; ?></span>
                    <?php if ($activo): ?>
                        <span style="color: #10B981; font-weight: 800; font-size: 0.82rem;"><?php echo $hIn; ?> - <?php echo $hOut; ?></span>
                    <?php else: ?>
                        <span style="color: #EF4444; font-weight: 700; font-size: 0.8rem;">Día Libre / Inactivo</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- SECCIÓN 4: HISTORIAL DE CITAS Y SERVICIOS REALIZADOS -->
<div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 14px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
    <h2 style="font-size: 1.2rem; font-weight: 900; color: #111111; margin-bottom: 16px; border-bottom: 1px solid #EAEAEA; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-cut"></i> Historial de Citas Atendidas (<?php echo count($historialCitas); ?>)
    </h2>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>FECHA / HORA</th>
                    <th>CLIENTE</th>
                    <th>SERVICIO</th>
                    <th>PRECIO</th>
                    <th>PROPINA</th>
                    <th>ESTADO</th>
                    <th>CONTACTO CLIENTE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historialCitas)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #888888;">
                            Aún no hay citas registradas para este barbero.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historialCitas as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($c['fecha_hora'])); ?></strong>
                                <span style="color: #888888; font-size: 0.8rem; display: block;"><?php echo date('H:i', strtotime($c['fecha_hora'])); ?></span>
                            </td>
                            <td style="font-weight: 700; color: #111111;">
                                <?php echo htmlspecialchars($c['cliente_nombre']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($c['servicio_nombre']); ?>
                            </td>
                            <td style="font-weight: 800; color: #111111;">
                                $<?php echo number_format($c['precio_final'], 2); ?>
                            </td>
                            <td>
                                <?php if (floatval($c['propina'] ?? 0) > 0): ?>
                                    <strong style="color: #10B981;">+$<?php echo number_format($c['propina'], 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #888888;">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $c['estado']; ?>">
                                    <?php echo strtoupper($c['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['cliente_telefono'])): 
                                    $waPhone = formatPhoneForWhatsapp($c['cliente_telefono']);
                                    $waMsg = urlencode("Hola " . explode(' ', $c['cliente_nombre'])[0] . ", te escribo de Kortzen Barbería respecto a tu cita agendada.");
                                ?>
                                    <a href="https://wa.me/<?php echo $waPhone; ?>?text=<?php echo $waMsg; ?>" 
                                       target="_blank" 
                                       style="background: #25D366; color: #FFFFFF; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                                    </a>
                                <?php else: ?>
                                    <span style="color: #888888; font-size: 0.78rem;">Sin teléfono</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ASIGNAR / EDITAR STOCK DEL BARBERO -->
<div id="modalStock" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: #FFFFFF; border-radius: 14px; width: 90%; max-width: 500px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <h2 id="modalStockTitle" style="margin-top: 0; margin-bottom: 16px; font-size: 1.3rem; font-weight: 900; color: #111111;">
            Asignar Producto al Stock del Barbero
        </h2>
        
        <form method="POST" action="api/barbero_stock_action.php">
            <input type="hidden" name="action" value="guardar_stock">
            <input type="hidden" name="barbero_id" value="<?php echo $barbero_id; ?>">
            <input type="hidden" name="stock_id" id="modalStockId" value="0">
            <input type="hidden" name="inventario_central_id" id="modalInventarioCentralId" value="0">

            <?php if (!empty($inventarioCentral)): ?>
                <div style="margin-bottom: 16px; background: #F0FDF4; border: 1.5px solid #10B981; padding: 12px; border-radius: 10px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-weight: 800; font-size: 0.78rem; color: #047857; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-store"></i> Seleccionar del Inventario Central (Débito Automático):
                    </label>
                    <select id="selectCentralStock" onchange="seleccionarItemCentral(this)" style="width: 100%; padding: 10px; border: 1px solid #10B981; border-radius: 6px; font-size: 0.88rem; font-weight: 700; background: #FFFFFF;">
                        <option value="">-- Seleccionar producto para debitar del stock general --</option>
                        <?php foreach ($inventarioCentral as $ic): ?>
                            <option value="<?php echo $ic['id']; ?>"
                                    data-producto="<?php echo htmlspecialchars($ic['producto']); ?>"
                                    data-cantidad="<?php echo $ic['cantidad']; ?>"
                                    data-unidad="<?php echo htmlspecialchars($ic['unidad'] ?? 'unidades'); ?>"
                                    data-precio="<?php echo $ic['precio']; ?>">
                                <?php echo htmlspecialchars($ic['producto']); ?> (Stock Central: <?php echo number_format($ic['cantidad'], 1); ?> <?php echo htmlspecialchars($ic['unidad'] ?? 'und'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size: 0.73rem; color: #047857; margin-top: 6px; display: flex; align-items: center; gap: 4px; font-weight: 700;">
                        <i class="fas fa-info-circle"></i> Al asignar stock al barbero, se descontará automáticamente en tiempo real del inventario general.
                    </span>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #333333; margin-bottom: 4px; text-transform: uppercase;">
                    Nombre del Producto / Suministro *
                </label>
                <input type="text" name="producto" id="modalStockProducto" placeholder="Ej: Pomada Mate KORTZEN, Navajas..." required style="width: 100%; padding: 10px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #333333; margin-bottom: 4px; text-transform: uppercase;">
                        Cantidad *
                    </label>
                    <input type="number" step="0.01" min="0" name="cantidad" id="modalStockCantidad" placeholder="10" required style="width: 100%; padding: 10px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #333333; margin-bottom: 4px; text-transform: uppercase;">
                        Unidad
                    </label>
                    <select name="unidad" id="modalStockUnidad" style="width: 100%; padding: 10px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
                        <option value="unidades">Unidades</option>
                        <option value="ml">Mililitros (ml)</option>
                        <option value="g">Gramos (g)</option>
                        <option value="cajas">Cajas</option>
                        <option value="paquetes">Paquetes</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #333333; margin-bottom: 4px; text-transform: uppercase;">
                    Precio / Valor Ref. ($)
                </label>
                <input type="number" step="0.01" min="0" name="precio" id="modalStockPrecio" placeholder="15.00" value="0.00" style="width: 100%; padding: 10px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #333333; margin-bottom: 4px; text-transform: uppercase;">
                    Notas / Descripción (Opcional)
                </label>
                <textarea name="descripcion" id="modalStockDesc" placeholder="Detalles de uso o asignación..." style="width: 100%; height: 60px; border: 1px solid #CCCCCC; border-radius: 8px; padding: 8px; font-size: 0.85rem; resize: none; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="cerrarModalStock()" style="flex: 1; padding: 10px; background: #F3F4F6; border: 1px solid #D1D5DB; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    Cancelar
                </button>
                <button type="submit" style="flex: 1; padding: 10px; background: #111111; color: #FFFFFF; border: none; border-radius: 8px; font-weight: 800; cursor: pointer;">
                    Guardar y Debitar Stock
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function seleccionarItemCentral(selectElem) {
        const option = selectElem.options[selectElem.selectedIndex];
        if (option && option.value) {
            document.getElementById('modalInventarioCentralId').value = option.value;
            document.getElementById('modalStockProducto').value = option.getAttribute('data-producto') || '';
            document.getElementById('modalStockUnidad').value = option.getAttribute('data-unidad') || 'unidades';
            document.getElementById('modalStockPrecio').value = option.getAttribute('data-precio') || '0.00';
            document.getElementById('modalStockCantidad').focus();
        } else {
            document.getElementById('modalInventarioCentralId').value = '0';
        }
    }

    function abrirModalStock() {
        document.getElementById('modalStockTitle').innerText = 'Asignar Producto al Stock del Barbero';
        document.getElementById('modalStockId').value = '0';
        document.getElementById('modalInventarioCentralId').value = '0';
        if (document.getElementById('selectCentralStock')) document.getElementById('selectCentralStock').value = '';
        document.getElementById('modalStockProducto').value = '';
        document.getElementById('modalStockCantidad').value = '';
        document.getElementById('modalStockPrecio').value = '0.00';
        document.getElementById('modalStockDesc').value = '';
        document.getElementById('modalStock').style.display = 'flex';
    }

    function editarStockItem(item) {
        document.getElementById('modalStockTitle').innerText = 'Editar Stock del Barbero';
        document.getElementById('modalStockId').value = item.id;
        document.getElementById('modalInventarioCentralId').value = '0';
        if (document.getElementById('selectCentralStock')) document.getElementById('selectCentralStock').value = '';
        document.getElementById('modalStockProducto').value = item.producto;
        document.getElementById('modalStockCantidad').value = item.cantidad;
        document.getElementById('modalStockUnidad').value = item.unidad;
        document.getElementById('modalStockPrecio').value = item.precio;
        document.getElementById('modalStockDesc').value = item.descripcion || '';
        document.getElementById('modalStock').style.display = 'flex';
    }

    function cerrarModalStock() {
        document.getElementById('modalStock').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
