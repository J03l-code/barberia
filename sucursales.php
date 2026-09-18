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

<!-- Buscador Predictivo de Sucursales -->
<div style="position: relative; margin-bottom: 24px; max-width: 500px;">
    <div style="position: relative; display: flex; align-items: center;">
        <i class="fas fa-search search-icon" style="position: absolute; left: 14px; color: #9CA3AF; font-size: 14px; pointer-events: none;"></i>
        <input type="text" 
               id="predictiveSucursalSearch" 
               placeholder="Buscar sucursal por nombre, dirección o teléfono..." 
               class="search-input"
               autocomplete="off"
               style="width: 100%; padding: 11px 16px 11px 40px; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 10px; font-size: 14px; font-weight: 500; color: #111827; outline: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div id="predictiveSucursalDropdown" class="predictive-dropdown"></div>
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

    .sucursal-icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #F3F4F6;
        color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        border: 1px solid #E5E7EB;
    }
</style>

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
                <?php foreach ($sucursales as $sucursal): 
                    $est = $sucursal['estado'] ?? 'activo';
                    $searchData = strtolower($sucursal['nombre'] . ' ' . $sucursal['direccion'] . ' ' . $sucursal['telefono'] . ' ' . $est);
                ?>
                    <tr class="sucursal-row" id="sucursal-row-<?php echo $sucursal['id']; ?>" data-search="<?php echo htmlspecialchars($searchData); ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="sucursal-icon-box">📍</div>
                                <div>
                                    <strong style="color: #111827; font-size: 14px;"><?php echo htmlspecialchars($sucursal['nombre']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($sucursal['direccion']); ?></td>
                        <td><?php echo htmlspecialchars($sucursal['telefono']); ?></td>
                        <td>
                            <?php
                            echo date('H:i', strtotime($sucursal['horario_apertura'])) . ' - ' .
                                date('H:i', strtotime($sucursal['horario_cierre']));
                            ?>
                        </td>
                        <td>
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
    const allSucursalesData = <?php echo json_encode(array_map(function($s) {
        return [
            'id' => $s['id'],
            'nombre' => $s['nombre'],
            'direccion' => $s['direccion'] ?: '',
            'telefono' => $s['telefono'] ?: '',
            'estado' => $s['estado'] ?? 'activo'
        ];
    }, $sucursales)); ?>;

    const sucursalSearchInput = document.getElementById('predictiveSucursalSearch');
    const sucursalSearchDropdown = document.getElementById('predictiveSucursalDropdown');
    const sucursalRows = document.querySelectorAll('.sucursal-row');

    if (sucursalSearchInput && sucursalSearchDropdown) {
        sucursalSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();

            // 1. Instant table filtering
            sucursalRows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!q || text.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });

            // 2. Predictive Suggestions
            if (q.length === 0) {
                sucursalSearchDropdown.style.display = 'none';
                sucursalSearchDropdown.innerHTML = '';
                return;
            }

            const matches = allSucursalesData.filter(s => 
                s.nombre.toLowerCase().includes(q) ||
                s.direccion.toLowerCase().includes(q) ||
                s.telefono.toLowerCase().includes(q)
            ).slice(0, 6);

            if (matches.length > 0) {
                let html = '';
                matches.forEach(m => {
                    const statusText = m.estado === 'activo' ? '🟢 Activa' : (m.estado === 'proximamente' ? '⏳ Próximamente' : '🔴 Inactiva');
                    html += `
                        <div class="predictive-item" onclick="seleccionarSucursalPredictiva(${m.id})">
                            <div class="sucursal-icon-box">📍</div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        ${m.nombre}
                                    </div>
                                    <span style="font-size: 0.72rem; font-weight: 800;">${statusText}</span>
                                </div>
                                <div style="font-size: 0.76rem; color: #6B7280; margin-top: 2px;">
                                    🏠 ${m.direccion} ${m.telefono ? ' • 📞 ' + m.telefono : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                sucursalSearchDropdown.innerHTML = html;
                sucursalSearchDropdown.style.display = 'block';
            } else {
                sucursalSearchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron sucursales coincidentes</div>';
                sucursalSearchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!sucursalSearchInput.contains(e.target) && !sucursalSearchDropdown.contains(e.target)) {
                sucursalSearchDropdown.style.display = 'none';
            }
        });
    }

    function seleccionarSucursalPredictiva(id) {
        sucursalSearchDropdown.style.display = 'none';
        const row = document.getElementById('sucursal-row-' + id);
        if (row) {
            sucursalRows.forEach(r => r.style.display = 'none');
            row.style.display = '';
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.background = '#FEF3C7';
            setTimeout(() => {
                row.style.background = '';
            }, 2500);
        }
    }

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
