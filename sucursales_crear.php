<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageBranches());
$currentUser = getCurrentUser();

$sucursal = null;
$isEdit = false;

// Si hay ID, cargar sucursal para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM sucursales WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $sucursal = $result[0];
        } else {
            header('Location: sucursales.php?error=Sucursal no encontrada');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: sucursales.php?error=Error al cargar sucursal');
        exit;
    }
}

$pageTitle = $isEdit ? 'Editar Sucursal' : 'Nueva Sucursal';
include 'includes/header.php';
?>

<style>
    .form-modal {
        max-width: 520px;
        margin: 40px auto;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .form-title {
        font-size: 22px;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 32px;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-input {
        width: 100%;
        padding: 14px 16px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 15px;
        font-family: var(--font-body);
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
        background: #FFFFFF;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 32px;
        justify-content: flex-end;
    }

    .btn-cancel {
        padding: 12px 28px;
        background: transparent;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-cancel:hover {
        background: rgba(0, 0, 0, 0.05);
        border-color: rgba(0, 0, 0, 0.3);
    }

    .btn-confirm {
        padding: 12px 28px;
        background: linear-gradient(135deg, #333333, #555555);
        border: none;
        border-radius: 6px;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-confirm:hover {
        background: linear-gradient(135deg, #444444, #333333);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(51, 51, 51, 0.3);
    }
</style>

<div class="form-modal">
    <h1 class="form-title">
        <?php echo $isEdit ? htmlspecialchars($sucursal['nombre']) : 'Nueva Sucursal'; ?>
    </h1>

    <?php if (isset($_GET['error'])): ?>
        <div style="background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #c0392b; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 600; font-size: 13px;">
            ⚠ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="api/sucursales_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $sucursal['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre de la Sucursal</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($sucursal['nombre']) : ''; ?>"
                placeholder="KORTZEN Gran Vía" required>
        </div>

        <div class="form-group">
            <label class="form-label">Dirección</label>
            <input type="text" name="direccion" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($sucursal['direccion']) : ''; ?>"
                placeholder="Calle Gran Vía, 42 - Madrid" required>
        </div>

        <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" name="telefono" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($sucursal['telefono']) : ''; ?>"
                placeholder="+593 98 842 2770" required>
        </div>

        <div class="form-group">
            <label class="form-label">Estado de la Sucursal</label>
            <select name="estado" class="form-input" style="background:#fff;">
                <option value="activo" <?php echo ($isEdit && ($sucursal['estado'] ?? '') === 'activo') ? 'selected' : ''; ?>>🟢 ACTIVA (Disponible para Reservas)</option>
                <option value="proximamente" <?php echo ($isEdit && ($sucursal['estado'] ?? '') === 'proximamente') ? 'selected' : ''; ?>>⏳ PRÓXIMAMENTE (Visible pero no clickeable)</option>
                <option value="inactivo" <?php echo ($isEdit && ($sucursal['estado'] ?? '') === 'inactivo') ? 'selected' : ''; ?>>🔴 INACTIVA (Oculta)</option>
            </select>
        </div>

        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
                <label class="form-label">Apertura</label>
                <input type="time" name="horario_apertura" class="form-input"
                    value="<?php echo $isEdit ? htmlspecialchars($sucursal['horario_apertura'] ?? '10:00') : '10:00'; ?>">
            </div>
            <div>
                <label class="form-label">Cierre</label>
                <input type="time" name="horario_cierre" class="form-input"
                    value="<?php echo $isEdit ? htmlspecialchars($sucursal['horario_cierre'] ?? '20:00') : '20:00'; ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">URL de Google Maps (Iframe / Enlace)</label>
            <input type="text" name="mapa_url" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($sucursal['mapa_url'] ?? '') : ''; ?>"
                placeholder="https://maps.google.com/...">
        </div>

        <div class="form-actions">
            <a href="sucursales.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
