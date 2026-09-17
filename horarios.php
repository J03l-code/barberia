<?php
require_once 'config.php';
requireLogin();

// Permisos seguros
if (!canManageSchedules() && !isAdminTecnico() && !in_array($_SESSION['user_rol'] ?? '', ['admin', 'superadmin', 'administrador', 'admin_local'])) {
    header('Location: dashboard.php?error=' . urlencode('No tienes permiso para gestionar horarios.'));
    exit;
}

$pdo = getConnection();

// Auto-migración segura de bloqueos_horas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bloqueos_horas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barbero_id INT NOT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            motivo VARCHAR(255) DEFAULT 'Bloqueo temporal',
            creado_por INT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_barbero (barbero_id),
            INDEX idx_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e_bh) {}

$currentUser = getCurrentUser();

// Obtener sucursales permitidas para este usuario
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
$barberos = [];
$sucursales = [];

try {
    if (isAdminTecnico()) {
        $barberos = query("SELECT id, nombre, email, sucursal_id, rol FROM usuarios WHERE rol IN ('barbero', 'admin_local', 'admin') ORDER BY nombre ASC");
        $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
    } else {
        $sucursales = getUsuarioSucursales($currentUser['id']);
        if (!empty($userSucursalesIds)) {
            $inList = implode(',', array_map('intval', $userSucursalesIds));
            $barberos = query("SELECT id, nombre, email, sucursal_id, rol FROM usuarios WHERE rol IN ('barbero', 'admin_local') AND (sucursal_id IN ($inList) OR id = ?) ORDER BY nombre ASC", [$currentUser['id']]);
        } else {
            $barberos = query("SELECT id, nombre, email, sucursal_id, rol FROM usuarios WHERE id = ?", [$currentUser['id']]);
        }
    }
} catch (Exception $e) {
    $barberos = [];
    $sucursales = [];
}

// Días de la semana
$diasSemana = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

// Obtener barbero seleccionado (Acepta barbero, barbero_id o id)
$barberoId = intval($_GET['barbero'] ?? $_GET['barbero_id'] ?? $_GET['id'] ?? 0);
if ($barberoId <= 0) {
    $barberoId = null;
}
$barberoSeleccionado = null;
$horarios = [];
$diasBloqueados = [];
$bloqueosHoras = [];

if ($barberoId) {
    // Obtener datos del barbero o staff
    try {
        $result = query("SELECT * FROM usuarios WHERE id = ?", [$barberoId]);
        if (!empty($result)) {
            $barberoSeleccionado = $result[0];
            
            // Obtener horarios
            $horarios = query("SELECT * FROM horarios_barberos WHERE barbero_id = ? ORDER BY dia_semana ASC", [$barberoId]);
            
            // Obtener días bloqueados (próximos 60 días)
            $diasBloqueados = query(
                "SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha >= CURDATE() ORDER BY fecha ASC",
                [$barberoId]
            );

            // Obtener BLOQUEOS POR HORA (próximos 60 días)
            $bloqueosHoras = query(
                "SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha >= CURDATE() ORDER BY fecha ASC, hora_inicio ASC",
                [$barberoId]
            );
        }
    } catch (Exception $e_queries) {
        // En caso de cualquier error en una consulta secundaria, aseguramos que la página cargue limpiamente
    }
}

$pageTitle = 'Gestión de Horarios';
include 'includes/header.php';
?>

<style>
/* Diseño Premium para Horarios */
.horarios-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 32px;
    align-items: start;
}

@media (max-width: 900px) {
    .horarios-container {
        grid-template-columns: 1fr;
    }
}

/* Lista de Barberos */
.barberos-list {
    background: #FFFFFF;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 16px;
    padding: 24px;
    position: sticky;
    top: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.barbero-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: 12px;
    cursor: pointer;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
    margin-bottom: 8px;
}

.barbero-item:hover {
    background: rgba(0, 0, 0, 0.03);
    color: var(--text-primary);
}

.barbero-item.active {
    background: linear-gradient(145deg, rgba(51, 51, 51, 0.1), rgba(51, 51, 51, 0.05));
    border-color: rgba(51, 51, 51, 0.3);
    color: #333333;
}

.barbero-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.08);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.3s ease;
}

.barbero-item.active .barbero-avatar {
    background: #333333;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(51, 51, 51, 0.3);
}

/* Panel Principal */
.horarios-panel {
    background: #FFFFFF;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.section-header {
    margin-bottom: 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    padding-bottom: 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.section-desc {
    color: var(--text-secondary);
    font-size: 14px;
}

/* Grid de Horarios */
.horario-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.horario-dia {
    display: grid;
    grid-template-columns: 140px 1fr auto;
    align-items: center;
    padding: 16px 20px;
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.horario-dia:hover {
    background: rgba(0, 0, 0, 0.04);
}

.horario-dia.activo-row {
    background: linear-gradient(to right, rgba(51, 51, 51, 0.05), transparent);
    border-left: 3px solid #333333;
}

.horario-dia.inactivo {
    opacity: 0.5;
    background: transparent;
    border-style: dashed;
}

.dia-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dia-nombre {
    font-weight: 500;
    font-size: 15px;
    color: var(--text-primary);
    text-transform: capitalize;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #444;
}

.activo-row .status-dot {
    background: #2ECC71;
    box-shadow: 0 0 8px rgba(46, 204, 113, 0.4);
}

.horario-inputs {
    display: flex;
    align-items: center;
    gap: 16px;
}

.time-group {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
}

.time-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

input[type="time"] {
    background: #FFFFFF;
    border: 1px solid rgba(0, 0, 0, 0.15);
    color: var(--text-primary);
    padding: 8px 12px;
    border-radius: 8px;
    font-family: var(--font-heading);
    font-size: 15px;
    outline: none;
    cursor: pointer;
    width: 130px;
    transition: all 0.2s ease;
}

input[type="time"]:focus {
    border-color: #333333;
    box-shadow: 0 0 0 2px rgba(51, 51, 51, 0.1);
}

input[type="time"]:disabled {
    background: #F5F5F5;
    border-color: transparent;
    color: var(--text-muted);
}

/* Switch Premium */
.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #DDDDDD;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #333333;
}

input:checked + .slider:before {
    transform: translateX(20px);
    background-color: #000;
}

input:focus + .slider {
    box-shadow: 0 0 1px #333333;
}

/* Días Bloqueados */
.bloqueo-card {
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.bloqueo-card:hover {
    border-color: rgba(231, 76, 60, 0.3);
    background: rgba(231, 76, 60, 0.05);
}

.bloqueo-date {
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    border-color: #E74C3C;
    color: #E74C3C;
    background: rgba(231, 76, 60, 0.1);
}

/* Responsivo */
@media (max-width: 768px) {
    .horario-dia {
        grid-template-columns: 1fr auto;
        gap: 16px;
    }
    
    .dia-info {
        grid-column: 1 / -1;
        margin-bottom: 8px;
    }

    .horario-inputs {
        grid-column: 1 / 2;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .switch-container {
        grid-column: 2 / 3;
        grid-row: 2 / 3;
        align-self: center;
    }
}
</style>

<div class="page-header">
    <h1 class="page-title">Gestión de Horarios</h1>
    <p class="page-subtitle">Configure la disponibilidad semanal y excepciones</p>
</div>

<div class="horarios-container">
    <!-- Lista de Barberos -->
    <div class="barberos-list">
        <h3 class="section-title" style="font-size: 16px; letter-spacing: 1px; text-transform: uppercase;">Staff</h3>
        <div style="height: 1px; background: rgba(0,0,0,0.1); margin: 16px 0;"></div>
        
        <?php if (count($barberos) > 0): ?>
            <?php foreach ($barberos as $barbero): ?>
                <a href="?barbero=<?php echo $barbero['id']; ?>" 
                   class="barbero-item <?php echo $barberoId == $barbero['id'] ? 'active' : ''; ?>">
                    <div class="barbero-avatar">
                        <?php echo strtoupper(substr($barbero['nombre'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; line-height: 1.2;"><?php echo htmlspecialchars($barbero['nombre']); ?></div>
                        <div style="font-size: 12px; opacity: 0.6; margin-top: 4px;">Configurar</div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: var(--text-secondary); opacity: 0.6;">No hay personal registrado</p>
        <?php endif; ?>
    </div>

    <!-- Panel de Horarios -->
    <div class="horarios-panel">
        <?php if ($barberoSeleccionado): ?>
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                <div>
                    <h2 class="section-title" style="margin: 0; font-size: 1.3rem; font-weight: 900;">Horario Semanal: <?php echo htmlspecialchars($barberoSeleccionado['nombre']); ?></h2>
                    <p class="section-desc" style="margin-top: 4px; color: #666666;">Define las horas de inicio y fin para cada día laboral.</p>
                </div>
                <a href="barbero_detalle.php?id=<?php echo $barberoId; ?>" class="btn" style="background: #111111; color: #FFFFFF; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem;">
                    ← Volver al Perfil del Barbero
                </a>
            </div>

            <!-- Horario Semanal -->
            <form id="formHorarios" method="POST" action="api/horarios_action.php">
                <input type="hidden" name="action" value="guardar_horarios">
                <input type="hidden" name="barbero_id" value="<?php echo $barberoId; ?>">
                <input type="hidden" name="from_profile" value="<?php echo (isset($_GET['from_profile']) || isset($_POST['from_profile'])) ? '1' : '0'; ?>">
                
                <div class="horario-grid">
                    <?php foreach ($diasSemana as $numDia => $nombreDia): 
                        $horarioDia = array_filter($horarios, fn($h) => $h['dia_semana'] == $numDia);
                        $horarioDia = !empty($horarioDia) ? array_values($horarioDia)[0] : null;
                        
                        // LOGICA ACTUALIZADA: Default 10:00 a 20:00 (Lunes a Domingo)
                        $activo = $horarioDia ? $horarioDia['activo'] : 1;
                        $horaInicio = $horarioDia ? substr($horarioDia['hora_inicio'], 0, 5) : '10:00'; 
                        $horaFin = $horarioDia ? substr($horarioDia['hora_fin'], 0, 5) : '20:00';
                    ?>
                    <div class="horario-dia <?php echo $activo ? 'activo-row' : 'inactivo'; ?>">
                        <div class="dia-info">
                            <span class="status-dot"></span>
                            <span class="dia-nombre"><?php echo $nombreDia; ?></span>
                        </div>
                        
                        <div class="horario-inputs">
                            <div class="time-group">
                                <span class="time-label">Desde</span>
                                <input type="time" name="hora_inicio[<?php echo $numDia; ?>]" 
                                       value="<?php echo $horaInicio; ?>" 
                                       <?php echo !$activo ? 'disabled' : ''; ?>>
                            </div>
                            <div class="time-group">
                                <span class="time-label">Hasta</span>
                                <input type="time" name="hora_fin[<?php echo $numDia; ?>]" 
                                       value="<?php echo $horaFin; ?>" 
                                       <?php echo !$activo ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="activo[<?php echo $numDia; ?>]" 
                                       <?php echo $activo ? 'checked' : ''; ?>
                                       onchange="toggleDia(this)">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 32px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-size: 14px;">
                        GUARDAR CAMBIOS
                    </button>
                </div>
            </form>

            <!-- Días Bloqueados -->
            <div style="margin-top: 60px;">
                <div class="section-header" style="border: none; padding-bottom: 0;">
                    <h2 class="section-title">Días Libres y Bloqueos</h2>
                    <p class="section-desc">Añade excepciones al horario (vacaciones, festivos, asuntos propios).</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <?php if (count($diasBloqueados) > 0): ?>
                        <?php foreach ($diasBloqueados as $bloqueo): ?>
                            <div class="bloqueo-card">
                                <div>
                                    <div class="bloqueo-date">
                                        <?php 
                                            $date = new DateTime($bloqueo['fecha']);
                                            $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
                                            echo ucfirst($formatter->format($date)); 
                                        ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">
                                        <?php echo htmlspecialchars($bloqueo['motivo']); ?>
                                    </div>
                                </div>
                                <form method="POST" action="api/horarios_action.php" style="margin: 0;">
                                    <input type="hidden" name="action" value="eliminar_bloqueo">
                                    <input type="hidden" name="bloqueo_id" value="<?php echo $bloqueo['id']; ?>">
                                    <input type="hidden" name="barbero_id" value="<?php echo $barberoId; ?>">
                                    <button type="submit" class="btn-icon" title="Eliminar bloqueo">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Formulario Agregar Bloqueo (Simplificado) -->
                <form method="POST" action="api/horarios_action.php" style="background: rgba(0,0,0,0.02); padding: 24px; border-radius: 12px; display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="action" value="agregar_bloqueo">
                    <input type="hidden" name="barbero_id" value="<?php echo $barberoId; ?>">
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Seleccionar Fecha</label>
                        <input type="date" name="fecha" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>" style="width: 100%;">
                    </div>
                    
                    <div style="flex: 2; min-width: 300px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Motivo</label>
                        <input type="text" name="motivo" class="form-input" 
                               placeholder="Ej: Vacaciones" style="width: 100%;">
                    </div>
                    
                    <button type="submit" class="btn btn-secondary" style="height: 48px; border: 1px solid rgba(0,0,0,0.15);">
                        + AÑADIR DÍA
                    </button>
                </form>
            </div>

            <!-- BLOQUEO POR HORAS -->
            <div style="margin-top: 60px; margin-bottom: 60px;">
                <div class="section-header" style="border: none; padding-bottom: 0;">
                    <h2 class="section-title">Bloqueo Parcial (Horas)</h2>
                    <p class="section-desc">Bloquea franjas horarias específicas (ej: permisos, médico).</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <?php if (count($bloqueosHoras ?? []) > 0): ?>
                        <?php foreach ($bloqueosHoras as $bh): ?>
                            <div class="bloqueo-card" style="border-left: 3px solid var(--primary-gold);">
                                <div>
                                    <div class="bloqueo-date">
                                        <?php 
                                            $date = new DateTime($bh['fecha']);
                                            $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE); 
                                            // Fallback date format if Intl not avail
                                            echo $date->format('d/m/Y'); 
                                        ?>
                                        <span style="font-weight: 400; font-size: 0.9em; margin-left: 8px;">
                                            <?php echo substr($bh['hora_inicio'], 0, 5) . ' - ' . substr($bh['hora_fin'], 0, 5); ?>
                                        </span>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">
                                        <?php echo htmlspecialchars($bh['motivo']); ?>
                                    </div>
                                </div>
                                <form method="POST" action="api/horarios_action.php" style="margin: 0;">
                                    <input type="hidden" name="action" value="eliminar_bloqueo_hora">
                                    <input type="hidden" name="bloqueo_id" value="<?php echo $bh['id']; ?>">
                                    <input type="hidden" name="barbero_id" value="<?php echo $barberoId; ?>">
                                    <button type="submit" class="btn-icon" title="Eliminar bloqueo">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Formulario Horas -->
                <form method="POST" action="api/horarios_action.php" style="background: rgba(0,0,0,0.02); padding: 24px; border-radius: 12px; display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="action" value="agregar_bloqueo_hora">
                    <input type="hidden" name="barbero_id" value="<?php echo $barberoId; ?>">
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Fecha</label>
                        <input type="date" name="fecha" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>" style="width: 100%;">
                    </div>

                    <div style="flex: 0.5; min-width: 100px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Inicio</label>
                        <input type="time" name="hora_inicio" class="form-input" required style="width: 100%;">
                    </div>

                    <div style="flex: 0.5; min-width: 100px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Fin</label>
                        <input type="time" name="hora_fin" class="form-input" required style="width: 100%;">
                    </div>
                    
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Motivo</label>
                        <input type="text" name="motivo" class="form-input" 
                               placeholder="Ej: Permiso médico" style="width: 100%;">
                    </div>
                    
                    <button type="submit" class="btn btn-secondary" style="height: 48px; border: 1px solid rgba(0,0,0,0.15);">
                        + RANGO
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div style="width: 80px; height: 80px; background: rgba(201, 169, 110, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <h3 style="color: var(--text-primary); font-size: 20px; margin-bottom: 8px;">Configuración de Horarios</h3>
                <p style="max-width: 300px; margin: 0 auto;">Selecciona un miembro del equipo de la lista izquierda para gestionar su disponibilidad.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDia(checkbox) {
    const row = checkbox.closest('.horario-dia');
    const inputs = row.querySelectorAll('input[type="time"]');
    
    if (checkbox.checked) {
        row.classList.remove('inactivo');
        row.classList.add('activo-row');
        inputs.forEach(input => input.disabled = false);
    } else {
        row.classList.add('inactivo');
        row.classList.remove('activo-row');
        inputs.forEach(input => input.disabled = true);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
