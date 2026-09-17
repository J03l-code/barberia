<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageServices());
$currentUser = getCurrentUser();

$pdo = getConnection();
asegurarTablaCategorias($pdo);

// Obtener todas las categorías
$categorias = getCategoriasServicios($pdo, false);

$pageTitle = 'Categorías de Servicios';
include 'includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .btn-secondary-custom {
        padding: 10px 20px;
        background: #FFFFFF;
        border: 1px solid #D1D5DB;
        border-radius: 6px;
        color: #374151;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-secondary-custom:hover {
        background: #F3F4F6;
        border-color: #9CA3AF;
        color: #111827;
    }

    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .cat-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 22px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        position: relative;
    }

    .cat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        border-color: #D1D5DB;
    }

    .cat-card.inactive {
        opacity: 0.65;
        background: #F9FAFB;
    }

    .cat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .cat-order-badge {
        background: #F3F4F6;
        color: #4B5563;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        letter-spacing: 0.5px;
    }

    .cat-title {
        font-size: 18px;
        font-weight: 700;
        color: #111827;
        margin: 0 0 6px 0;
    }

    .cat-desc {
        font-size: 13px;
        color: #6B7280;
        line-height: 1.45;
        margin: 0 0 16px 0;
        min-height: 38px;
    }

    .cat-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 14px;
        border-top: 1px solid #F3F4F6;
        margin-top: auto;
    }

    .cat-services-count {
        font-size: 12px;
        color: #4B5563;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #A7F3D0;
    }

    .status-inactive {
        background: #FEF2F2;
        color: #DC2626;
        border: 1px solid #FECACA;
    }

    .cat-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
    }

    .btn-card-action {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #D1D5DB;
        background: #FFFFFF;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .btn-card-action:hover {
        background: #F9FAFB;
        border-color: #9CA3AF;
        color: #111827;
    }

    .btn-card-delete {
        color: #DC2626;
        border-color: #FCA5A5;
    }

    .btn-card-delete:hover {
        background: #DC2626;
        color: #FFFFFF;
        border-color: #DC2626;
    }

    /* Modal styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(3px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }

    .modal-content {
        background: #FFFFFF;
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        padding: 28px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        position: relative;
    }

    .modal-title {
        font-size: 19px;
        font-weight: 800;
        color: #111827;
        margin: 0 0 18px 0;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .form-input, .form-textarea, .form-select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #D1D5DB;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: #111827;
        box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
    }

    .form-actions-row {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title" style="margin-bottom: 4px;">Categorías de Servicios</h1>
        <p style="color: var(--text-muted, #6B7280); font-size: 14px; margin: 0;">
            Configura las secciones y categorías visibles en la página web pública, reservas y app PWA.
        </p>
    </div>
    <div class="header-actions">
        <a href="servicios.php" class="btn-secondary-custom">
            <span>←</span> Volver a Servicios
        </a>
        <button onclick="openCreateModal()" class="btn btn-primary" style="padding: 10px 22px;">
            + NUEVA CATEGORÍA
        </button>
    </div>
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

<div class="category-grid">
    <?php if (empty($categorias)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: #FFFFFF; border-radius: 12px; border: 1px dashed #D1D5DB; color: #6B7280;">
            <h3>No hay categorías configuradas</h3>
            <p>Haz clic en el botón "+ NUEVA CATEGORÍA" para agregar la primera categoría.</p>
        </div>
    <?php else: ?>
        <?php foreach ($categorias as $cat): ?>
            <div class="cat-card <?php echo $cat['activo'] ? '' : 'inactive'; ?>">
                <div>
                    <div class="cat-card-header">
                        <span class="cat-order-badge">Orden: #<?php echo intval($cat['orden']); ?></span>
                        <span class="status-badge status-<?php echo $cat['activo'] ? 'active' : 'inactive'; ?>">
                            <?php echo $cat['activo'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </div>
                    <h3 class="cat-title"><?php echo htmlspecialchars($cat['nombre']); ?></h3>
                    <p class="cat-desc">
                        <?php echo !empty($cat['descripcion']) ? htmlspecialchars($cat['descripcion']) : '<span style="color:#9CA3AF; font-style:italic;">Sin descripción</span>'; ?>
                    </p>
                </div>

                <div>
                    <div class="cat-meta">
                        <span class="cat-services-count">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"></path>
                            </svg>
                            <?php echo intval($cat['total_servicios'] ?? 0); ?> servicio(s)
                        </span>
                        <a href="servicios.php" style="font-size: 11px; color: #2563EB; text-decoration: none; font-weight: 600;">Ver servicios →</a>
                    </div>

                    <div class="cat-actions">
                        <button type="button" class="btn-card-action" onclick='openEditModal(<?php echo json_encode($cat); ?>)'>
                            ✏️ Editar
                        </button>
                        <button type="button" class="btn-card-action btn-card-delete" onclick="openDeleteModal(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['nombre'])); ?>', <?php echo intval($cat['total_servicios'] ?? 0); ?>)">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- MODAL CREAR CATEGORÍA -->
<div id="modalCreate" class="modal-overlay">
    <div class="modal-content">
        <h2 class="modal-title">Nueva Categoría</h2>
        <form action="api/categorias_action.php" method="POST">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="redirect" value="1">

            <div class="form-group">
                <label class="form-label">Nombre de la Categoría *</label>
                <input type="text" name="nombre" class="form-input" placeholder="Ej: Colorimetría, Tratamientos Spa, Combos..." required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción (opcional)</label>
                <textarea name="descripcion" class="form-textarea" rows="2" placeholder="Breve descripción que se mostrará en el catálogo de la web"></textarea>
            </div>

            <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="form-label">Orden de Visualización</label>
                    <input type="number" name="orden" class="form-input" value="<?php echo count($categorias) + 1; ?>" min="1">
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="1">Activa (Visible)</option>
                        <option value="0">Inactiva (Oculta)</option>
                    </select>
                </div>
            </div>

            <div class="form-actions-row">
                <button type="button" class="btn-secondary-custom" onclick="closeModal('modalCreate')">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Guardar Categoría</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR CATEGORÍA -->
<div id="modalEdit" class="modal-overlay">
    <div class="modal-content">
        <h2 class="modal-title">Editar Categoría</h2>
        <form action="api/categorias_action.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="redirect" value="1">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label">Nombre de la Categoría *</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-input" required>
                <small style="color: #6B7280; font-size: 11px; margin-top: 4px; display: block;">
                    * Si cambias el nombre, los servicios asignados se actualizarán automáticamente.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" id="edit_descripcion" class="form-textarea" rows="2"></textarea>
            </div>

            <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="form-label">Orden de Visualización</label>
                    <input type="number" name="orden" id="edit_orden" class="form-input" min="1">
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select name="activo" id="edit_activo" class="form-select">
                        <option value="1">Activa (Visible)</option>
                        <option value="0">Inactiva (Oculta)</option>
                    </select>
                </div>
            </div>

            <div class="form-actions-row">
                <button type="button" class="btn-secondary-custom" onclick="closeModal('modalEdit')">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ELIMINAR CATEGORÍA -->
<div id="modalDelete" class="modal-overlay">
    <div class="modal-content">
        <h2 class="modal-title" style="color: #DC2626;">¿Eliminar Categoría?</h2>
        <p id="delete_message" style="font-size: 14px; color: #4B5563; line-height: 1.5; margin-bottom: 18px;"></p>

        <form action="api/categorias_action.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="redirect" value="1">
            <input type="hidden" name="id" id="delete_id">

            <div id="reassign_container" class="form-group" style="display: none; background: #FEF3C7; border: 1px solid #FDE68A; padding: 14px; border-radius: 8px;">
                <label class="form-label" style="color: #92400E;">Reasignar servicios existentes a:</label>
                <select name="reassign_to" id="delete_reassign_to" class="form-select">
                    <option value="General">General</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions-row">
                <button type="button" class="btn-secondary-custom" onclick="closeModal('modalDelete')">Cancelar</button>
                <button type="submit" class="btn btn-action btn-delete" style="padding: 10px 20px; font-weight: bold;">Sí, Eliminar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('modalCreate').style.display = 'flex';
    }

    function openEditModal(cat) {
        document.getElementById('edit_id').value = cat.id;
        document.getElementById('edit_nombre').value = cat.nombre;
        document.getElementById('edit_descripcion').value = cat.descripcion || '';
        document.getElementById('edit_orden').value = cat.orden || 1;
        document.getElementById('edit_activo').value = cat.activo ? '1' : '0';
        document.getElementById('modalEdit').style.display = 'flex';
    }

    function openDeleteModal(id, nombre, totalServicios) {
        document.getElementById('delete_id').value = id;
        const msgEl = document.getElementById('delete_message');
        const reassignBox = document.getElementById('reassign_container');

        if (totalServicios > 0) {
            msgEl.innerHTML = `Estás a punto de eliminar la categoría <strong>"${nombre}"</strong>, la cual tiene <strong>${totalServicios} servicio(s)</strong> asociados.`;
            reassignBox.style.display = 'block';
        } else {
            msgEl.innerHTML = `¿Estás seguro de que deseas eliminar la categoría <strong>"${nombre}"</strong>? Esta acción no se puede deshacer.`;
            reassignBox.style.display = 'none';
        }

        document.getElementById('modalDelete').style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Cerrar al hacer clic fuera del modal
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
