<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

$servicio = null;
$isEdit = false;

// Si hay ID, cargar servicio para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM servicios WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $servicio = $result[0];
        } else {
            header('Location: servicios.php?error=Servicio no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: servicios.php?error=Error al cargar servicio');
        exit;
    }
}

$pageTitle = $isEdit ? 'Editar Servicio' : 'Nuevo Servicio';
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
    .form-select,
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
        min-height: 100px;
        resize: vertical;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
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
        <?php echo $isEdit ? htmlspecialchars($servicio['nombre']) : 'Nuevo Servicio'; ?>
    </h1>

    <form method="POST" action="api/servicios_action.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $servicio['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre del Servicio</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($servicio['nombre']) : ''; ?>" placeholder="Corte Clásico"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Descripción General</label>
            <textarea name="descripcion" class="form-textarea"
                placeholder="Breve resumen del estilo o concepto del servicio"><?php echo $isEdit ? htmlspecialchars($servicio['descripcion']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                <span>¿Qué Incluye el Servicio?</span>
                <span style="font-size: 11px; color: #10B981; font-weight: 700; text-transform: none; background: #ECFDF5; padding: 2px 8px; border-radius: 4px; border: 1px solid #A7F3D0;">✓ Se muestra a los clientes</span>
            </label>
            <textarea name="que_incluye" class="form-textarea" style="min-height: 120px;"
                placeholder="Detalla lo que incluye este servicio. Ej:&#10;• Lavado capilar con shampoo premium&#10;• Asesoría de visagismo&#10;• Corte de precisión a máquina y tijera&#10;• Perfilado de contornos a navaja&#10;• Peinado final con cera mate"><?php echo $isEdit ? htmlspecialchars($servicio['que_incluye'] ?? '') : ''; ?></textarea>
            <small style="color: var(--text-muted); font-size: 0.8em; display: block; margin-top: 4px;">
                Escribe cada beneficio o paso del servicio (puedes usar viñetas • o un ítem por renglón).
            </small>
        </div>

        <div class="form-group">
            <label class="form-label">Imagen Referencial</label>
            <?php if ($isEdit && !empty($servicio['foto_url'])): ?>
                <div style="margin-bottom: 12px;">
                    <img src="<?php echo htmlspecialchars($servicio['foto_url']); ?>" alt="Vista previa" style="max-width: 150px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.1); display: block;">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">Imagen actual: <?php echo htmlspecialchars($servicio['foto_url']); ?></small>
                </div>
            <?php endif; ?>
            <input type="file" name="foto_file" class="form-input" accept="image/*">
            <small style="color: var(--text-muted); font-size: 0.8em; display: block; margin-top: 4px;">Sube una imagen (PNG, JPG, JPEG, WEBP)</small>
        </div>

        <div class="form-group">
            <label class="form-label">Precio ($)</label>
            <input type="number" name="precio" class="form-input"
                value="<?php echo $isEdit ? $servicio['precio'] : ''; ?>" placeholder="15.00" min="0" step="0.01"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Duración (minutos)</label>
            <input type="number" name="duracion_minutos" class="form-input"
                value="<?php echo $isEdit ? $servicio['duracion_minutos'] : '30'; ?>" placeholder="30" min="5" step="5"
                required>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label class="form-label" style="margin-bottom: 0;">Categoría *</label>
                <a href="categorias_servicios.php" target="_blank" style="font-size: 12px; color: #2563EB; text-decoration: none; font-weight: 600;">
                    ⚙️ Gestionar Categorías
                </a>
            </div>
            <select name="categoria" id="categoriaSelect" class="form-select" required>
                <?php
                $categoriasDB = getCategoriasServicios($pdo, true);
                $currentCat = $isEdit ? ($servicio['categoria'] ?? 'Corte') : 'Corte';
                
                // Ensure current category is in list even if not active
                $foundCurrent = false;
                foreach ($categoriasDB as $catObj) {
                    if (strcasecmp($catObj['nombre'], $currentCat) === 0) {
                        $foundCurrent = true;
                        break;
                    }
                }
                
                foreach ($categoriasDB as $catObj): 
                    $cat = $catObj['nombre'];
                ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (strcasecmp($currentCat, $cat) === 0) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>

                <?php if (!$foundCurrent && !empty($currentCat)): ?>
                    <option value="<?php echo htmlspecialchars($currentCat); ?>" selected>
                        <?php echo htmlspecialchars($currentCat); ?>
                    </option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label class="form-label" style="margin-bottom: 0;">Barberos Disponibles para este Servicio</label>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="toggleAllBarbers(true)" style="font-size: 11px; background: #F3F4F6; border: 1px solid #D1D5DB; padding: 3px 8px; border-radius: 4px; cursor: pointer; color: #374151; font-weight: 600;">
                        ✓ Todos
                    </button>
                    <button type="button" onclick="toggleAllBarbers(false)" style="font-size: 11px; background: #F3F4F6; border: 1px solid #D1D5DB; padding: 3px 8px; border-radius: 4px; cursor: pointer; color: #374151; font-weight: 600;">
                        ✗ Ninguno
                    </button>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; padding: 12px; border: 1px solid rgba(0,0,0,0.12); border-radius: 8px; background: #FAFAFA;">
                <?php
                $barberosList = query("SELECT u.id, u.nombre, u.rol, s.nombre as sucursal_nombre FROM usuarios u LEFT JOIN sucursales s ON u.sucursal_id = s.id WHERE u.activo = 1 AND (u.rol = 'barbero' OR u.rol = 'admin_local' OR u.rol = 'admin') ORDER BY u.nombre ASC");
                
                // Get assigned barbers
                $assignedBarbers = [];
                if ($isEdit) {
                    try {
                        $assignedB = query("SELECT barbero_id FROM servicios_barberos WHERE servicio_id = ?", [$servicio['id']]);
                        if (!empty($assignedB)) {
                            $assignedBarbers = array_column($assignedB, 'barbero_id');
                        } elseif (!empty($servicio['barbero_id'])) {
                            $assignedBarbers = [intval($servicio['barbero_id'])];
                        } else {
                            // If never customized, default to all barbers
                            $assignedBarbers = array_column($barberosList, 'id');
                        }
                    } catch (Throwable $e) {
                        if (!empty($servicio['barbero_id'])) {
                            $assignedBarbers = [intval($servicio['barbero_id'])];
                        } else {
                            $assignedBarbers = array_column($barberosList, 'id');
                        }
                    }
                } else {
                    $assignedBarbers = array_column($barberosList, 'id');
                }

                foreach ($barberosList as $b):
                    $isChecked = in_array($b['id'], $assignedBarbers) ? 'checked' : '';
                ?>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; font-size: 13.5px; padding: 4px 6px; border-radius: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="barberos[]" value="<?php echo $b['id']; ?>" <?php echo $isChecked; ?> class="barber-checkbox"
                                style="width: 16px; height: 16px; cursor: pointer; accent-color: #111827;">
                            <span style="font-weight: 600; color: #1F2937;">
                                <?php echo htmlspecialchars($b['nombre']); ?>
                            </span>
                        </div>
                        <span style="font-size: 11px; color: #6B7280; background: #E5E7EB; padding: 2px 6px; border-radius: 4px;">
                            <?php echo htmlspecialchars($b['sucursal_nombre'] ?? ucfirst($b['rol'])); ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: var(--text-muted); font-size: 0.8em; margin-top: 5px; display: block;">
                Marca qué barberos pueden realizar este servicio. Los clientes solo podrán reservar con los barberos seleccionados.
            </small>
        </div>

        <script>
            function toggleAllBarbers(check) {
                document.querySelectorAll('.barber-checkbox').forEach(cb => cb.checked = check);
            }
        </script>

        <div class="form-group">
            <label class="form-label">Sucursales Disponibles</label>
            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 150px; overflow-y: auto; padding: 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                <?php
                $sucursales = query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre ASC");
                // Get assigned branches
                $assignedBranches = [];
                if ($isEdit) {
                    $assigned = query("SELECT sucursal_id FROM servicios_sucursales WHERE servicio_id = ?", [$servicio['id']]);
                    if (empty($assigned)) {
                        // If no assignments found, assume available in all (backward compatibility) or none. 
                        // For safety, let's load all if it's legacy data, but usually we check table.
                        // Actually, if distinct rows exist, we use them. If table empty? 
                        // Let's rely on standard logic: empty means none.
                    } else {
                        $assignedBranches = array_column($assigned, 'sucursal_id');
                    }
                } else {
                    // New service: check all by default? Or none? Let's check all for convenience
                    $assignedBranches = array_column($sucursales, 'id');
                }

                foreach ($sucursales as $sucursal):
                    $isChecked = in_array($sucursal['id'], $assignedBranches) ? 'checked' : '';
                ?>
                    <label style="display: flex; align-items: center; cursor: pointer; font-size: 14px;">
                        <input type="checkbox" name="sucursales[]" value="<?php echo $sucursal['id']; ?>" <?php echo $isChecked; ?>
                            style="margin-right: 10px; transform: scale(1.2);">
                        <?php echo htmlspecialchars($sucursal['nombre']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: var(--text-muted); font-size: 0.8em; margin-top: 5px; display: block;">
                Selecciona las sucursales donde se ofrecerá este servicio.
            </small>
        </div>

        <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="activo" class="form-select" required>
                <option value="1" <?php echo ($isEdit && $servicio['activo']) ? 'selected' : ''; ?>>Activo</option>
                <option value="0" <?php echo ($isEdit && !$servicio['activo']) ? 'selected' : ''; ?>>Inactivo</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="display:flex; align-items:center; cursor:pointer;">
                <input type="checkbox" name="destacado" value="1" <?php echo ($isEdit && isset($servicio['destacado']) && $servicio['destacado']) ? 'checked' : ''; ?>
                    style="margin-right: 10px; width: auto; transform: scale(1.5);">
                Destacado en Inicio (Se mostrará en la portada)
            </label>
            <small style="color: var(--text-muted); margin-left: 28px;">Máximo 3 servicios aparecerán en la
                portada.</small>
        </div>

        <div class="form-actions">
            <a href="servicios.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
