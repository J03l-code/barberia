<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageReviews());

$currentUser = getCurrentUser();
$pageTitle = 'Moderación de Reseñas';
include 'includes/header.php';

// Filtro de estado
$filter = $_GET['filtro'] ?? 'todas';

$whereClause = "";
$params = [];

if ($filter === 'pendientes') {
    $whereClause = "WHERE visible = 0";
} elseif ($filter === 'aprobadas') {
    $whereClause = "WHERE visible = 1";
}

$resenas = [];
try {
    $resenas = query("SELECT * FROM resenas $whereClause ORDER BY COALESCE(fecha, id) DESC, id DESC", $params);
} catch (Exception $e) {
    try {
        $resenas = query("SELECT * FROM resenas $whereClause ORDER BY id DESC", $params);
    } catch (Exception $e2) {
        $resenas = [];
    }
}

// Conteo para estadísticas
$countPendientes = 0;
$countAprobadas = 0;
$countTotal = 0;
try {
    $rPen = query("SELECT COUNT(*) as total FROM resenas WHERE visible = 0");
    $countPendientes = intval($rPen[0]['total'] ?? 0);
} catch (Exception $e) {}

try {
    $rApr = query("SELECT COUNT(*) as total FROM resenas WHERE visible = 1");
    $countAprobadas = intval($rApr[0]['total'] ?? 0);
} catch (Exception $e) {}

try {
    $rTot = query("SELECT COUNT(*) as total FROM resenas");
    $countTotal = intval($rTot[0]['total'] ?? 0);
} catch (Exception $e) {}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 900; color: #111827;">Moderación de Reseñas</h1>
        <p style="color: #6B7280; font-size: 0.88rem; margin-top: 4px; margin-bottom: 0;">
            Filtra, aprueba o modera las opiniones dejadas por tus clientes en la aplicación y página web.
        </p>
    </div>
    <button type="button" onclick="abrirModalResena()" class="btn btn-primary" style="padding: 10px 18px; font-weight: 800; background: #111827; color: #FFFFFF; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
        <i class="fas fa-plus"></i>
        <span>NUEVA RESEÑA</span>
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="background: #E8F8F0; color: #1E7E45; border: 1px solid #C2EBCF; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="background: #FDF2F2; color: #9B1C1C; border: 1px solid #F8B4B4; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
    </div>
<?php endif; ?>

<!-- Tarjetas de Moderación Rápida -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <a href="resenas.php?filtro=pendientes" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'pendientes' ? '#FFFBEB' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'pendientes' ? '#F59E0B' : '#E5E7EB'; ?>; border-radius: 12px; padding: 18px; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.75rem; font-weight: 800; color: #B45309; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-clock"></i>
                <span>PENDIENTES DE MODERACIÓN</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 900; color: #92400E;">
                <?php echo $countPendientes; ?>
            </div>
            <div style="font-size: 0.8rem; color: #B45309; margin-top: 4px;">
                <?php echo $countPendientes > 0 ? 'Requieren aprobación para publicarse' : 'No hay reseñas pendientes'; ?>
            </div>
        </div>
    </a>

    <a href="resenas.php?filtro=aprobadas" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'aprobadas' ? '#ECFDF5' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'aprobadas' ? '#10B981' : '#E5E7EB'; ?>; border-radius: 12px; padding: 18px; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.75rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-check-double"></i>
                <span>PUBLICADAS EN WEB</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 900; color: #065F46;">
                <?php echo $countAprobadas; ?>
            </div>
            <div style="font-size: 0.8rem; color: #047857; margin-top: 4px;">
                Visibles para clientes en la web
            </div>
        </div>
    </a>

    <a href="resenas.php?filtro=todas" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'todas' ? '#F3F4F6' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'todas' ? '#111827' : '#E5E7EB'; ?>; border-radius: 12px; padding: 18px; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.75rem; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-list-alt"></i>
                <span>TOTAL REGISTRADAS</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 900; color: #111827;">
                <?php echo $countTotal; ?>
            </div>
            <div style="font-size: 0.8rem; color: #4B5563; margin-top: 4px;">
                Historial completo de testimonios
            </div>
        </div>
    </a>
</div>

<!-- Filtros Tabs & Buscador -->
<div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; border-bottom: 1px solid #E5E7EB; padding-bottom: 12px;">
    <div style="display: flex; gap: 8px;">
        <a href="resenas.php?filtro=todas" class="btn" style="padding: 7px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid #DDD; background: <?php echo $filter === 'todas' ? '#111827' : '#FFFFFF'; ?>; color: <?php echo $filter === 'todas' ? '#FFFFFF' : '#111827'; ?>; text-decoration: none;">
            Todas (<?php echo $countTotal; ?>)
        </a>
        <a href="resenas.php?filtro=pendientes" class="btn" style="padding: 7px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid <?php echo $filter === 'pendientes' ? '#F59E0B' : '#DDD'; ?>; background: <?php echo $filter === 'pendientes' ? '#F59E0B' : '#FFFFFF'; ?>; color: <?php echo $filter === 'pendientes' ? '#FFFFFF' : '#B45309'; ?>; text-decoration: none;">
            Pendientes (<?php echo $countPendientes; ?>)
        </a>
        <a href="resenas.php?filtro=aprobadas" class="btn" style="padding: 7px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid <?php echo $filter === 'aprobadas' ? '#10B981' : '#DDD'; ?>; background: <?php echo $filter === 'aprobadas' ? '#10B981' : '#FFFFFF'; ?>; color: <?php echo $filter === 'aprobadas' ? '#FFFFFF' : '#047857'; ?>; text-decoration: none;">
            Publicadas (<?php echo $countAprobadas; ?>)
        </a>
    </div>
    
    <div style="position: relative; min-width: 260px;">
        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; font-size: 0.85rem;"></i>
        <input type="text" id="filtroTextoResenas" placeholder="Buscar por cliente o contenido..." oninput="filtrarTablaResenas()" style="width: 100%; padding: 8px 12px 8px 34px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.84rem; outline: none; box-sizing: border-box;">
    </div>
</div>

<div class="table-container" style="background: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Cliente</th>
                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Comentario</th>
                <th style="padding: 12px 16px; text-align: center; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Calif.</th>
                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Fecha</th>
                <th style="padding: 12px 16px; text-align: center; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Estado</th>
                <th style="padding: 12px 16px; text-align: right; font-size: 0.75rem; font-weight: 800; color: #4B5563; text-transform: uppercase; letter-spacing: 0.5px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="resenasTableBody">
            <?php if (empty($resenas)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6B7280; font-weight: 600;">
                        <i class="fas fa-comment-slash" style="font-size: 2rem; color: #D1D5DB; display: block; margin-bottom: 10px;"></i>
                        No hay reseñas registradas en este filtro.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($resenas as $r): 
                    $rId = intval($r['id'] ?? 0);
                    $rNombre = !empty($r['cliente_nombre']) ? $r['cliente_nombre'] : 'Cliente';
                    $rComentario = !empty($r['comentario']) ? $r['comentario'] : '';
                    $rCalif = max(1, min(5, intval($r['calificacion'] ?? 5)));
                    $rFecha = !empty($r['fecha']) ? date('d/m/Y', strtotime($r['fecha'])) : date('d/m/Y');
                    $rFechaInput = !empty($r['fecha']) ? date('Y-m-d', strtotime($r['fecha'])) : date('Y-m-d');
                    $esVisible = intval($r['visible'] ?? 0) === 1;
                ?>
                    <tr class="resena-row" data-search="<?php echo htmlspecialchars(strtolower($rNombre . ' ' . $rComentario)); ?>" style="border-bottom: 1px solid #F3F4F6; <?php echo !$esVisible ? 'background: #FFFDF5;' : ''; ?>">
                        <td style="padding: 14px 16px; font-weight: 800; color: #111827;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 34px; height: 34px; border-radius: 50%; background: #EEF2F6; color: #1E293B; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <?php echo strtoupper(mb_substr($rNombre, 0, 1, 'UTF-8')); ?>
                                </div>
                                <span><?php echo htmlspecialchars($rNombre); ?></span>
                            </div>
                        </td>
                        <td style="padding: 14px 16px; max-width: 380px;">
                            <div style="font-size: 0.88rem; color: #374151; line-height: 1.45; font-style: italic;">
                                "<?php echo htmlspecialchars($rComentario); ?>"
                            </div>
                        </td>
                        <td style="padding: 14px 16px; text-align: center;">
                            <div style="color: #F59E0B; font-weight: 800; font-size: 1rem; letter-spacing: 1px; white-space: nowrap;">
                                <?php for ($i = 0; $i < $rCalif; $i++) echo '★'; ?>
                                <span style="color: #6B7280; font-size: 0.78rem; margin-left: 2px;">(<?php echo $rCalif; ?>)</span>
                            </div>
                        </td>
                        <td style="padding: 14px 16px; color: #6B7280; font-size: 0.82rem; white-space: nowrap;">
                            <i class="far fa-calendar-alt" style="margin-right: 4px;"></i>
                            <?php echo $rFecha; ?>
                        </td>
                        <td style="padding: 14px 16px; text-align: center;">
                            <?php if ($esVisible): ?>
                                <span style="background: #DEF7EC; color: #03543F; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-check" style="font-size: 0.65rem;"></i> PUBLICADA
                                </span>
                            <?php else: ?>
                                <span style="background: #FEF08A; color: #713F12; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-hourglass-half" style="font-size: 0.65rem;"></i> PENDIENTE
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 14px 16px; text-align: right;">
                            <div style="display: flex; gap: 6px; align-items: center; justify-content: flex-end;">
                                <?php if (!$esVisible): ?>
                                    <!-- Botón Aprobar -->
                                    <form method="POST" action="api/reviews_action.php" style="display:inline;" onsubmit="return handleResenaAction(event, this);">
                                        <input type="hidden" name="action" value="aprobar">
                                        <input type="hidden" name="id" value="<?php echo $rId; ?>">
                                        <button type="submit" style="background: #10B981; color: #FFFFFF; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Aprobar y publicar en la web">
                                            <i class="fas fa-check"></i>
                                            <span>APROBAR</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Botón Ocultar -->
                                    <form method="POST" action="api/reviews_action.php" style="display:inline;" onsubmit="return handleResenaAction(event, this);">
                                        <input type="hidden" name="action" value="rechazar">
                                        <input type="hidden" name="id" value="<?php echo $rId; ?>">
                                        <button type="submit" style="background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; padding: 6px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" title="Ocultar de la web">
                                            <i class="fas fa-eye-slash" style="margin-right: 3px;"></i>
                                            <span>Ocultar</span>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Editar Modal -->
                                <button type="button" onclick="editarResena(<?php echo htmlspecialchars(json_encode([
                                    'id' => $rId,
                                    'cliente_nombre' => $rNombre,
                                    'comentario' => $rComentario,
                                    'calificacion' => $rCalif,
                                    'fecha' => $rFechaInput,
                                    'visible' => $esVisible ? 1 : 0
                                ])); ?>)" style="background: #F3F4F6; color: #4B5563; border: 1px solid #D1D5DB; padding: 6px 9px; border-radius: 6px; font-size: 0.78rem; cursor: pointer;" title="Editar reseña">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <!-- Eliminar -->
                                <form method="POST" action="api/reviews_action.php" style="display:inline;"
                                    onsubmit="return confirm('¿Estás seguro de eliminar esta reseña permanentemente?') && handleResenaAction(event, this);">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $rId; ?>">
                                    <button type="submit" style="background: none; border: 1px solid #FEE2E2; color: #EF4444; background: #FEF2F2; cursor: pointer; padding: 6px 9px; border-radius: 6px; font-size: 0.78rem;" title="Eliminar reseña">
                                        <i class="fas fa-trash-alt"></i>
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

<!-- MODAL CREAR / EDITAR RESEÑA -->
<div id="modalResena" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #FFFFFF; width: 100%; max-width: 500px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); animation: fadeIn 0.15s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalResenaTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #111827;">Nueva Reseña</h3>
            <button type="button" onclick="cerrarModalResena()" style="background: none; border: none; font-size: 1.25rem; color: #9CA3AF; cursor: pointer; padding: 4px 8px;">✕</button>
        </div>

        <form id="formResenaModal" method="POST" action="api/reviews_action.php" onsubmit="guardarResenaModal(event)">
            <input type="hidden" id="resenaAction" name="action" value="create">
            <input type="hidden" id="resenaId" name="id" value="">

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px;">Nombre del Cliente *</label>
                <input type="text" id="resenaNombre" name="cliente_nombre" required placeholder="Ej. Juan Pérez" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px;">Comentario / Opinión *</label>
                <textarea id="resenaComentario" name="comentario" rows="3" required placeholder="Escribe aquí el comentario del cliente..." style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box; resize: vertical;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px;">Calificación (Estrellas)</label>
                    <select id="resenaCalif" name="calificacion" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box;">
                        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                        <option value="4">⭐⭐⭐⭐ (4)</option>
                        <option value="3">⭐⭐⭐ (3)</option>
                        <option value="2">⭐⭐ (2)</option>
                        <option value="1">⭐ (1)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px;">Fecha</label>
                    <input type="date" id="resenaFecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px;">Estado de Visibilidad</label>
                <select id="resenaVisible" name="visible" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box;">
                    <option value="1">Publicada (Visible en la web)</option>
                    <option value="0">Pendiente / Oculta</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="cerrarModalResena()" style="padding: 10px 18px; border: 1px solid #D1D5DB; background: #FFFFFF; color: #374151; border-radius: 8px; font-weight: 700; cursor: pointer;">Cancelar</button>
                <button type="submit" id="btnGuardarModal" style="padding: 10px 22px; border: none; background: #111827; color: #FFFFFF; border-radius: 8px; font-weight: 800; cursor: pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function filtrarTablaResenas() {
    const q = (document.getElementById('filtroTextoResenas').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.resena-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function abrirModalResena() {
    document.getElementById('modalResenaTitle').innerText = 'Nueva Reseña';
    document.getElementById('resenaAction').value = 'create';
    document.getElementById('resenaId').value = '';
    document.getElementById('resenaNombre').value = '';
    document.getElementById('resenaComentario').value = '';
    document.getElementById('resenaCalif').value = '5';
    document.getElementById('resenaFecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('resenaVisible').value = '1';
    document.getElementById('modalResena').style.display = 'flex';
}

function editarResena(r) {
    document.getElementById('modalResenaTitle').innerText = 'Editar Reseña';
    document.getElementById('resenaAction').value = 'update';
    document.getElementById('resenaId').value = r.id;
    document.getElementById('resenaNombre').value = r.cliente_nombre;
    document.getElementById('resenaComentario').value = r.comentario;
    document.getElementById('resenaCalif').value = r.calificacion;
    document.getElementById('resenaFecha').value = r.fecha;
    document.getElementById('resenaVisible').value = r.visible;
    document.getElementById('modalResena').style.display = 'flex';
}

function cerrarModalResena() {
    document.getElementById('modalResena').style.display = 'none';
}

function handleResenaAction(e, form) {
    // Si se desea recarga limpia estándar se puede dejar continuar
    return true;
}

function guardarResenaModal(e) {
    // Permite submit tradicional que redirige con success/error
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>
