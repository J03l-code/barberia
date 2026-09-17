<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageUsers());
$currentUser = getCurrentUser();

$usuario = null;
$isEdit = false;

// Si hay ID, cargar usuario para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM usuarios WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $usuario = $result[0];
        } else {
            header('Location: usuarios.php?error=Usuario no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: usuarios.php?error=Error al cargar usuario');
        exit;
    }
}

// Obtener sucursales
try {
    $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
} catch (PDOException $e) {
    $sucursales = [];
}

// Obtener sucursales asignadas al usuario (para multi-sucursal admin_local)
$assignedBranchIds = [];
if ($isEdit && $usuario) {
    try {
        $assignedRows = query("SELECT sucursal_id FROM usuarios_sucursales WHERE usuario_id = ?", [$usuario['id']]);
        $assignedBranchIds = array_map('intval', array_column($assignedRows, 'sucursal_id'));
    } catch (Exception $e) {}
    if (empty($assignedBranchIds) && !empty($usuario['sucursal_id'])) {
        $assignedBranchIds = [intval($usuario['sucursal_id'])];
    }
}

$pageTitle = $isEdit ? 'Editar Usuario' : 'Nuevo Usuario';
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
        text-align: left;
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
        display: inline-block;
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
        <?php echo $isEdit ? htmlspecialchars($usuario['nombre']) : 'Nuevo Usuario'; ?>
    </h1>

    <form method="POST" action="api/usuarios_action.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre Completo</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($usuario['nombre']) : ''; ?>" placeholder="Carlos Méndez"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($usuario['email']) : ''; ?>"
                placeholder="barbero@kortzen.com" required>
        </div>

        <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" name="telefono" class="form-input"
                value="<?php echo $isEdit && isset($usuario['telefono']) ? htmlspecialchars($usuario['telefono']) : ''; ?>"
                placeholder="+34 612 345 678">
        </div>

        <div class="form-group">
            <label class="form-label">Biografía</label>
            <textarea name="biografia" class="form-input" rows="4" placeholder="Breve descripción del barbero..."><?php echo $isEdit ? htmlspecialchars(!empty($usuario['biografia']) ? $usuario['biografia'] : ($usuario['bio'] ?? '')) : ''; ?></textarea>
        </div>


        <div class="form-group">
            <label class="form-label">Especialidades</label>
            <input type="text" name="especialidades" class="form-input"
                value="<?php echo $isEdit && isset($usuario['especialidades']) ? htmlspecialchars($usuario['especialidades']) : ''; ?>"
                placeholder="Corte Clásico, Barba, Fade...">
            <small style="color: var(--text-muted); font-size: 0.8em;">Separadas por comas</small>
        </div>

        <div class="form-group">
            <label class="form-label">Foto de Perfil</label>
            <?php if ($isEdit && !empty($usuario['foto_url'])): ?>
                <div style="margin-bottom: 12px;">
                    <img src="<?php echo htmlspecialchars($usuario['foto_url']); ?>" alt="Foto actual" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;">
                </div>
            <?php endif; ?>
            <input type="file" name="foto_perfil" class="form-input" accept="image/*">
            <input type="hidden" name="foto_url" value="<?php echo $isEdit && isset($usuario['foto_url']) ? htmlspecialchars($usuario['foto_url']) : ''; ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-input" 
                placeholder="<?php echo $isEdit ? 'Dejar en blanco para mantener la actual' : '••••••••'; ?>" 
                <?php echo !$isEdit ? 'required' : ''; ?>>
            <?php if ($isEdit): ?>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 5px; display: block;">
                    * Solo escribe aquí si deseas cambiar la contraseña del usuario.
                </small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Rol</label>
            <select name="rol" id="rolSelect" class="form-select" required onchange="toggleSucursalSelector()">
                <option value="barbero" <?php echo ($isEdit && $usuario['rol'] == 'barbero') ? 'selected' : ''; ?>>Barbero</option>
                <option value="admin_local" <?php echo ($isEdit && $usuario['rol'] == 'admin_local') ? 'selected' : ''; ?>>Admin Locales (Administrador de Local)</option>
                <option value="admin" <?php echo ($isEdit && $usuario['rol'] == 'admin') ? 'selected' : ''; ?>>Admin Técnico (Acceso Total)</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Comisión Diario (%)</label>
            <input type="number" name="comision_porcentaje" class="form-input" 
                   value="<?php echo $isEdit && isset($usuario['comision_porcentaje']) ? $usuario['comision_porcentaje'] : '50.00'; ?>" 
                   min="0" max="100" step="0.01" placeholder="Ej. 50">
            <small style="color: var(--text-muted); font-size: 0.8em;">Lun - Vie</small>
        </div>

        <div class="form-group">
            <label class="form-label">Comisión Fin de Semana (%)</label>
            <input type="number" name="comision_fin_semana" class="form-input" 
                   value="<?php echo $isEdit && isset($usuario['comision_fin_semana']) ? $usuario['comision_fin_semana'] : '50.00'; ?>" 
                   min="0" max="100" step="0.01" placeholder="Ej. 60">
            <small style="color: var(--text-muted); font-size: 0.8em;">Sáb - Dom (y Festivos)</small>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--color-gold, #C0A062); font-weight: 700;">Comisión Venta de Productos (%)</label>
            <input type="number" name="comision_productos" class="form-input" 
                   value="<?php echo $isEdit && isset($usuario['comision_productos']) ? $usuario['comision_productos'] : '10.00'; ?>" 
                   min="0" max="100" step="0.01" placeholder="Ej. 10.00">
            <small style="color: var(--text-muted); font-size: 0.8em;">Porcentaje exclusivo asignado por venta de productos</small>
        </div>

        <!-- SECCIÓN HORARIO DE ALMUERZO FIJO (SOLO ADMINISTRACIÓN) -->
        <div style="grid-column: span 2; background: #FAFAFA; border: 1px solid #E0E0E0; border-radius: 12px; padding: 20px; margin: 12px 0;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <i class="fas fa-clock" style="color: #111111; font-size: 0.95rem;"></i>
                <label style="font-weight: 900; font-size: 0.88rem; color: #111111; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                    Horario de Almuerzo Fijo (Exclusivo Administrador)
                </label>
            </div>
            <p style="font-size: 0.82rem; color: #666666; margin: 0 0 16px 0; line-height: 1.4;">
                Configure el horario de descanso diario del barbero. Las reservas de clientes colisionantes con este rango quedarán bloqueadas de forma fija e inamovible.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
                <div>
                    <label style="display: block; font-weight: 700; font-size: 0.8rem; color: #333333; margin-bottom: 6px; text-transform: uppercase;">
                        Hora Inicio Almuerzo
                    </label>
                    <input type="time" name="almuerzo_inicio" class="form-input" 
                           value="<?php echo $isEdit && !empty($usuario['almuerzo_inicio']) ? substr($usuario['almuerzo_inicio'], 0, 5) : '13:00'; ?>"
                           style="width: 100%; height: 42px; padding: 8px 12px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; background: #FFFFFF;">
                </div>
                <div>
                    <label style="display: block; font-weight: 700; font-size: 0.8rem; color: #333333; margin-bottom: 6px; text-transform: uppercase;">
                        Hora Fin Almuerzo
                    </label>
                    <input type="time" name="almuerzo_fin" class="form-input" 
                           value="<?php echo $isEdit && !empty($usuario['almuerzo_fin']) ? substr($usuario['almuerzo_fin'], 0, 5) : '14:00'; ?>"
                           style="width: 100%; height: 42px; padding: 8px 12px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; background: #FFFFFF;">
                </div>
                <div>
                    <label style="display: block; font-weight: 700; font-size: 0.8rem; color: #333333; margin-bottom: 6px; text-transform: uppercase;">
                        Estado Almuerzo
                    </label>
                    <select name="almuerzo_activo" class="form-select" style="width: 100%; height: 42px; padding: 8px 12px; border: 1px solid #CCCCCC; border-radius: 8px; font-size: 0.88rem; box-sizing: border-box; background: #FFFFFF; font-weight: 600;">
                        <option value="1" <?php echo (!$isEdit || ($usuario['almuerzo_activo'] ?? 1) == 1) ? 'selected' : ''; ?>>
                            Activo (Bloqueado)
                        </option>
                        <option value="0" <?php echo ($isEdit && ($usuario['almuerzo_activo'] ?? 1) == 0) ? 'selected' : ''; ?>>
                            Desactivado
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SELECTOR DE SUCURSAL PARA BARBERO (INDIVIDUAL) -->
        <div class="form-group" id="sucursalSingleContainer">
            <label class="form-label">Sucursal Asignada</label>
            <select name="sucursal_id" class="form-select">
                <option value="">Todas las sucursales</option>
                <?php foreach ($sucursales as $suc): ?>
                    <option value="<?php echo $suc['id']; ?>" <?php echo ($isEdit && $usuario['sucursal_id'] == $suc['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($suc['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- SELECTOR MULTI-SUCURSAL PARA ADMIN DE LOCAL -->
        <div class="form-group" id="sucursalesMultiContainer" style="display: none; background: #F9F9F9; border: 1.5px solid #E5E5E5; border-radius: 10px; padding: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label class="form-label" style="margin: 0; color: #111111; font-weight: 800;">
                    🏢 Sucursales que Administra
                </label>
                <div style="display: flex; gap: 6px;">
                    <button type="button" onclick="seleccionarTodasSucursales(true)" style="background: #EAEAEA; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">Todas</button>
                    <button type="button" onclick="seleccionarTodasSucursales(false)" style="background: #EAEAEA; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">Ninguna</button>
                </div>
            </div>
            <p style="font-size: 12px; color: #666666; margin: 0 0 14px 0; line-height: 1.4;">
                Marque las sucursales sobre las que este administrador tendrá control (overview, citas, inventario y usuarios). Puede seleccionar una, dos o más sucursales, o modificarlas en cualquier momento.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($sucursales as $suc): ?>
                    <?php $isChecked = in_array(intval($suc['id']), $assignedBranchIds); ?>
                    <label style="display: flex; align-items: center; gap: 10px; background: #FFFFFF; border: 1px solid #E0E0E0; padding: 10px 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 14px; font-weight: 600; color: #111111;">
                        <input type="checkbox" name="sucursales_ids[]" value="<?php echo $suc['id']; ?>" class="sucursal-checkbox" <?php echo $isChecked ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #111111; cursor: pointer;">
                        <span>📍 <?php echo htmlspecialchars($suc['nombre']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- INFO ADMIN TECNICO -->
        <div class="form-group" id="sucursalAdminInfo" style="display: none; background: #111111; color: #FFFFFF; border-radius: 10px; padding: 16px;">
            <div style="font-weight: 800; font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                👑 Acceso Global Ilimitado
            </div>
            <p style="font-size: 12px; color: #CCCCCC; margin: 0; line-height: 1.4;">
                El Administrador Técnico tiene acceso automático y total a todas las sucursales, configuraciones y módulos del sistema.
            </p>
        </div>

        <div class="form-actions">
            <a href="usuarios.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<script>
function toggleSucursalSelector() {
    const rol = document.getElementById('rolSelect').value;
    const singleCont = document.getElementById('sucursalSingleContainer');
    const multiCont = document.getElementById('sucursalesMultiContainer');
    const adminInfo = document.getElementById('sucursalAdminInfo');

    if (rol === 'admin_local') {
        singleCont.style.display = 'none';
        multiCont.style.display = 'block';
        adminInfo.style.display = 'none';
    } else if (rol === 'admin') {
        singleCont.style.display = 'none';
        multiCont.style.display = 'none';
        adminInfo.style.display = 'block';
    } else {
        // barbero
        singleCont.style.display = 'block';
        multiCont.style.display = 'none';
        adminInfo.style.display = 'none';
    }
}

function seleccionarTodasSucursales(marcar) {
    const checkboxes = document.querySelectorAll('.sucursal-checkbox');
    checkboxes.forEach(cb => cb.checked = marcar);
}

document.addEventListener('DOMContentLoaded', function() {
    toggleSucursalSelector();
});
</script>

<?php include 'includes/footer.php'; ?>
