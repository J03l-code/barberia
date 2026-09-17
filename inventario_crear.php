<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

$producto = null;
$isEdit = false;

// Si hay ID, cargar producto para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM inventario WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $producto = $result[0];
        } else {
            header('Location: inventario.php?error=Producto no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: inventario.php?error=Error al cargar producto');
        exit;
    }
}

// Obtener sucursales
try {
    if (isAdminTecnico()) {
        $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
    } else {
        $sucursales = getUsuarioSucursales($currentUser['id']);
    }
} catch (PDOException $e) {
    $sucursales = [];
}

$pageTitle = $isEdit ? 'Editar Producto' : 'Nuevo Producto';
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
    .form-select {
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

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
        background: #FFFFFF;
    }

    .form-select {
        cursor: pointer;
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
        <?php echo $isEdit ? htmlspecialchars($producto['producto']) : 'Nuevo Producto'; ?>
    </h1>

    <form method="POST" action="api/inventario_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre del Producto</label>
            <input type="text" name="producto" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($producto['producto']) : ''; ?>"
                placeholder="Cuchillas Desechables" required>
        </div>

        <div class="form-group">
            <label class="form-label">Cantidad</label>
            <input type="number" name="cantidad" class="form-input"
                value="<?php echo $isEdit ? $producto['cantidad'] : ''; ?>" placeholder="45 unidades" min="0" required>
        </div>

        <div class="form-group">
            <label class="form-label">Precio Unitario</label>
            <input type="number" name="precio" class="form-input"
                value="<?php echo $isEdit ? $producto['precio'] : ''; ?>" placeholder="15.00" min="0" step="0.01"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Stock Mínimo (Alerta)</label>
            <input type="number" name="stock_minimo" class="form-input"
                value="<?php echo $isEdit ? $producto['stock_minimo'] : '5'; ?>" placeholder="5" min="0" required>
        </div>

        <div class="form-group">
            <label class="form-label">Sucursal</label>
            <select name="sucursal_id" class="form-select" required>
                <option value="">Seleccionar sucursal</option>
                <?php foreach ($sucursales as $suc): ?>
                    <option value="<?php echo $suc['id']; ?>" <?php echo ($isEdit && $producto['sucursal_id'] == $suc['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($suc['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <a href="inventario.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
