<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageBranches());
$currentUser = getCurrentUser();

// Obtener sucursales
try {
    $sucursales = query("SELECT * FROM sucursales ORDER BY id DESC");
} catch (PDOException $e) {
    error_log("Error al obtener sucursales: " . $e->getMessage());
    $sucursales = [];
}

$pageTitle = 'Sucursales';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Sucursales</h1>
    <?php if (canManageBranches()): ?>
        <button onclick="window.location.href='sucursales_crear.php'" class="btn btn-primary">+ AÑADIR SUCURSAL</button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27ae60; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 18px;">✓</span> <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #c0392b; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 18px;">⚠</span> <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-active {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
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
                <th>SUCURSAL</th>
                <th>DIRECCIÓN</th>
                <th>TELÉFONO</th>
                <th>HORARIO</th>
                <th>ESTADO</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($sucursales) > 0): ?>
                <?php foreach ($sucursales as $sucursal): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sucursal['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sucursal['direccion']); ?></td>
                        <td><?php echo htmlspecialchars($sucursal['telefono']); ?></td>
                        <td>
                            <?php
                            echo date('H:i', strtotime($sucursal['horario_apertura'])) . ' - ' .
                                date('H:i', strtotime($sucursal['horario_cierre']));
                            ?>
                        </td>
                        <td>
                            <?php $est = $sucursal['estado'] ?? 'activo'; ?>
                            <?php if (canManageBranches()): ?>
                                <select onchange="cambiarEstadoSucursal(<?php echo $sucursal['id']; ?>, this.value)" 
                                        style="padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; background: <?php echo ($est === 'activo' ? 'rgba(46, 204, 113, 0.15)' : ($est === 'proximamente' ? 'rgba(241, 196, 15, 0.15)' : 'rgba(149, 165, 166, 0.15)')); ?>; color: <?php echo ($est === 'activo' ? '#27ae60' : ($est === 'proximamente' ? '#d35400' : '#7f8c8d')); ?>; border: 1px solid currentColor; outline: none;">
                                    <option value="activo" <?php echo $est === 'activo' ? 'selected' : ''; ?>>🟢 ACTIVA</option>
                                    <option value="proximamente" <?php echo $est === 'proximamente' ? 'selected' : ''; ?>>⏳ PRÓXIMAMENTE</option>
                                    <option value="inactivo" <?php echo $est === 'inactivo' ? 'selected' : ''; ?>>🔴 INACTIVA</option>
                                </select>
                            <?php else: ?>
                                <?php if ($est === 'activo'): ?>
                                    <span class="status-badge status-active">🟢 ACTIVA</span>
                                <?php elseif ($est === 'proximamente'): ?>
                                    <span class="status-badge" style="background: rgba(241, 196, 15, 0.15); color: #D4AC0D; border: 1px solid rgba(241, 196, 15, 0.3);">⏳ PRÓXIMAMENTE</span>
                                <?php else: ?>
                                    <span class="status-badge" style="background: rgba(149, 165, 166, 0.15); color: #7F8C8D; border: 1px solid rgba(149, 165, 166, 0.3);">🔴 INACTIVA</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php if (canManageBranches()): ?>
                                    <a href="sucursales_editar.php?id=<?php echo $sucursal['id']; ?>" class="btn-action">EDITAR</a>
                                    <button
                                        onclick="confirmarEliminar(<?php echo $sucursal['id']; ?>, '<?php echo htmlspecialchars($sucursal['nombre']); ?>')"
                                        class="btn-action btn-delete">ELIMINAR</button>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Solo lectura</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay sucursales registradas
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function cambiarEstadoSucursal(id, nuevoEstado) {
        const formData = new FormData();
        formData.append('action', 'change_status');
        formData.append('id', id);
        formData.append('estado', nuevoEstado);

        fetch('api/sucursales_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error al actualizar el estado de la sucursal');
            }
        })
        .catch(err => {
            console.error(err);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/sucursales_action.php';
            form.innerHTML = `<input type="hidden" name="action" value="change_status"><input type="hidden" name="id" value="${id}"><input type="hidden" name="estado" value="${nuevoEstado}">`;
            document.body.appendChild(form);
            form.submit();
        });
    }

    function confirmarEliminar(id, nombre) {
        if (confirm('¿Estás seguro de eliminar la sucursal "' + nombre + '"?\n\nEsto eliminará también todo el inventario asociado.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/sucursales_action.php';

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
