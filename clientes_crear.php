<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

if (isBarbero()) {
    header('Location: barber-dashboard.php?error=' . urlencode('No tienes permiso para crear clientes.'));
    exit;
}

$cliente = null;
$isEdit = false;

// Si hay ID, cargar cliente para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM clientes WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $cliente = $result[0];
        } else {
            header('Location: clientes.php?error=Cliente no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: clientes.php?error=Error al cargar cliente');
        exit;
    }
}

$pageTitle = $isEdit ? 'Editar Cliente' : 'Nuevo Cliente';
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
    .form-textarea {
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

    .form-textarea {
        min-height: 80px;
        resize: vertical;
    }

    .form-input:focus,
    .form-textarea:focus {
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
        <?php echo $isEdit ? htmlspecialchars($cliente['nombre']) : 'Nuevo Cliente'; ?>
    </h1>

    <form method="POST" action="api/clientes_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre Completo</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($cliente['nombre']) : ''; ?>"
                placeholder="Juan Pérez García" required>
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($cliente['email']) : ''; ?>" placeholder="juan@email.com">
        </div>

        <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" name="telefono" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($cliente['telefono']) : ''; ?>"
                placeholder="+34 612 345 678">
        </div>

        <div class="form-group">
            <label class="form-label">Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" class="form-input"
                value="<?php echo $isEdit && $cliente['fecha_nacimiento'] ? $cliente['fecha_nacimiento'] : ''; ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-textarea"
                placeholder="Preferencias del cliente..."><?php echo $isEdit ? htmlspecialchars($cliente['notas']) : ''; ?></textarea>
        </div>

        <div class="form-actions">
            <a href="clientes.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
