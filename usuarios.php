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

<!-- Buscador Predictivo de Usuarios / Barberos -->
<div style="position: relative; margin-bottom: 24px; max-width: 500px;">
    <div style="position: relative; display: flex; align-items: center;">
        <i class="fas fa-search search-icon" style="position: absolute; left: 14px; color: #9CA3AF; font-size: 14px; pointer-events: none;"></i>
        <input type="text" 
               id="predictiveUserSearch" 
               placeholder="Buscar por nombre, barbero, rol, email o sucursal..." 
               class="search-input"
               autocomplete="off"
               style="width: 100%; padding: 11px 16px 11px 40px; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 10px; font-size: 14px; font-weight: 500; color: #111827; outline: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div id="predictiveUserDropdown" class="predictive-dropdown"></div>
</div>

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
                    
                    $userSucText = $usuario['rol'] === 'admin' ? 'Global' : ($usuario['sucursal_nombre'] ?? 'Sin asignar');
                    $searchData = strtolower($usuario['nombre'] . ' ' . $usuario['email'] . ' ' . $rol_texto . ' ' . $userSucText);
                    ?>
                    <tr class="usuario-row" id="user-row-<?php echo $usuario['id']; ?>" data-search="<?php echo htmlspecialchars($searchData); ?>">
                        <td>
                            <a href="barbero_detalle.php?id=<?php echo $usuario['id']; ?>" style="text-decoration: none; color: inherit;" title="Ver perfil completo y stock">
                                <div class="user-cell">
                                    <?php if (!empty($usuario['foto_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($usuario['foto_url']); ?>" 
                                             class="user-avatar" 
                                             style="object-fit: cover;"
                                             alt="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="user-avatar" style="display: none;"><?php echo $iniciales; ?></div>
                                    <?php else: ?>
                                        <div class="user-avatar"><?php echo $iniciales; ?></div>
                                    <?php endif; ?>
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
                                        echo '<span style="background: #111111; color: #FFFFFF; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">' . htmlspecialchars($bName) . '</span>';
                                    }
                                    echo '</div>';
                                } elseif (!empty($usuario['sucursal_nombre'])) {
                                    echo '<span style="background: #111111; color: #FFFFFF; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">' . htmlspecialchars($usuario['sucursal_nombre']) . '</span>';
                                } else {
                                    echo '<span style="color: #999999; font-size: 0.85rem;">Ninguna</span>';
                                }
                            } else {
                                echo !empty($usuario['sucursal_nombre']) ? htmlspecialchars($usuario['sucursal_nombre']) : '<span style="color: #999999; font-size: 0.85rem;">Sin asignar</span>';
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
    const allUsersData = <?php echo json_encode(array_map(function($u) {
        $userRolTxt = getRolDisplayName($u['rol']);
        return [
            'id' => $u['id'],
            'nombre' => $u['nombre'],
            'email' => $u['email'],
            'rol' => $userRolTxt,
            'sucursal' => $u['rol'] === 'admin' ? 'Global' : ($u['sucursal_nombre'] ?? 'Sin asignar'),
            'foto' => $u['foto_url'] ?? ''
        ];
    }, $usuarios)); ?>;

    const userSearchInput = document.getElementById('predictiveUserSearch');
    const userSearchDropdown = document.getElementById('predictiveUserDropdown');
    const userRows = document.querySelectorAll('.usuario-row');

    if (userSearchInput && userSearchDropdown) {
        userSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();

            // 1. Instant Table Row Filtering
            userRows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!q || text.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });

            // 2. Predictive Suggestions
            if (q.length === 0) {
                userSearchDropdown.style.display = 'none';
                userSearchDropdown.innerHTML = '';
                return;
            }

            const matches = allUsersData.filter(u => 
                u.nombre.toLowerCase().includes(q) ||
                u.email.toLowerCase().includes(q) ||
                u.rol.toLowerCase().includes(q) ||
                u.sucursal.toLowerCase().includes(q)
            ).slice(0, 6);

            if (matches.length > 0) {
                let html = '';
                matches.forEach(m => {
                    const initial = (m.nombre || 'U').charAt(0).toUpperCase();
                    const avatarHtml = m.foto 
                        ? `<img src="${m.foto}" class="user-avatar" style="width:34px;height:34px;font-size:11px;" onerror="this.onerror=null; this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:34px;height:34px;font-size:11px;\\'>${initial}</div>';">` 
                        : `<div class="user-avatar" style="width:34px;height:34px;font-size:11px;">${initial}</div>`;

                    html += `
                        <a href="barbero_detalle.php?id=${m.id}" class="predictive-item">
                            ${avatarHtml}
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${m.nombre}</div>
                                    <span class="role-badge" style="font-size: 9px; padding: 2px 6px;">${m.rol}</span>
                                </div>
                                <div style="font-size: 0.76rem; color: #6B7280; margin-top: 2px;">
                                    ✉️ ${m.email} • 📍 ${m.sucursal}
                                </div>
                            </div>
                        </a>
                    `;
                });
                userSearchDropdown.innerHTML = html;
                userSearchDropdown.style.display = 'block';
            } else {
                userSearchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron usuarios coincidentes</div>';
                userSearchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!userSearchInput.contains(e.target) && !userSearchDropdown.contains(e.target)) {
                userSearchDropdown.style.display = 'none';
            }
        });
    }

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
