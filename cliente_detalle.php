<?php
/**
 * KORTZEN - Detalle Completo de Cliente (Panel Admin)
 * Diseño de alto contraste con tarjetas oscuras legibles e impecables
 */
require_once 'config.php';
requireLogin();

if (isBarbero()) {
    header('Location: barber-dashboard.php?error=' . urlencode('Solo el administrador puede ver el perfil completo del cliente.'));
    exit;
}

$cliente_id = intval($_GET['id'] ?? 0);

if ($cliente_id <= 0) {
    header('Location: clientes.php');
    exit;
}

$pdo = getConnection();

// Asegurar que exista la columna 'puntos'
try {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
} catch (Exception $e) {}

// Obtener datos del cliente
$stmtC = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtC->execute([$cliente_id]);
$cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: clientes.php?error=Cliente no encontrado');
    exit;
}

// Historial completo de citas
$stmtH = $pdo->prepare("
    SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre, suc.nombre as sucursal_nombre
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN usuarios b ON c.barbero_id = b.id
    LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
    WHERE c.cliente_id = ?
    ORDER BY c.fecha_hora DESC
");
$stmtH->execute([$cliente_id]);
$historialCitas = $stmtH->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del cliente
$totalCitas = count($historialCitas);
$citasCompletadas = 0;
$citasCanceladas = 0;
$totalGastado = 0;

foreach ($historialCitas as $h) {
    if ($h['estado'] === 'completada') {
        $citasCompletadas++;
        $totalGastado += floatval($h['precio_final'] ?? 0);
    } elseif ($h['estado'] === 'cancelada') {
        $citasCanceladas++;
    }
}

$pageTitle = 'Detalle de Cliente: ' . htmlspecialchars($cliente['nombre']);
include 'includes/header.php';
?>

<?php 
$backUrl = isBarbero() ? 'barber-dashboard.php' : 'clientes.php';
$backText = isBarbero() ? 'Volver a mi Panel' : 'Volver a Clientes';
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <a href="<?php echo $backUrl; ?>" style="color: #888888; text-decoration: none; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s;">
            <i class="fas fa-arrow-left"></i> <?php echo $backText; ?>
        </a>
        <h1 class="page-title" style="margin-top: 6px; color: #FFFFFF; font-weight: 800; font-size: 1.8rem;"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
    </div>
    <?php if (!isBarbero()): ?>
    <div style="display: flex; gap: 10px;">
        <a href="clientes_editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-secondary" style="background: #1F1F1F; color: #FFFFFF; border: 1px solid #333333; padding: 10px 18px; border-radius: 8px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-user-edit"></i> EDITAR DATOS
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 12px 18px; background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid #10B981; border-radius: 10px; margin-bottom: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i> <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<!-- Grid de Información General -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; align-items: stretch;">
    
    <!-- Tarjeta de Datos Personales (Fondo Oscuro Elegante) -->
    <div style="background: #141414; border: 1px solid #262626; border-radius: 14px; padding: 24px; color: #FFFFFF; box-shadow: 0 4px 20px rgba(0,0,0,0.25); display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-border-box;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0 0 18px 0; border-bottom: 1px solid #262626; padding-bottom: 12px; display: flex; align-items: center; gap: 10px; letter-spacing: 0.02em;">
                <i class="fas fa-address-card" style="color: #C0A062;"></i> Datos de Contacto y Cuenta
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 14px; font-size: 0.92rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1F1F1F; padding-bottom: 10px;">
                    <span style="color: #888888; font-weight: 600;">Email:</span>
                    <strong style="color: #FFFFFF; font-weight: 700;"><?php echo $cliente['email'] ? htmlspecialchars($cliente['email']) : 'Sin registrar'; ?></strong>
                </div>
                <?php if (!isBarbero()): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1F1F1F; padding-bottom: 10px;">
                    <span style="color: #888888; font-weight: 600;">Teléfono:</span>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="color: #FFFFFF; font-weight: 700;"><?php echo $cliente['telefono'] ? htmlspecialchars(formatPhoneDisplay($cliente['telefono'])) : 'Sin registrar'; ?></strong>
                        <?php if (!empty($cliente['telefono'])): 
                            $wa_phone = formatPhoneForWhatsapp($cliente['telefono']);
                        ?>
                            <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" rel="noopener noreferrer" style="background: #10B981; color: #FFFFFF; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-weight: 800; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);">
                                <i class="fab fa-whatsapp" style="font-size: 0.9rem;"></i> Abrir WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1F1F1F; padding-bottom: 10px;">
                    <span style="color: #888888; font-weight: 600;">Fecha de Registro:</span>
                    <strong style="color: #FFFFFF; font-weight: 700;"><?php echo date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])); ?></strong>
                </div>
            </div>
        </div>

        <?php if (!empty($cliente['notas'])): ?>
            <div style="background: #1A1A1A; padding: 12px 14px; border-radius: 8px; margin-top: 14px; border: 1px solid #282828;">
                <span style="color: #C0A062; font-weight: 700; display: block; margin-bottom: 4px; font-size: 0.8rem; letter-spacing: 0.03em;">Notas del Barbero / Admin:</span>
                <p style="margin: 0; font-style: italic; color: #DDDDDD; font-size: 0.88rem; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($cliente['notas'])); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tarjeta de Gestión de Puntos KORTZEN (Fondo Negro Gold) -->
    <div style="background: #141414; border: 1px solid #C0A062; border-radius: 14px; padding: 24px; color: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 20px rgba(0,0,0,0.3); box-sizing: border-box;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #262626; padding-bottom: 12px;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #C0A062; margin: 0; display: flex; align-items: center; gap: 10px; letter-spacing: 0.02em;">
                    <i class="fas fa-award" style="color: #C0A062;"></i> Puntos KORTZEN (Fidelidad)
                </h3>
                <span style="background: rgba(192, 160, 98, 0.15); color: #D4AF37; border: 1px solid #D4AF37; padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px;">
                    <?php 
                        $pts = intval($cliente['puntos'] ?? 0);
                        if ($pts >= 1500) echo '<i class="fas fa-crown"></i> Nivel Oro';
                        elseif ($pts >= 500) echo '<i class="fas fa-medal"></i> Nivel Plata';
                        else echo '<i class="fas fa-medal"></i> Nivel Bronce';
                    ?>
                </span>
            </div>

            <div style="font-size: 2.6rem; font-weight: 900; color: #FFFFFF; margin-bottom: 8px; line-height: 1;">
                <?php echo number_format(intval($cliente['puntos'] ?? 0)); ?> <span style="font-size: 1.1rem; color: #C0A062; font-weight: 700;">pts</span>
            </div>
            
            <p style="font-size: 0.85rem; color: #888888; margin-bottom: 18px; line-height: 1.4;">
                Los puntos se acumulan automáticamente por cada cita finalizada (+100 pts) o pueden ser asignados manualmente desde aquí.
            </p>
        </div>

        <!-- Formulario Alineado para Modificar Puntos -->
        <form method="POST" action="api/clientes_action.php" style="background: #1F1F1F; padding: 14px; border-radius: 10px; border: 1px solid #333333; display: flex; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="action" value="update_puntos">
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
            <input type="hidden" name="redirect_to" value="cliente_detalle.php?id=<?php echo $cliente['id']; ?>">
            
            <div style="flex: 1;">
                <label style="font-size: 0.75rem; color: #AAAAAA; font-weight: 700; display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Editar Puntos Totales:</label>
                <input type="number" name="puntos" value="<?php echo intval($cliente['puntos'] ?? 0); ?>" required min="0" 
                       style="width: 100%; height: 44px; padding: 0 14px; background: #121212; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1rem; box-sizing: border-box; outline: none;">
            </div>
            
            <button type="submit" style="height: 44px; padding: 0 20px; background: #C0A062; color: #111111; border: none; border-radius: 8px; font-weight: 900; cursor: pointer; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: center; gap: 8px; box-sizing: border-box; transition: all 0.2s ease;">
                <i class="fas fa-save"></i> Guardar Puntos
            </button>
        </form>
    </div>

</div>

<!-- KPIs del Cliente -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="background: #141414; border: 1px solid #262626; padding: 20px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.75rem; color: #888888; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">TOTAL DE CITAS</div>
        <div style="font-size: 2rem; font-weight: 900; color: #FFFFFF; margin-top: 4px;"><?php echo $totalCitas; ?></div>
    </div>
    <div style="background: #141414; border: 1px solid #262626; padding: 20px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.75rem; color: #10B981; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">COMPLETADAS</div>
        <div style="font-size: 2rem; font-weight: 900; color: #10B981; margin-top: 4px;"><?php echo $citasCompletadas; ?></div>
    </div>
    <div style="background: #141414; border: 1px solid #262626; padding: 20px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.75rem; color: #EF4444; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">CANCELADAS</div>
        <div style="font-size: 2rem; font-weight: 900; color: #EF4444; margin-top: 4px;"><?php echo $citasCanceladas; ?></div>
    </div>
    <div style="background: #141414; border: 1px solid #262626; padding: 20px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.75rem; color: #C0A062; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">TOTAL INVERTIDO</div>
        <div style="font-size: 2rem; font-weight: 900; color: #C0A062; margin-top: 4px;">$<?php echo number_format($totalGastado, 2); ?></div>
    </div>
</div>

<!-- Tabla de Historial Completo de Citas (Fondo Oscuro de Alto Contraste) -->
<div style="background: #141414; border: 1px solid #262626; padding: 24px; border-radius: 14px; color: #FFFFFF; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
    <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 18px; color: #FFFFFF; display: flex; align-items: center; gap: 10px; letter-spacing: 0.02em;">
        <i class="fas fa-history" style="color: #C0A062;"></i> Historial Completo de Citas & Servicios
    </h3>

    <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 2px solid #262626; color: #888888; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                <th style="padding: 14px 12px; background: #1A1A1A;">FECHA Y HORA</th>
                <th style="padding: 14px 12px; background: #1A1A1A;">SERVICIO</th>
                <th style="padding: 14px 12px; background: #1A1A1A;">BARBERO</th>
                <th style="padding: 14px 12px; background: #1A1A1A;">SUCURSAL</th>
                <th style="padding: 14px 12px; background: #1A1A1A;">PRECIO</th>
                <th style="padding: 14px 12px; background: #1A1A1A;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($historialCitas) > 0): ?>
                <?php foreach ($historialCitas as $cita): 
                    $ts = strtotime($cita['fecha_hora']);
                    $estadoColor = '#888888';
                    if ($cita['estado'] === 'completada') $estadoColor = '#10B981';
                    elseif ($cita['estado'] === 'confirmada') $estadoColor = '#3B82F6';
                    elseif ($cita['estado'] === 'pendiente') $estadoColor = '#F59E0B';
                    elseif ($cita['estado'] === 'cancelada') $estadoColor = '#EF4444';
                ?>
                    <tr style="border-bottom: 1px solid #222222; color: #FFFFFF; font-size: 0.9rem;">
                        <td style="padding: 14px 12px;">
                            <strong style="color: #FFFFFF; display: block; font-weight: 800;"><?php echo date('d/m/Y', $ts); ?></strong>
                            <span style="color: #888888; font-size: 0.8rem; font-weight: 600;"><?php echo date('H:i', $ts); ?> hs</span>
                        </td>
                        <td style="padding: 14px 12px; font-weight: 800; color: #FFFFFF;">
                            <?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'Servicio Personalizado'); ?>
                        </td>
                        <td style="padding: 14px 12px; color: #DDDDDD;">
                            <?php echo htmlspecialchars($cita['barbero_nombre'] ?? 'Por asignar'); ?>
                        </td>
                        <td style="padding: 14px 12px; color: #DDDDDD;">
                            <?php echo htmlspecialchars($cita['sucursal_nombre'] ?? 'Llano Chico'); ?>
                        </td>
                        <td style="padding: 14px 12px; font-weight: 900; color: #C0A062;">
                            $<?php echo number_format(floatval($cita['precio_final'] ?? 0), 2); ?>
                        </td>
                        <td style="padding: 14px 12px;">
                            <span style="display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; background: <?php echo $estadoColor; ?>1E; color: <?php echo $estadoColor; ?>; border: 1px solid <?php echo $estadoColor; ?>;">
                                <?php echo htmlspecialchars($cita['estado']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #888888; font-weight: 500; font-size: 0.92rem;">
                        El cliente no tiene citas ni servicios registrados aún.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
