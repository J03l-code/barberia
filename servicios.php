<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Si es barbero, redirigir al dashboard (no tiene acceso a gestión de servicios)
// Si es barbero, tiene acceso pero solo lectura
$isReadOnly = isBarbero();

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
            ORDER BY COALESCE(cs.orden, 999) ASC, s.categoria ASC, s.activo DESC, s.nombre ASC";
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
            Administra los servicios, precios, duraciones y categorías de tu barbería.
        </p>
    </div>
    <?php if (!$isReadOnly): ?>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <a href="categorias_servicios.php" class="btn-secondary-custom" style="padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; border: 1px solid #D1D5DB; border-radius: 6px; background: #FFFFFF; color: #374151; transition: all 0.2s ease;">
                <span>🏷️</span> GESTIONAR CATEGORÍAS
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
                <?php echo htmlspecialchars($cl['nombre']); ?> (<?php echo intval($cl['total_servicios'] ?? 0); ?>)
            </button>
        <?php endforeach; ?>
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

    .status-inactive {
        background: rgba(142, 142, 147, 0.12);
        color: #8E8E93;
        border: 1px solid rgba(142, 142, 147, 0.3);
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
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
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
                ?>
                    <tr class="service-row" data-category="<?php echo htmlspecialchars($catVal); ?>">
                        <td>
                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($servicio['nombre']); ?>
                                </strong>
                                <?php 
                                $assignedList = $barbersByService[$servicio['id']] ?? [];
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
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay servicios registrados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function filterByCategory(cat) {
        document.querySelectorAll('.cat-filter-pill').forEach(p => p.classList.remove('active'));
        if (cat === 'all') {
            document.getElementById('pill-all')?.classList.add('active');
            document.querySelectorAll('.service-row').forEach(row => row.classList.remove('hidden-by-cat'));
        } else {
            event.target.classList.add('active');
            document.querySelectorAll('.service-row').forEach(row => {
                if (row.getAttribute('data-category') === cat) {
                    row.classList.remove('hidden-by-cat');
                } else {
                    row.classList.add('hidden-by-cat');
                }
            });
        }
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
