<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageServices());
$currentUser = getCurrentUser();

// Obtener categorías y servicios
try {
    $pdo = getConnection();
    asegurarTablaCategorias($pdo);
    $categoriasList = getCategoriasServicios($pdo, false);

    // Listado de todos los barberos activos para conteos
    $totalActiveBarbers = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND (rol = 'barbero' OR rol = 'admin_local' OR rol = 'admin')")->fetchColumn();

    $sql = "SELECT s.*, cs.orden as cat_orden, u.nombre as barbero_asignado_nombre 
            FROM servicios s 
            LEFT JOIN categorias_servicios cs ON s.categoria = cs.nombre 
            LEFT JOIN usuarios u ON s.barbero_id = u.id 
            ORDER BY COALESCE(cs.orden, 999) ASC, s.categoria ASC, COALESCE(s.orden, 999) ASC, s.id ASC";
    $servicios = query($sql);

    // Obtener asignaciones de servicios_barberos
    $barbersByService = [];
    try {
        $sbRows = $pdo->query("
            SELECT sb.servicio_id, sb.barbero_id, u.nombre as barbero_nombre 
            FROM servicios_barberos sb 
            JOIN usuarios u ON sb.barbero_id = u.id 
            WHERE u.activo = 1 
            ORDER BY u.nombre ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sbRows as $r) {
            $barbersByService[$r['servicio_id']][] = $r['barbero_nombre'];
        }
    } catch (Throwable $e) {}

} catch (PDOException $e) {
    error_log("Error al obtener servicios: " . $e->getMessage());
    $servicios = [];
    $categoriasList = [];
    $barbersByService = [];
    $totalActiveBarbers = 0;
}

$pageTitle = 'Servicios';
include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title" style="margin-bottom: 4px;">Catálogo de Servicios</h1>
        <p style="color: var(--text-muted, #6B7280); font-size: 14px; margin: 0;">
            Administra los servicios, precios, duraciones, categorías y el <strong>orden exacto de aparición</strong> en la web.
        </p>
    </div>
    <?php if (!$isReadOnly): ?>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <a href="categorias_servicios.php" class="btn-secondary-custom" style="padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; border: 1px solid #D1D5DB; border-radius: 6px; background: #FFFFFF; color: #374151; transition: all 0.2s ease;">
                <span>🏷️</span> GESTIONAR Y ORDENAR CATEGORÍAS
            </a>
            <button onclick="window.location.href='servicios_crear.php'" class="btn btn-primary">+ AÑADIR SERVICIO</button>
        </div>
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

<?php if (!empty($categoriasList)): ?>
    <div style="display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px;">
        <button type="button" onclick="filterByCategory('all')" class="cat-filter-pill active" id="pill-all">
            Todos (<?php echo count($servicios); ?>)
        </button>
        <?php foreach ($categoriasList as $cl): ?>
            <button type="button" onclick="filterByCategory('<?php echo htmlspecialchars(addslashes($cl['nombre'])); ?>')" class="cat-filter-pill" id="pill-<?php echo md5($cl['nombre']); ?>">
                <span style="opacity: 0.65; font-size: 0.85em; margin-right: 2px;">#<?php echo intval($cl['orden']); ?></span>
                <?php echo htmlspecialchars($cl['nombre']); ?> (<?php echo intval($cl['total_servicios'] ?? 0); ?>)
            </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Buscador Predictivo de Servicios -->
<div style="position: relative; margin-bottom: 24px; max-width: 500px;">
    <div style="position: relative; display: flex; align-items: center;">
        <i class="fas fa-search search-icon" style="position: absolute; left: 14px; color: #9CA3AF; font-size: 14px; pointer-events: none;"></i>
        <input type="text" 
               id="predictiveServiceSearch" 
               placeholder="Buscar servicio por nombre, categoría, precio, duración..." 
               class="search-input"
               autocomplete="off"
               style="width: 100%; padding: 11px 16px 11px 40px; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 10px; font-size: 14px; font-weight: 500; color: #111827; outline: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div id="predictiveServiceDropdown" class="predictive-dropdown"></div>
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

    .service-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #F3F4F6;
        color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        border: 1px solid #E5E7EB;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .table-container {
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th,
    .table td {
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: rgba(46, 204, 113, 0.15);
        color: #2ECC71;
    }

    .status-inactive {
        background: rgba(231, 76, 60, 0.15);
        color: #E74C3C;
    }

    .btn-action {
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 4px;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:first-child {
        background: #333333;
        color: #FFFFFF;
    }

    .btn-action:first-child:hover {
        background: #111111;
    }

    .btn-delete {
        background: transparent;
        color: #E74C3C;
        border: 1px solid #E74C3C;
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

    .service-description {
        color: var(--text-muted);
        font-size: 13px;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cat-filter-pill {
        padding: 8px 16px;
        background: #FFFFFF;
        border: 1px solid #D1D5DB;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        color: #4B5563;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .cat-filter-pill:hover {
        background: #F3F4F6;
        color: #111827;
        border-color: #9CA3AF;
    }

    .cat-filter-pill.active {
        background: #111827;
        color: #FFFFFF;
        border-color: #111827;
    }

    .service-row.hidden-by-cat {
        display: none !important;
    }

    .order-input-box {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #F8FAFC;
        border: 1.5px solid #E2E8F0;
        border-radius: 8px;
        padding: 3px 8px;
        transition: all 0.2s ease;
    }
    .order-input-box:focus-within {
        border-color: #111827;
        background: #FFFFFF;
        box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.1);
    }
    .order-num-input {
        width: 42px;
        border: none;
        background: transparent;
        font-weight: 800;
        font-size: 13px;
        color: #1E293B;
        text-align: center;
        outline: none;
    }
    .order-save-indicator {
        font-size: 11px;
        color: #10B981;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .order-save-indicator.show {
        opacity: 1;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th style="width: 100px; text-align: center;">POSICIÓN</th>
                <th>SERVICIO</th>
                <th>CATEGORÍA</th>
                <th>PRECIO</th>
                <th>DURACIÓN</th>
                <th>ESTADO</th>
                <?php if (!$isReadOnly): ?>
                    <th>ACCIONES</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="servicesTableBody">
            <?php if (count($servicios) > 0): ?>
                <?php foreach ($servicios as $servicio): 
                    $catVal = trim($servicio['categoria'] ?? 'General');
                    $sOrden = intval($servicio['orden'] ?? 1);
                    $assignedList = $barbersByService[$servicio['id']] ?? [];
                    $barbNamesStr = implode(' ', $assignedList);
                    $searchData = strtolower($servicio['nombre'] . ' ' . $catVal . ' ' . ($servicio['descripcion'] ?? '') . ' ' . $barbNamesStr . ' ' . $servicio['precio'] . ' ' . $servicio['duracion_minutos']);
                ?>
                    <tr class="service-row" data-category="<?php echo htmlspecialchars($catVal); ?>" data-search="<?php echo htmlspecialchars($searchData); ?>" id="row-service-<?php echo $servicio['id']; ?>">
                        <td style="text-align: center;">
                            <?php if (!$isReadOnly): ?>
                                <div class="order-input-box" title="Cambia el número para reordenar en la web">
                                    <span style="font-size: 11px; color: #94A3B8; font-weight: 800;">#</span>
                                    <input type="number" 
                                           class="order-num-input" 
                                           value="<?php echo $sOrden; ?>" 
                                           min="1" 
                                           step="1"
                                           onchange="actualizarOrdenServicio(<?php echo $servicio['id']; ?>, this.value, this)"
                                           onkeydown="if(event.key==='Enter') this.blur()">
                                    <span class="order-save-indicator" id="saved-<?php echo $servicio['id']; ?>">✓</span>
                                </div>
                            <?php else: ?>
                                <span style="font-weight: 800; color: #64748B; font-size: 13px;">#<?php echo $sOrden; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($servicio['nombre']); ?>
                                </strong>
                                <?php 
                                $singleB = !empty($servicio['barbero_asignado_nombre']) ? $servicio['barbero_asignado_nombre'] : (stripos($servicio['nombre'], 'mateo') !== false ? 'Mateo Álvaro' : null);
                                
                                if (!empty($assignedList)) {
                                    if (count($assignedList) === 1) {
                                        echo '<span style="display: inline-block; background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; margin-left: 6px;">⭐ Solo ' . htmlspecialchars($assignedList[0]) . '</span>';
                                    } elseif ($totalActiveBarbers > 0 && count($assignedList) < $totalActiveBarbers) {
                                        echo '<span style="display: inline-block; background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-left: 6px;" title="' . htmlspecialchars(implode(', ', $assignedList)) . '">✂️ ' . count($assignedList) . ' barberos habilitados</span>';
                                    } else {
                                        echo '<span style="display: inline-block; background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-left: 6px;">👥 Todos los barberos</span>';
                                    }
                                } elseif ($singleB) {
                                    echo '<span style="display: inline-block; background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; margin-left: 6px;">⭐ Solo ' . htmlspecialchars($singleB) . '</span>';
                                } else {
                                    echo '<span style="display: inline-block; background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-left: 6px;">👥 Todos los barberos</span>';
                                }
                                ?>
                                <?php if ($servicio['descripcion']): ?>
                                    <div class="service-description" style="margin-top: 2px;">
                                        <?php echo htmlspecialchars($servicio['descripcion']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($servicio['que_incluye'])): ?>
                                    <div style="margin-top: 6px; font-size: 11.5px; background: #F8FAFC; border: 1px solid #E2E8F0; border-left: 3px solid #10B981; border-radius: 4px; padding: 5px 9px; color: #334155; line-height: 1.4; max-width: 380px;">
                                        <div style="font-weight: 700; color: #059669; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                            ✓ Incluye:
                                        </div>
                                        <div style="white-space: pre-line; color: #475569;"><?php echo htmlspecialchars($servicio['que_incluye']); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 4px;">
                                        <a href="servicios_editar.php?id=<?php echo $servicio['id']; ?>" style="font-size: 11px; color: #6B7280; text-decoration: none; font-style: italic;">
                                            + Especificar qué incluye
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge"
                                style="background: rgba(51, 51, 51, 0.08); color: #111827; border: 1px solid rgba(51, 51, 51, 0.2); font-weight: 700;">
                                <?php echo htmlspecialchars($catVal); ?>
                            </span>
                        </td>
                        <td>$
                            <?php echo number_format($servicio['precio'], 2); ?>
                        </td>
                        <td>
                            <?php echo $servicio['duracion_minutos']; ?> min
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $servicio['activo'] ? 'active' : 'inactive'; ?>">
                                <?php echo $servicio['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                            </span>
                        </td>
                        <?php if (!$isReadOnly): ?>
                            <td>
                                <div class="actions-cell">
                                    <a href="servicios_editar.php?id=<?php echo $servicio['id']; ?>" class="btn-action">EDITAR</a>
                                    <button
                                        onclick="confirmarEliminar(<?php echo $servicio['id']; ?>, '<?php echo htmlspecialchars($servicio['nombre']); ?>')"
                                        class="btn-action btn-delete">ELIMINAR</button>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay servicios registrados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    const allServicesData = <?php echo json_encode(array_map(function($s) use ($barbersByService) {
        $assigned = $barbersByService[$s['id']] ?? [];
        return [
            'id' => $s['id'],
            'nombre' => $s['nombre'],
            'categoria' => trim($s['categoria'] ?? 'General'),
            'precio' => number_format($s['precio'], 2),
            'duracion' => intval($s['duracion_minutos']),
            'barberos' => !empty($assigned) ? implode(', ', $assigned) : 'Todos los barberos',
            'activo' => intval($s['activo'])
        ];
    }, $servicios)); ?>;

    const serviceSearchInput = document.getElementById('predictiveServiceSearch');
    const serviceSearchDropdown = document.getElementById('predictiveServiceDropdown');
    const serviceRows = document.querySelectorAll('.service-row');

    if (serviceSearchInput && serviceSearchDropdown) {
        serviceSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();

            // 1. Instant table filtering
            serviceRows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!q || text.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });

            // 2. Predictive Suggestions
            if (q.length === 0) {
                serviceSearchDropdown.style.display = 'none';
                serviceSearchDropdown.innerHTML = '';
                return;
            }

            const matches = allServicesData.filter(s => 
                s.nombre.toLowerCase().includes(q) ||
                s.categoria.toLowerCase().includes(q) ||
                s.precio.includes(q) ||
                s.barberos.toLowerCase().includes(q)
            ).slice(0, 6);

            if (matches.length > 0) {
                let html = '';
                matches.forEach(m => {
                    html += `
                        <a href="servicios_editar.php?id=${m.id}" class="predictive-item">
                            <div class="service-icon-box">✂️</div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        ${m.nombre}
                                    </div>
                                    <span style="color: #059669; font-weight: 900; font-size: 0.88rem;">$${m.precio}</span>
                                </div>
                                <div style="font-size: 0.76rem; color: #6B7280; margin-top: 2px;">
                                    🏷️ <strong>${m.categoria}</strong> • ⏱️ ${m.duracion} min • 💈 ${m.barberos}
                                </div>
                            </div>
                        </a>
                    `;
                });
                serviceSearchDropdown.innerHTML = html;
                serviceSearchDropdown.style.display = 'block';
            } else {
                serviceSearchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron servicios coincidentes</div>';
                serviceSearchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!serviceSearchInput.contains(e.target) && !serviceSearchDropdown.contains(e.target)) {
                serviceSearchDropdown.style.display = 'none';
            }
        });
    }

    function filterByCategory(cat) {
        document.querySelectorAll('.cat-filter-pill').forEach(p => p.classList.remove('active'));
        if (cat === 'all') {
            document.getElementById('pill-all')?.classList.add('active');
            document.querySelectorAll('.service-row').forEach(row => row.classList.remove('hidden-by-cat'));
        } else {
            event.target.closest('.cat-filter-pill')?.classList.add('active');
            document.querySelectorAll('.service-row').forEach(row => {
                if (row.getAttribute('data-category') === cat) {
                    row.classList.remove('hidden-by-cat');
                } else {
                    row.classList.add('hidden-by-cat');
                }
            });
        }
    }

    function actualizarOrdenServicio(id, nuevoOrden, inputElement) {
        const ordenVal = parseInt(nuevoOrden, 10);
        if (isNaN(ordenVal) || ordenVal < 1) return;

        const formData = new FormData();
        formData.append('action', 'update_order');
        formData.append('id', id);
        formData.append('orden', ordenVal);

        fetch('api/servicios_action.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const ind = document.getElementById('saved-' + id);
                if (ind) {
                    ind.classList.add('show');
                    setTimeout(() => ind.classList.remove('show'), 1500);
                }
            } else {
                alert('Error al guardar orden: ' + (data.message || 'Ocurrió un error'));
            }
        })
        .catch(err => {
            console.error(err);
        });
    }

    function confirmarEliminar(id, nombre) {
        if (confirm('¿Estás seguro de eliminar el servicio "' + nombre + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/servicios_action.php';

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
