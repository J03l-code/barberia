<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageReviews());

$currentUser = getCurrentUser();
$pageTitle = 'Nueva Reseña';
$isEdit = false;
$resena = null;

if (isset($_GET['id'])) {
    $pageTitle = 'Editar Reseña';
    $isEdit = true;
    $id = intval($_GET['id']);
    $res = query("SELECT * FROM resenas WHERE id = ?", [$id]);
    if ($res) {
        $resena = $res[0];
    } else {
        header('Location: resenas.php?error=Reseña no encontrada');
        exit;
    }
}

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

    .form-input,
    .form-textarea,
    .form-select {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        font-family: var(--font-body);
    }

    .btn-confirm {
        padding: 12px 28px;
        background: linear-gradient(135deg, #333, #555);
        border: none;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 1px;
    }

    .btn-cancel {
        padding: 12px 28px;
        border: 1px solid #ccc;
        border-radius: 6px;
        color: #333;
        text-decoration: none;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 1px;
        margin-right: 10px;
    }
</style>

<div class="form-modal">
    <h1 class="form-title">
        <?php echo $pageTitle; ?>
    </h1>

    <form method="POST" action="api/reviews_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?php echo $resena['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre del Cliente</label>
            <input type="text" name="cliente_nombre" class="form-input" required
                value="<?php echo $isEdit ? htmlspecialchars($resena['cliente_nombre']) : ''; ?>"
                placeholder="Ej. Juan Pérez">
        </div>

        <div class="form-group">
            <label class="form-label">Comentario</label>
            <textarea name="comentario" class="form-textarea" rows="4" required
                placeholder="Escribe la reseña aquí..."><?php echo $isEdit ? htmlspecialchars($resena['comentario']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Calificación (Estrellas)</label>
            <select name="calificacion" class="form-select">
                <option value="5" <?php echo ($isEdit && $resena['calificacion'] == 5) ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ (5)
                </option>
                <option value="4" <?php echo ($isEdit && $resena['calificacion'] == 4) ? 'selected' : ''; ?>>⭐⭐⭐⭐ (4)
                </option>
                <option value="3" <?php echo ($isEdit && $resena['calificacion'] == 3) ? 'selected' : ''; ?>>⭐⭐⭐ (3)
                </option>
                <option value="2" <?php echo ($isEdit && $resena['calificacion'] == 2) ? 'selected' : ''; ?>>⭐⭐ (2)
                </option>
                <option value="1" <?php echo ($isEdit && $resena['calificacion'] == 1) ? 'selected' : ''; ?>>⭐ (1)
                </option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Fecha</label>
            <input type="date" name="fecha" class="form-input"
                value="<?php echo $isEdit ? $resena['fecha'] : date('Y-m-d'); ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="visible" class="form-select">
                <option value="1" <?php echo (!$isEdit || $resena['visible']) ? 'selected' : ''; ?>>Visible</option>
                <option value="0" <?php echo ($isEdit && !$resena['visible']) ? 'selected' : ''; ?>>Oculto</option>
            </select>
        </div>

        <div style="text-align: right; margin-top: 30px;">
            <a href="resenas.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Guardar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
