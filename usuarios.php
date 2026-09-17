<?php
require_once 'config.php';
requireLogin();
requirePermission(canViewUsers());
$currentUser = getCurrentUser();

// Filtros
$sucursal_id = isset($_GET['sucursal_id']) && $_GET['sucursal_id'] !== '' ? intval($_GET['sucursal_id']) : '';

// Obtener sucursales permitidas para este usuario
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$sucursales_lista = [];
try {
    if (isAdminTecnico()) {
        $sucursales_lista = query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
    } else {
        $sucursales_lista = getUsuarioSucursales($currentUser['id']);
    }
} catch (Exception $e) {
    $sucursales_lista = [];
}

// Mapa de sucursales por usuario (para admin_local multi-sucursal)
$userBranchesMap = [];
try {
    $rowsB = query("SELECT us.usuario_id, s.nombre FROM usuarios_sucursales us JOIN sucursales s ON us.sucursal_id = s.id ORDER BY s.nombre ASC");
    foreach ($rowsB as $r) {
        $userBranchesMap[$r['usuario_id']][] = $r['nombre'];
    }
} catch (Exception $e) {}

// Obtener usuarios con filtro
try {
    $sql = "SELECT u.*, s.nombre as sucursal_nombre 
            FROM usuarios u 
            LEFT JOIN sucursales s ON u.sucursal_id = s.id 
            WHERE 1=1";
    $params = [];

    if (!isAdminTecnico()) {
        // Scoping para Admin Local: Solo ver usuarios de sus sucursales asignadas o su propio usuario
        if (!empty($userSucursalesIds)) {
            $inList = implode(',', array_map('intval', $userSucursalesIds));
            if ($sucursal_id !== '' && in_array($sucursal_id, $userSucursalesIds)) {
                $sql .= " AND (u.sucursal_id = ? OR u.id = ?)";
                $params[] = $sucursal_id;
                $params[] = $currentUser['id'];
            } else {
                $sql .= " AND (u.sucursal_id IN ($inList) OR u.id = ?)";
                $params[] = $currentUser['id'];
            }
        } else {
            $sql .= " AND u.id = ?";
            $params[] = $currentUser['id'];
        }
    } else {
        if ($sucursal_id !== '') {
            $sql .= " AND u.sucursal_id = ?";
            $params[] = $sucursal_id;
        }
    }

    $sql .= " ORDER BY u.fecha_creacion DESC";
    $usuarios = query($sql, $params);
} catch (PDOException $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    $usuarios = [];
}

$pageTitle = 'Usuarios';
include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 28px;">
    <h1 class="page-title" style="margin: 0;">Gestión de Usuarios</h1>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <form method="GET" id="filterFormUsuarios" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 0;">
            <!-- Filtro por Sucursal -->
            <select name="sucursal_id" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas las sucursales</option>
                <?php foreach ($sucursales_lista as $suc): ?>
                    <option value="<?php echo $suc['id']; ?>" <?php echo (string)$sucursal_id === (string)$suc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($suc['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($sucursal_id !== ''): ?>
                <a href="usuarios.php" class="btn btn-secondary" style="padding: 9px 14px; text-decoration: none;">Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if (canManageUsers()): ?>
            <button onclick="window.location.href='usuarios_crear.php'" class="btn btn-primary" style="white-space: nowrap;">+ AÑADIR USUARIO</button>
        <?php endif; ?>
    </div>
</div>

<style>
    .filter-select {
        padding: 9px 14px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .filter-select:hover,
    .filter-select:focus {
        border-color: #111111;
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #333333, #555555);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: #FFFFFF;
        flex-shrink: 0;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .user-name {
        font-weight: 500;
        color: var(--text-primary);
        font-size: 14px;
    }

    .user-phone {
        font-size: 12px;
        color: var(--text-muted);
    }

    .role-badge {
        padding: 6px 14px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
        background: rgba(51, 51, 51, 0.1);
        color: #333333;
        border: 1px solid rgba(51, 51, 51, 0.2);
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
        color: #FFFFFF;
    }

    .btn-delete {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .btn-delete:hover {
        background: #E74C3C;
        color: #FFFFFF;
    }

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
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
                <th>USUARIO</th>
                <th>EMAIL</th>
                <th>ROL</th>
                <th>SUCURSAL</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($usuarios) > 0): ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                    // Generar iniciales
                    $nombres = explode(' ', $usuario['nombre']);
                    $iniciales = '';
                    if (count($nombres) >= 2) {
                        $iniciales = strtoupper(substr($nombres[0], 0, 1) . substr($nombres[1], 0, 1));
                    } else {
                        $iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
                    }

                    // Determinar tipo de rol
                    $rol_texto = getRolDisplayName($usuario['rol']);

                    // Generar teléfono ficticio basado en ID
                    $telefono = '+34 ' . (900 + $usuario['id']) . ' ' . str_pad($usuario['id'] * 123, 6, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td>
                            <a href="barbero_detalle.php?id=<?php echo $usuario['id']; ?>" style="text-decoration: none; color: inherit;" title="Ver perfil completo y stock">
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo $iniciales; ?></div>
                                    <div class="user-info">
                                        <div class="user-name" style="font-weight: 800; color: #111111;"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                        <div class="user-phone"><?php echo $telefono; ?></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <span class="role-badge"><?php echo $rol_texto; ?></span>
                        </td>
                        <td>
                            <?php 
                            if ($usuario['rol'] === 'admin') {
                                echo '<span style="color: #666666; font-size: 0.85rem; font-weight: 700;">Global (Todas)</span>';
                            } elseif ($usuario['rol'] === 'admin_local') {
                                $assignedNames = $userBranchesMap[$usuario['id']] ?? [];
                                if (!empty($assignedNames)) {
                                    echo '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
                                    foreach ($assignedNames as $bName) {
                                        echo '<span style="background: #111111; color: #FFFFFF; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">📍 ' . htmlspecialchars($bName) . '</span>';
                                    }
                                    echo '</div>';
                                } elseif (!empty($usuario['sucursal_nombre'])) {
                                    echo '<span style="background: #111111; color: #FFFFFF; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">📍 ' . htmlspecialchars($usuario['sucursal_nombre']) . '</span>';
                                } else {
                                    echo '<span style="color: #999999; font-size: 0.85rem;">Ninguna</span>';
                                }
                            } else {
                                echo !empty($usuario['sucursal_nombre']) ? '📍 ' . htmlspecialchars($usuario['sucursal_nombre']) : '<span style="color: #999999; font-size: 0.85rem;">Sin asignar</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="barbero_detalle.php?id=<?php echo $usuario['id']; ?>" class="btn-action" style="background: #111111; color: #FFFFFF; border: none; font-weight: 800;">VER PERFIL & STOCK</a>
                                <?php if (canManageUsers()): ?>
                                    <a href="usuarios_editar.php?id=<?php echo $usuario['id']; ?>" class="btn-action">EDITAR</a>
                                    <?php if ($usuario['id'] != $currentUser['id']): ?>
                                        <button
                                            onclick="confirmarEliminar(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')"
                                            class="btn-action btn-delete">ELIMINAR</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Solo lectura</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted); font-size: 14px;">
                        <?php echo $sucursal_id !== '' ? 'No hay usuarios ni barberos registrados en esta sucursal.' : 'No hay usuarios registrados.'; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function confirmarEliminar(id, nombre) {
        if (confirm('¿Estás seguro de eliminar el usuario "' + nombre + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/usuarios_action.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
