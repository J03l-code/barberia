<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Filtros
$estado = $_GET['estado'] ?? '';
$sucursal_id = $_GET['sucursal_id'] ?? '';
$barbero_id = $_GET['barbero_id'] ?? '';

// Filtro de fecha: Por defecto mostrar directamente solo las citas de hoy con cada barbero
// Si el usuario especifica una fecha en GET (o '?fecha=' vacía para ver todas), se respeta
if (isset($_GET['fecha'])) {
    $fecha = trim($_GET['fecha']);
} elseif (isset($_GET['todas']) || isset($_GET['limpiar'])) {
    $fecha = '';
} else {
    $fecha = date('Y-m-d'); // Por defecto: Hoy
}
$es_hoy = ($fecha === date('Y-m-d'));

// Obtener inventario para el modal de terminar cita (de todas las sucursales)
$inventario = [];
try {
    $inventario = query("SELECT i.id, i.producto, i.cantidad, i.unidad, s.nombre as sucursal 
                         FROM inventario i 
                         JOIN sucursales s ON i.sucursal_id = s.id 
                         WHERE i.cantidad > 0 
                         ORDER BY i.producto ASC, s.nombre ASC");
} catch (Exception $e) {
    // Silencioso
}

// Obtener sucursales permitidas para este usuario
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$sucursales_lista = [];
$barberos_lista = [];

try {
    if (isAdminTecnico()) {
        $sucursales_lista = query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
        $barberos_lista = query("SELECT id, nombre FROM usuarios WHERE rol IN ('barbero', 'admin_local', 'admin') AND activo = 1 ORDER BY nombre ASC");
    } elseif ($currentUser['rol'] === 'admin_local') {
        $sucursales_lista = getUsuarioSucursales($currentUser['id']);
        if (!empty($userSucursalesIds)) {
            $inSucList = implode(',', array_map('intval', $userSucursalesIds));
            $barberos_lista = query("SELECT id, nombre FROM usuarios WHERE rol IN ('barbero', 'admin_local') AND activo = 1 AND (sucursal_id IN ($inSucList) OR id = ?) ORDER BY nombre ASC", [$currentUser['id']]);
        } else {
            $barberos_lista = query("SELECT id, nombre FROM usuarios WHERE id = ? AND activo = 1", [$currentUser['id']]);
        }
    } else {
        // Barbero
        $sucursales_lista = getUsuarioSucursales($currentUser['id']);
        $barberos_lista = query("SELECT id, nombre FROM usuarios WHERE id = ?", [$currentUser['id']]);
    }
} catch (Exception $e) {
    // Silencioso
}

// Obtener citas
try {
    $sql = "SELECT c.*, 
            cl.nombre as cliente_nombre,
            cl.telefono as cliente_telefono,
            COALESCE(cl.foto_perfil, '') as cliente_foto,
            s.nombre as servicio_nombre,
            u.nombre as barbero_nombre,
            COALESCE(u.foto_url, '') as barbero_foto,
            su.nombre as sucursal_nombre,
            ref.codigo_usado as referido_codigo,
            ref.descuento_aplicado as referido_descuento,
            ref.referente_id,
            ref_cli.nombre as referente_nombre
            FROM citas c
            INNER JOIN clientes cl ON c.cliente_id = cl.id
            INNER JOIN servicios s ON c.servicio_id = s.id
            INNER JOIN usuarios u ON c.barbero_id = u.id
            INNER JOIN sucursales su ON c.sucursal_id = su.id
            LEFT JOIN referidos ref ON c.id = ref.cita_id
            LEFT JOIN clientes ref_cli ON ref.referente_id = ref_cli.id
            WHERE 1=1";

    $params = [];

    // SI ES BARBERO: Solo ver sus citas
    if ($currentUser['rol'] === 'barbero') {
        $sql .= " AND c.barbero_id = ?";
        $params[] = $currentUser['id'];
    } elseif ($currentUser['rol'] === 'admin_local') {
        // Admin Local: Solo ver citas de sus sucursales asignadas
        if (!empty($sucursal_id) && in_array(intval($sucursal_id), $userSucursalesIds)) {
            $sql .= " AND c.sucursal_id = ?";
            $params[] = intval($sucursal_id);
        } else {
            $inSucList = !empty($userSucursalesIds) ? implode(',', array_map('intval', $userSucursalesIds)) : '0';
            $sql .= " AND c.sucursal_id IN ($inSucList)";
        }
        if (!empty($barbero_id)) {
            $sql .= " AND c.barbero_id = ?";
            $params[] = $barbero_id;
        }
    } else {
        // Admin Técnico
        if (!empty($barbero_id)) {
            $sql .= " AND c.barbero_id = ?";
            $params[] = $barbero_id;
        }
        if (!empty($sucursal_id)) {
            $sql .= " AND c.sucursal_id = ?";
            $params[] = $sucursal_id;
        }
    }

    if ($estado) {
        $sql .= " AND c.estado = ?";
        $params[] = $estado;
    }

    if ($fecha) {
        $sql .= " AND DATE(c.fecha_hora) = ?";
        $params[] = $fecha;
        // Orden cronológico para el día seleccionado (mañana a noche con cada barbero)
        $sql .= " ORDER BY c.fecha_hora ASC, u.nombre ASC";
    } else {
        $sql .= " ORDER BY c.fecha_hora DESC, c.id DESC";
    }

    $citas = query($sql, $params);
} catch (PDOException $e) {
    error_log("Error al obtener citas: " . $e->getMessage());
    $citas = [];
}

$pageTitle = 'Citas';
include 'includes/header.php';
?>

<div class="page-header" style="flex-wrap: wrap; gap: 14px; align-items: center;">
    <div>
        <h1 class="page-title" style="margin: 0;">
            <?php echo $currentUser['rol'] === 'barbero' ? 'Mis Citas' : 'Gestión de Citas'; ?>
        </h1>
        <?php if ($es_hoy): ?>
            <span style="display: inline-block; margin-top: 4px; font-size: 0.78rem; font-weight: 800; color: #10B981; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 2px 8px; border-radius: 6px;">
                ● Mostrando citas de hoy (<?php echo date('d/m/Y'); ?>)
            </span>
        <?php elseif (!empty($fecha)): ?>
            <span style="display: inline-block; margin-top: 4px; font-size: 0.78rem; font-weight: 800; color: #1E40AF; background: #DBEAFE; border: 1px solid #93C5FD; padding: 2px 8px; border-radius: 6px;">
                ● Mostrando citas del <?php echo date('d/m/Y', strtotime($fecha)); ?>
            </span>
        <?php else: ?>
            <span style="display: inline-block; margin-top: 4px; font-size: 0.78rem; font-weight: 700; color: #6B7280; background: #F3F4F6; padding: 2px 8px; border-radius: 6px;">
                ● Mostrando histórico completo de todas las fechas
            </span>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <form method="GET" id="filterForm" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php if ($currentUser['rol'] !== 'barbero'): ?>
                <!-- Filtro Sucursal -->
                <select name="sucursal_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($sucursales_lista as $suc): ?>
                        <option value="<?php echo $suc['id']; ?>" <?php echo $sucursal_id == $suc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($suc['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filtro Barbero -->
                <select name="barbero_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todos los barberos</option>
                    <?php foreach ($barberos_lista as $barb): ?>
                        <option value="<?php echo $barb['id']; ?>" <?php echo $barbero_id == $barb['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barb['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <!-- Filtro Estado -->
            <select name="estado" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="confirmada" <?php echo $estado == 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                <option value="completada" <?php echo $estado == 'completada' ? 'selected' : ''; ?>>Completada</option>
                <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
            </select>

            <!-- Filtro Fecha -->
            <input type="date" name="fecha" id="filtroFecha" class="filter-date" value="<?php echo htmlspecialchars($fecha); ?>"
                onchange="this.form.submit()">

            <!-- Botón Rápido: Solo Citas de Hoy -->
            <button type="button" class="btn <?php echo $es_hoy ? 'btn-today-active' : 'btn-today'; ?>" onclick="toggleFiltroHoy()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?php echo $es_hoy ? 'Viendo: Hoy' : 'Citas de Hoy'; ?>
            </button>

            <?php if (!empty($fecha)): ?>
                <a href="citas.php?fecha=<?php echo (!empty($sucursal_id) ? '&sucursal_id='.urlencode($sucursal_id) : '') . (!empty($barbero_id) ? '&barbero_id='.urlencode($barbero_id) : '') . (!empty($estado) ? '&estado='.urlencode($estado) : ''); ?>" class="btn btn-secondary" title="Ver citas de todos los días">Ver Todos los Días</a>
            <?php endif; ?>
            <?php if ($estado || $sucursal_id || $barbero_id || $fecha !== ''): ?>
                <a href="citas.php?fecha=" class="btn btn-secondary" style="color: #666;">Limpiar Filtros</a>
            <?php endif; ?>
        </form>

        <?php if ($currentUser['rol'] === 'admin'): ?>
            <button onclick="window.location.href='citas_crear.php'" class="btn btn-primary" style="white-space: nowrap;">+ AÑADIR CITA</button>
        <?php endif; ?>
    </div>
</div>

<!-- Buscador Predictivo de Citas -->
<div style="position: relative; margin-bottom: 24px; max-width: 540px;">
    <div style="position: relative; display: flex; align-items: center;">
        <i class="fas fa-search search-icon" style="position: absolute; left: 14px; color: #9CA3AF; font-size: 14px; pointer-events: none;"></i>
        <input type="text" 
               id="predictiveCitaSearch" 
               placeholder="Buscar cita por cliente, teléfono, servicio, barbero..." 
               class="search-input"
               autocomplete="off"
               style="width: 100%; padding: 11px 16px 11px 40px; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 10px; font-size: 14px; font-weight: 500; color: #111827; outline: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div id="predictiveCitaDropdown" class="predictive-dropdown"></div>
</div>

<script>
function toggleFiltroHoy() {
    const inputFecha = document.getElementById('filtroFecha');
    const form = document.getElementById('filterForm');
    const hoyStr = '<?php echo date('Y-m-d'); ?>';
    
    if (inputFecha.value === hoyStr) {
        inputFecha.value = '';
    } else {
        inputFecha.value = hoyStr;
    }
    form.submit();
}
</script>

<style>
    .predictive-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        max-height: 380px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .predictive-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid #F3F4F6;
        cursor: pointer;
        transition: background 0.15s ease;
        text-decoration: none;
        color: inherit;
    }

    .predictive-item:last-child {
        border-bottom: none;
    }

    .predictive-item:hover {
        background: #F9FAFB;
    }

    .avatar-circle-sm {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        background: #111827;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11px;
        border: 1.5px solid #E5E7EB;
    }
    .btn-today {
        padding: 9px 14px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 6px;
        color: #111111;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }
    .btn-today:hover {
        background: #F3F4F6;
        border-color: #111111;
    }
    .btn-today-active {
        padding: 9px 14px;
        background: #111111;
        border: 1px solid #111111;
        border-radius: 6px;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }
    .btn-today-active:hover {
        background: #222222;
    }

    /* Estilos existentes + Modal styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .filter-select,
    .filter-date {
        padding: 10px 16px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 14px;
        cursor: pointer;
    }

    .filter-select:focus,
    .filter-date:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: transparent;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-secondary:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .status-pendiente {
        background: rgba(255, 184, 0, 0.12);
        color: #FFB800;
        border: 1px solid rgba(255, 184, 0, 0.3);
    }

    .status-confirmada {
        background: rgba(59, 130, 246, 0.12);
        color: #3B82F6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .status-completada {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    .status-cancelada {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
        border: 1px solid rgba(231, 76, 60, 0.3);
    }

    .btn-action {
        padding: 8px 18px;
        background: transparent;
        border: 1px solid #333333;
        border-radius: 4px;
        color: #333333;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:hover {
        background: #333333;
        color: #1A1A1A;
    }

    .btn-delete {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .btn-delete:hover {
        background: #E74C3C;
        color: #FFFFFF;
    }

    .btn-complete {
        border-color: #2ECC71;
        color: #2ECC71;
    }

    .btn-complete:hover {
        background: #2ECC71;
        color: #000;
    }

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: #FFFFFF;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        width: 500px;
        max-width: 90%;
    }

    .material-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>FECHA/HORA</th>
                <th>CLIENTE</th>
                <th>SERVICIO</th>
                <?php if ($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local'): ?>
                    <th>BARBERO</th>
                <?php endif; ?>
                <th>PROPINA</th>
                <th>ESTADO</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($citas) > 0): ?>
                <?php foreach ($citas as $cita): 
                    $cliInit = strtoupper(mb_substr($cita['cliente_nombre'] ?? 'C', 0, 1, 'UTF-8'));
                    $barbInit = strtoupper(mb_substr($cita['barbero_nombre'] ?? 'B', 0, 1, 'UTF-8'));
                    $searchData = strtolower($cita['cliente_nombre'] . ' ' . ($cita['cliente_telefono'] ?? '') . ' ' . $cita['servicio_nombre'] . ' ' . $cita['barbero_nombre'] . ' ' . $cita['sucursal_nombre'] . ' ' . $cita['estado'] . ' ' . date('d/m/Y', strtotime($cita['fecha_hora'])));
                ?>
                    <tr class="cita-row" id="cita-row-<?php echo $cita['id']; ?>" data-search="<?php echo htmlspecialchars($searchData); ?>">
                        <td>
                            <strong><?php echo date('d/m/Y', strtotime($cita['fecha_hora'])); ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 13px;">
                                <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if (!empty($cita['cliente_foto'])): ?>
                                    <img src="<?php echo htmlspecialchars($cita['cliente_foto']); ?>" 
                                         class="avatar-circle-sm" 
                                         alt="<?php echo htmlspecialchars($cita['cliente_nombre']); ?>"
                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="avatar-circle-sm" style="display: none;"><?php echo $cliInit; ?></div>
                                <?php else: ?>
                                    <div class="avatar-circle-sm"><?php echo $cliInit; ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 700; color: #111827;"><?php echo htmlspecialchars($cita['cliente_nombre']); ?></div>
                                    <?php if (($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local') && !empty($cita['cliente_telefono'])):
                                        $wa_phone = formatPhoneForWhatsapp($cita['cliente_telefono']);
                                        $wa_msg = urlencode("Hola " . explode(' ', $cita['cliente_nombre'])[0] . ", te escribo de Kortzen sobre tu cita.");
                                        ?>
                                        <div style="font-size: 11px; margin-top: 2px; display: flex; gap: 8px; align-items: center;">
                                            <span style="color:#888;"><?php echo htmlspecialchars(formatPhoneDisplay($cita['cliente_telefono'])); ?></span>

                                            <!-- WA Button -->
                                            <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank"
                                                title="WhatsApp" style="color: #25D366; text-decoration: none;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                    fill="currentColor">
                                                    <path
                                                        d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                </svg>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #111111; font-size: 13.5px;">
                                <?php echo htmlspecialchars($cita['servicio_nombre']); ?>
                            </div>
                            <?php if (!empty($cita['referido_descuento']) && floatval($cita['referido_descuento']) > 0): ?>
                                <div style="margin-top: 5px; display: flex; flex-direction: column; gap: 2px;">
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 2px 7px; border-radius: 4px; width: fit-content;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                        Reservado con código de referido<?php echo !empty($cita['referido_codigo']) ? ' (' . htmlspecialchars($cita['referido_codigo']) . ')' : ''; ?>
                                    </span>
                                    <span style="font-size: 11px; color: #059669; font-weight: 600;">
                                        Descuento aplicado por referido: -$<?php echo number_format(floatval($cita['referido_descuento']), 2); ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($cita['referido_codigo'])): ?>
                                <div style="margin-top: 5px;">
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 2px 7px; border-radius: 4px; width: fit-content;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                        Reservado con código: <?php echo htmlspecialchars($cita['referido_codigo']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <?php if ($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local'): ?>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if (!empty($cita['barbero_foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($cita['barbero_foto']); ?>" 
                                             class="avatar-circle-sm" 
                                             style="width: 28px; height: 28px; font-size: 10px;"
                                             alt="<?php echo htmlspecialchars($cita['barbero_nombre']); ?>"
                                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="avatar-circle-sm" style="display: none; width: 28px; height: 28px; font-size: 10px; background: #374151;"><?php echo $barbInit; ?></div>
                                    <?php else: ?>
                                        <div class="avatar-circle-sm" style="width: 28px; height: 28px; font-size: 10px; background: #374151;"><?php echo $barbInit; ?></div>
                                    <?php endif; ?>
                                    <span style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($cita['barbero_nombre']); ?></span>
                                </div>
                            </td>
                        <?php endif; ?>

                        <td>
                            <?php if (floatval($cita['propina'] ?? 0) > 0): ?>
                                <strong style="color: #10B981; font-weight: 800;">+$<?php echo number_format($cita['propina'], 2); ?></strong>
                            <?php else: ?>
                                <span style="color: #888888;">$0.00</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local'): ?>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <select id="select_estado_citas_<?php echo $cita['id']; ?>" onchange="prepararGuardarEstadoCitas(<?php echo $cita['id']; ?>)" style="padding: 6px 10px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; border: 1px solid currentColor; outline: none; background: <?php echo ($cita['estado'] === 'completada' ? 'rgba(46, 204, 113, 0.15)' : ($cita['estado'] === 'confirmada' ? 'rgba(241, 196, 15, 0.15)' : ($cita['estado'] === 'cancelada' ? 'rgba(231, 76, 60, 0.15)' : 'rgba(149, 165, 166, 0.15)'))); ?>; color: <?php echo ($cita['estado'] === 'completada' ? '#27ae60' : ($cita['estado'] === 'confirmada' ? '#d35400' : ($cita['estado'] === 'cancelada' ? '#c0392b' : '#7f8c8d'))); ?>;">
                                        <option value="confirmada" <?php echo $cita['estado'] === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                        <option value="completada" <?php echo $cita['estado'] === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                        <option value="cancelada" <?php echo $cita['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                    <button type="button" id="btn_guardar_citas_<?php echo $cita['id']; ?>" onclick="guardarEstadoDirectoCitas(<?php echo $cita['id']; ?>, '<?php echo htmlspecialchars(addslashes($cita['cliente_nombre'])); ?>')" style="padding: 6px 14px; border-radius: 6px; background: #111111; color: #FFFFFF; border: 1px solid rgba(255,255,255,0.15); font-weight: 800; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: all 0.2s;" title="Guardar cambio de estado en toda la plataforma">
                                        <i class="fas fa-check-circle" style="font-size: 11px; color: var(--primary-gold);"></i> Guardar
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="status-badge status-<?php echo $cita['estado']; ?>">
                                    <?php echo strtoupper($cita['estado']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php if ($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local'): ?>
                                    <!-- ACCIONES ADMIN -->
                                    <?php if (!empty($cita['cliente_telefono'])): 
                                        $waPhone = formatPhoneForWhatsapp($cita['cliente_telefono']);
                                        $waMsg = urlencode("Hola " . explode(' ', $cita['cliente_nombre'])[0] . ", te saludamos de Kortzen Barbería respecto a tu cita agendada.");
                                    ?>
                                        <a href="https://wa.me/<?php echo $waPhone; ?>?text=<?php echo $waMsg; ?>" 
                                           target="_blank" 
                                           class="btn-action" 
                                           style="background: #25D366; color: #FFFFFF; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fab fa-whatsapp"></i> WHATSAPP CLIENTE
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($cita['estado'] !== 'completada' && $cita['estado'] !== 'cancelada'): ?>
                                        <button onclick="abrirModalTerminar(<?php echo $cita['id']; ?>)"
                                            class="btn-action btn-complete" style="background: #10B981; color: #FFFFFF; font-weight: 800;">FINALIZAR Y PROPINA</button>
                                    <?php endif; ?>
                                    <a href="citas_editar.php?id=<?php echo $cita['id']; ?>" class="btn-action">EDITAR</a>
                                    <button onclick="confirmarEliminar(<?php echo $cita['id']; ?>)"
                                        class="btn-action btn-delete">ELIMINAR</button>
                                <?php else: ?>
                                    <!-- BARBERO SOLO LECTURA -->
                                    <span style="font-size:0.75rem; color:#888; font-weight: 700;">Controlado por Admin</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay citas registradas
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Terminar Cita -->
<div id="modalTerminar" class="modal-overlay">
    <div class="modal-content">
        <h2 style="margin-bottom: 14px; color: #111827; font-weight: 900;">Finalizar Corte y Asignar Propina</h2>
        <p style="margin-bottom: 20px; color: #4B5563; font-size: 0.88rem;">Registra los materiales consumidos y la propina otorgada al barbero por el cliente:</p>

        <form id="formTerminar" method="POST" action="api/citas_action.php" onsubmit="enviarFormTerminar(event)">
            <input type="hidden" name="action" value="completar">
            <input type="hidden" name="id" id="citaIdTerminar">
            <input type="hidden" name="return_url" id="returnUrlTerminar" value="">

            <!-- Campo de Propina -->
            <div style="margin-bottom: 20px; background: #F9FAFB; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 14px;">
                <label style="display: block; font-weight: 800; font-size: 0.8rem; color: #111827; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                    Propina para el Barbero ($)
                </label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 10px; font-weight: 800; color: #374151;">$</span>
                    <input type="number" step="0.01" min="0" name="propina" id="inputPropinaTerminar" value="0.00" placeholder="0.00" style="width: 100%; padding: 10px 10px 10px 28px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 1.05rem; font-weight: 800; box-sizing: border-box;">
                </div>
                <span style="font-size: 0.75rem; color: #6B7280; margin-top: 4px; display: block;">Esta propina se sumará a los ingresos del barbero en un rubro independiente.</span>
            </div>

            <p style="margin-bottom: 12px; color: #4B5563; font-weight: 700; font-size: 0.85rem;">Materiales consumidos (opcional):</p>

            <div id="materialesList">
                <div class="material-row">
                    <select name="materiales[]" class="filter-select" style="flex: 1;">
                        <option value="">Seleccionar material...</option>
                        <?php foreach ($inventario as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']) . ' - ' . htmlspecialchars($item['sucursal']) . ' (' . $item['unidad'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cantidades[]" placeholder="Cant." class="filter-select"
                        style="width: 80px;" min="1" step="0.1">
                </div>
            </div>

            <button type="button" onclick="agregarFilaMaterial()" class="btn-secondary"
                style="margin-top: 10px; width: 100%;">+ Agregar otro material</button>

            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" onclick="cerrarModal()" class="btn-secondary" style="flex: 1;">Cancelar</button>
                <button type="submit" id="btnSubmitTerminar" class="btn-primary" style="flex: 1;">Confirmar y Terminar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmarEliminar(id) {
        if (confirm('¿Estás seguro de eliminar esta cita de la base de datos?')) {
            enviarAccion(id, 'delete');
        }
    }

    function confirmarCancelar(id) {
        if (confirm('¿Deseas cancelar esta cita?')) {
            enviarAccion(id, 'cancelar_barbero');
        }
    }

    function enviarAccion(id, action) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('id', id);
        formData.append('ajax', '1');
        formData.append('return_url', window.location.href);

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
                alert('Error: ' + (data.error || data.message || 'No se pudo procesar la solicitud.'));
            }
        })
        .catch(err => {
            window.location.reload();
        });
    }

    // Modal Logic
    function abrirModalTerminar(id) {
        document.getElementById('citaIdTerminar').value = id;
        document.getElementById('returnUrlTerminar').value = window.location.href;
        const pInput = document.getElementById('inputPropinaTerminar');
        if (pInput) pInput.value = '0.00';
        const submitBtn = document.getElementById('btnSubmitTerminar');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Confirmar y Terminar';
        }
        document.getElementById('modalTerminar').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('modalTerminar').style.display = 'none';
    }

    function enviarFormTerminar(e) {
        e.preventDefault();
        const form = document.getElementById('formTerminar');
        const submitBtn = document.getElementById('btnSubmitTerminar');
        const originalText = submitBtn ? submitBtn.innerHTML : 'Confirmar y Terminar';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');
        formData.append('return_url', window.location.href);

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
                cerrarModal();
                // Recargar la página conservando exactamente todos los filtros y parámetros de búsqueda actuales
                window.location.reload();
            } else {
                alert('Error al completar cita: ' + (data.error || data.message || 'Ocurrió un error en el servidor.'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        })
        .catch(err => {
            window.location.reload();
        });
    }

    function agregarFilaMaterial() {
        const div = document.createElement('div');
        div.className = 'material-row';
        div.innerHTML = `
            <select name="materiales[]" class="filter-select" style="flex: 1;">
                <option value="">Seleccionar material...</option>
                <?php foreach ($inventario as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo htmlspecialchars($item['producto']) . ' - ' . htmlspecialchars($item['sucursal']) . ' (' . $item['unidad'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #E74C3C; cursor: pointer;">✕</button>
        `;
    }

    function prepararGuardarEstadoCitas(id) {
        const select = document.getElementById('select_estado_citas_' + id);
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

    function guardarEstadoDirectoCitas(id, clienteNombre) {
        const select = document.getElementById('select_estado_citas_' + id);
        if (!select) return;
        const nuevoEstado = select.value;

        if (nuevoEstado === 'completada') {
            abrirModalTerminar(id);
        } else {
            const btn = document.getElementById('btn_guardar_citas_' + id);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            }

            const formData = new FormData();
            formData.append('action', 'cambiar_estado');
            formData.append('id', id);
            formData.append('estado', nuevoEstado);
            formData.append('ajax', '1');

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
                    alert('Error al guardar: ' + (data.error || data.message || 'Ocurrió un error en el servidor.'));
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Guardar';
                    }
                }
            })
            .catch(err => {
                window.location.reload();
            });
        }
    }

    // BUSCADOR PREDICTIVO CITAS
    const allCitasData = <?php echo json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'cliente_nombre' => $c['cliente_nombre'],
            'cliente_telefono' => !empty($c['cliente_telefono']) ? formatPhoneDisplay($c['cliente_telefono']) : '',
            'cliente_foto' => $c['cliente_foto'] ?? '',
            'servicio_nombre' => $c['servicio_nombre'],
            'barbero_nombre' => $c['barbero_nombre'],
            'barbero_foto' => $c['barbero_foto'] ?? '',
            'sucursal_nombre' => $c['sucursal_nombre'] ?? '',
            'fecha_hora' => date('d/m/Y H:i', strtotime($c['fecha_hora'])),
            'estado' => $c['estado']
        ];
    }, $citas)); ?>;

    const citaSearchInput = document.getElementById('predictiveCitaSearch');
    const citaSearchDropdown = document.getElementById('predictiveCitaDropdown');
    const citaRows = document.querySelectorAll('.cita-row');

    if (citaSearchInput && citaSearchDropdown) {
        citaSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();

            // 1. Instant table row filtering
            citaRows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!q || text.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });

            // 2. Dropdown predictive suggestions
            if (q.length === 0) {
                citaSearchDropdown.style.display = 'none';
                citaSearchDropdown.innerHTML = '';
                return;
            }

            // Clientes únicos
            const seenClients = new Set();
            const clientMatches = [];
            allCitasData.forEach(c => {
                const nom = (c.cliente_nombre || '').toLowerCase();
                const tel = (c.cliente_telefono || '').toLowerCase();
                if (nom.includes(q) || tel.includes(q)) {
                    const key = c.cliente_nombre.trim();
                    if (!seenClients.has(key)) {
                        seenClients.add(key);
                        clientMatches.push(c);
                    }
                }
            });

            // Servicios únicos
            const seenServices = new Set();
            const serviceMatches = [];
            allCitasData.forEach(c => {
                const serv = (c.servicio_nombre || '').toLowerCase();
                if (serv.includes(q)) {
                    const key = c.servicio_nombre.trim();
                    if (!seenServices.has(key)) {
                        seenServices.add(key);
                        serviceMatches.push(c);
                    }
                }
            });

            // Barberos únicos
            const seenBarbers = new Set();
            const barberMatches = [];
            allCitasData.forEach(c => {
                const barb = (c.barbero_nombre || '').toLowerCase();
                if (barb.includes(q)) {
                    const key = c.barbero_nombre.trim();
                    if (!seenBarbers.has(key)) {
                        seenBarbers.add(key);
                        barberMatches.push(c);
                    }
                }
            });

            if (clientMatches.length > 0 || serviceMatches.length > 0 || barberMatches.length > 0) {
                let html = '';

                // Clientes
                clientMatches.slice(0, 6).forEach(m => {
                    const cliInit = (m.cliente_nombre || 'C').charAt(0).toUpperCase();
                    const cliAvatar = m.cliente_foto 
                        ? `<img src="${m.cliente_foto}" class="avatar-circle-sm" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><div class="avatar-circle-sm" style="display:none;">${cliInit}</div>` 
                        : `<div class="avatar-circle-sm">${cliInit}</div>`;

                    html += `
                        <div class="predictive-item" onclick="seleccionarFiltroTextoCita('${m.cliente_nombre.replace(/'/g, "\\'")}')">
                            ${cliAvatar}
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        ${m.cliente_nombre}
                                    </div>
                                    <span style="font-size: 10px; font-weight: 700; color: #4B5563; background: #F3F4F6; padding: 2px 7px; border-radius: 6px;">👤 Cliente</span>
                                </div>
                                <div style="font-size: 0.76rem; color: #6B7280; margin-top: 1px;">
                                    📞 ${m.cliente_telefono || 'Sin teléfono'}
                                </div>
                            </div>
                        </div>
                    `;
                });

                // Servicios
                serviceMatches.slice(0, 3).forEach(m => {
                    html += `
                        <div class="predictive-item" onclick="seleccionarFiltroTextoCita('${m.servicio_nombre.replace(/'/g, "\\'")}')">
                            <div class="avatar-circle-sm" style="background:#FEF3C7; color:#B45309;">✂️</div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827;">
                                        ${m.servicio_nombre}
                                    </div>
                                    <span style="font-size: 10px; font-weight: 700; color: #B45309; background: #FEF3C7; padding: 2px 7px; border-radius: 6px;">Servicio</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                // Barberos
                barberMatches.slice(0, 3).forEach(m => {
                    const barbInit = (m.barbero_nombre || 'B').charAt(0).toUpperCase();
                    const barbAvatar = m.barbero_foto 
                        ? `<img src="${m.barbero_foto}" class="avatar-circle-sm" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><div class="avatar-circle-sm" style="display:none; background:#374151;">${barbInit}</div>` 
                        : `<div class="avatar-circle-sm" style="background:#374151;">${barbInit}</div>`;

                    html += `
                        <div class="predictive-item" onclick="seleccionarFiltroTextoCita('${m.barbero_nombre.replace(/'/g, "\\'")}')">
                            ${barbAvatar}
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827;">
                                        ${m.barbero_nombre}
                                    </div>
                                    <span style="font-size: 10px; font-weight: 700; color: #111; background: #E5E7EB; padding: 2px 7px; border-radius: 6px;">Barbero</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                citaSearchDropdown.innerHTML = html;
                citaSearchDropdown.style.display = 'block';
            } else {
                citaSearchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron resultados</div>';
                citaSearchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!citaSearchInput.contains(e.target) && !citaSearchDropdown.contains(e.target)) {
                citaSearchDropdown.style.display = 'none';
            }
        });
    }

    function seleccionarFiltroTextoCita(texto) {
        if (citaSearchInput) {
            citaSearchInput.value = texto;
            citaSearchInput.dispatchEvent(new Event('input'));
        }
        if (citaSearchDropdown) {
            citaSearchDropdown.style.display = 'none';
        }
    }

    function seleccionarCitaPredictiva(id) {
        citaSearchDropdown.style.display = 'none';
        const row = document.getElementById('cita-row-' + id);
        if (row) {
            citaRows.forEach(r => r.style.display = 'none');
            row.style.display = '';
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.background = '#FEF3C7';
            setTimeout(() => {
                row.style.background = '';
            }, 2500);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
