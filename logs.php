<?php
require_once 'config.php';
requireLogin();
requirePermission(canViewLogs());
$currentUser = getCurrentUser();

// Filtros
$tabla_filter = $_GET['tabla'] ?? '';
$usuario_filter = $_GET['usuario_id'] ?? '';

// Obtener logs
$db_error = null;
try {
    asegurarTablaLogs();
    $pdo = getConnection();

    // Si la tabla está vacía, registrar un log inicial para verificar funcionamiento
    $cntStmt = $pdo->query("SELECT COUNT(*) FROM logs_actividad");
    if ($cntStmt && $cntStmt->fetchColumn() == 0) {
        registrarLog('SISTEMA', 'logs', 0, 'Sistema de Registro de Actividad KORTZEN activado correctamente');
    }

    $sql = "SELECT l.*, u.nombre as usuario_nombre, c.nombre as cliente_nombre 
            FROM logs_actividad l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN clientes c ON l.cliente_id = c.id
            WHERE 1=1";

    $params = [];

    if ($tabla_filter) {
        $sql .= " AND l.tabla_afectada = ?";
        $params[] = $tabla_filter;
    }

    if ($usuario_filter) {
        $sql .= " AND (l.usuario_id = ? OR l.cliente_id = ?)";
        $params[] = $usuario_filter;
        $params[] = $usuario_filter;
    }

    $sql .= " ORDER BY l.fecha_hora DESC LIMIT 300";

    $logs = query($sql, $params);

    // Obtener usuarios y clientes para filtro
    $usuarios = query("SELECT id, nombre FROM usuarios ORDER BY nombre ASC");
    $tablasDisponibles = query("SELECT DISTINCT tabla_afectada FROM logs_actividad WHERE tabla_afectada IS NOT NULL AND tabla_afectada != '' ORDER BY tabla_afectada ASC");

} catch (Exception $e) {
    error_log("Error al obtener logs: " . $e->getMessage());
    $db_error = $e->getMessage();
    $logs = [];
    $usuarios = [];
    $tablasDisponibles = [];
}

$pageTitle = 'Logs';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Registro de Actividad del Sistema</h1>
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <select name="tabla" class="filter-select" onchange="this.form.submit()">
            <option value="">Todas las áreas / tablas</option>
            <?php foreach ($tablasDisponibles as $t): ?>
                <option value="<?php echo htmlspecialchars($t['tabla_afectada']); ?>" <?php echo $tabla_filter == $t['tabla_afectada'] ? 'selected' : ''; ?>>
                    <?php echo ucfirst(htmlspecialchars($t['tabla_afectada'])); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="usuario_id" class="filter-select" onchange="this.form.submit()">
            <option value="">Todos los usuarios</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filter == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>

    </form>
</div>

<?php if ($db_error): ?>
    <div style="background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid #EF4444; padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 700; font-size: 0.9rem;">
        ⚠️ Error de Base de Datos al consultar logs: <?php echo htmlspecialchars($db_error); ?>
    </div>
<?php endif; ?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .filter-select {
        padding: 11px 16px;
        background: #1F1F1F !important;
        border: 1px solid #333333 !important;
        border-radius: 8px;
        color: #FFFFFF !important;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        min-width: 200px;
        outline: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
    }

    .filter-select option {
        background: #1A1A1A !important;
        color: #FFFFFF !important;
        padding: 10px;
        font-weight: 600;
    }

    .filter-select:focus,
    .filter-select:hover {
        border-color: #C0A062 !important;
        background: #282828 !important;
        box-shadow: 0 0 10px rgba(192, 160, 98, 0.3);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .action-badge {
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-create {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
    }

    .action-update {
        background: rgba(59, 130, 246, 0.12);
        color: #3B82F6;
    }

    .action-delete {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>FECHA/HORA</th>
                <th>USUARIO</th>
                <th>ACCIÓN</th>
                <th>TABLA</th>
                <th>DESCRIPCIÓN</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong>
                                <?php echo date('d/m/Y', strtotime($log['fecha_hora'])); ?>
                            </strong><br>
                            <span style="color: var(--text-muted); font-size: 12px;">
                                <?php echo date('H:i:s', strtotime($log['fecha_hora'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                if (!empty($log['usuario_nombre'])) {
                                    echo '<strong>' . htmlspecialchars($log['usuario_nombre']) . '</strong> <span style="font-size:0.75rem; color:#888;">(Personal)</span>';
                                } elseif (!empty($log['cliente_nombre'])) {
                                    echo '<strong>' . htmlspecialchars($log['cliente_nombre']) . '</strong> <span style="font-size:0.75rem; color:#10B981;">(Cliente PWA)</span>';
                                } elseif (!empty($log['usuario_id'])) {
                                    echo '<strong>Usuario #' . intval($log['usuario_id']) . '</strong> <span style="font-size:0.75rem; color:#888;">(Personal)</span>';
                                } elseif (!empty($log['cliente_id'])) {
                                    echo '<strong>Cliente #' . intval($log['cliente_id']) . '</strong> <span style="font-size:0.75rem; color:#10B981;">(Cliente PWA)</span>';
                                } else {
                                    echo '<span style="color:#C0A062; font-weight:700;">Sistema / Administrador</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <span class="action-badge action-<?php echo strtolower($log['accion']); ?>">
                                <?php echo htmlspecialchars($log['accion']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['tabla_afectada']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['descripcion']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay registros de actividad
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
