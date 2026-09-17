<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

$cita = null;
$isEdit = false;

// Si hay ID, cargar cita para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM citas WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $cita = $result[0];
        } else {
            header('Location: citas.php?error=Cita no encontrada');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: citas.php?error=Error al cargar cita');
        exit;
    }
}

// Obtener datos para los selects
try {
    $clientes = query("SELECT id, nombre, telefono, email, puntos_fidelidad FROM clientes ORDER BY nombre ASC");
    $servicios = query("SELECT id, nombre, duracion_minutos FROM servicios WHERE activo = 1 ORDER BY nombre ASC");
    $barberos = query("SELECT id, nombre FROM usuarios WHERE rol = 'barbero' ORDER BY nombre ASC");
    $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
} catch (PDOException $e) {
    $clientes = [];
    $servicios = [];
    $barberos = [];
    $sucursales = [];
}

$selectedClient = null;
if ($isEdit && !empty($cita['cliente_id'])) {
    foreach ($clientes as $c) {
        if ($c['id'] == $cita['cliente_id']) {
            $selectedClient = $c;
            break;
        }
    }
}

$pageTitle = $isEdit ? 'Editar Cita' : 'Nueva Cita';
include 'includes/header.php';
?>

<style>
.form-modal {
    max-width: 620px;
    margin: 30px auto 60px auto;
    background: #FFFFFF;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
}

.form-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 22px;
    position: relative;
}

.form-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #4B5563;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 13px 16px;
    background: #FFFFFF;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14.5px;
    font-family: inherit;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-textarea {
    min-height: 80px;
    resize: vertical;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #111827;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
}

.form-select {
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    justify-content: flex-end;
}

.btn-cancel {
    padding: 12px 26px;
    background: transparent;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    color: #4B5563;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-cancel:hover {
    background: #F3F4F6;
    border-color: #D1D5DB;
    color: #111827;
}

.btn-confirm {
    padding: 12px 32px;
    background: #111827;
    border: none;
    border-radius: 8px;
    color: #FFFFFF;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(17, 24, 39, 0.25);
}

.btn-confirm:hover {
    background: #000000;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(17, 24, 39, 0.35);
}

/* SMART CLIENT SELECTOR STYLES (SETMORE PRO STYLE) */
.client-picker-wrapper {
    position: relative;
    width: 100%;
}

.client-search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.client-search-icon {
    position: absolute;
    left: 14px;
    color: #9CA3AF;
    font-size: 15px;
    pointer-events: none;
}

.client-search-input {
    width: 100%;
    padding: 13px 40px 13px 40px;
    background: #FFFFFF;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14.5px;
    color: #111827;
    box-sizing: border-box;
    transition: all 0.2s ease;
}

.client-search-input:focus {
    outline: none;
    border-color: #111827;
    box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
}

.client-search-clear {
    position: absolute;
    right: 12px;
    background: #E5E7EB;
    border: none;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    font-size: 12px;
    color: #4B5563;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.client-search-clear:hover {
    background: #D1D5DB;
    color: #111827;
}

/* DROPDOWN RESULTS */
.client-dropdown-results {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #FFFFFF;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    z-index: 1000;
    max-height: 280px;
    overflow-y: auto;
    display: none;
}

.client-dropdown-results.active {
    display: block;
}

.client-item-option {
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    cursor: pointer;
    border-bottom: 1px solid #F3F4F6;
    transition: background 0.15s ease;
}

.client-item-option:last-child {
    border-bottom: none;
}

.client-item-option:hover,
.client-item-option.highlighted {
    background: #F9FAFB;
}

.client-avatar-badge {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #111827;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
}

.client-info-col {
    flex: 1;
    min-width: 0;
}

.client-info-name {
    font-weight: 700;
    font-size: 14px;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.client-info-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: #6B7280;
    margin-top: 2px;
    flex-wrap: wrap;
}

.client-phone-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-weight: 600;
    color: #047857;
    background: #ECFDF5;
    padding: 1px 6px;
    border-radius: 4px;
}

.client-create-btn-option {
    padding: 12px 16px;
    background: #F0FDF4;
    border-bottom: 1.5px solid #DCFCE7;
    color: #059669;
    font-weight: 800;
    font-size: 13.5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease;
}

.client-create-btn-option:hover {
    background: #DCFCE7;
    color: #047857;
}

.client-empty-state {
    padding: 24px 16px;
    text-align: center;
    color: #6B7280;
}

/* SELECTED CLIENT CARD */
.selected-client-card {
    display: none;
    align-items: center;
    justify-content: space-between;
    background: #F9FAFB;
    border: 1.5px solid #10B981;
    border-radius: 10px;
    padding: 12px 16px;
    gap: 12px;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08);
}

.selected-client-card.active {
    display: flex;
}

.btn-change-client {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.btn-change-client:hover {
    background: #F3F4F6;
    border-color: #9CA3AF;
    color: #111827;
}

/* MODAL CREAR CLIENTE RÁPIDO */
.modal-quick-client-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 99999;
    justify-content: center;
    align-items: center;
    padding: 16px;
    box-sizing: border-box;
}

.modal-quick-client-overlay.active {
    display: flex;
}

.modal-quick-client-content {
    background: #FFFFFF;
    width: 100%;
    max-width: 480px;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    position: relative;
    box-sizing: border-box;
    animation: modalSlideIn 0.2s ease-out;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.toast-feedback {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #10B981;
    color: #FFFFFF;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
    z-index: 100000;
    display: none;
    align-items: center;
    gap: 8px;
    animation: toastIn 0.25s ease-out;
}

@keyframes toastIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="form-modal">
    <h1 class="form-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #111827; flex-shrink: 0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        <span><?php echo $isEdit ? 'Editar Cita' : 'Nueva Cita'; ?></span>
    </h1>
    
    <form method="POST" action="api/citas_action.php" id="formCita">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $cita['id']; ?>">
        <?php endif; ?>
        
        <!-- SELECTOR INTELIGENTE DE CLIENTE -->
        <div class="form-group">
            <div class="form-label">
                <span>Cliente *</span>
                <button type="button" onclick="abrirModalNuevoCliente()" style="background: none; border: none; color: #059669; font-weight: 800; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 0; text-transform: uppercase;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Nuevo Cliente
                </button>
            </div>

            <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo $selectedClient ? $selectedClient['id'] : ''; ?>" required>

            <div class="client-picker-wrapper" id="clientPickerWrapper">
                <!-- Estado Buscador -->
                <div class="client-search-box" id="clientSearchBox" style="<?php echo $selectedClient ? 'display: none;' : ''; ?>">
                    <span class="client-search-icon" style="display:flex; align-items:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" 
                           id="clientSearchInput" 
                           class="client-search-input" 
                           placeholder="Buscar cliente por nombre o teléfono..." 
                           autocomplete="off">
                    <button type="button" id="clientSearchClear" class="client-search-clear" onclick="limpiarBusquedaCliente()">&times;</button>
                    
                    <!-- Menú Desplegable con Resultados -->
                    <div class="client-dropdown-results" id="clientDropdownResults">
                        <div class="client-create-btn-option" id="btnDropdownCreateClient" onclick="abrirModalNuevoClienteConQuery()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>+ Registrar nuevo cliente</span>
                        </div>
                        <div id="clientResultsList"></div>
                    </div>
                </div>

                <!-- Estado Cliente Seleccionado -->
                <div class="selected-client-card <?php echo $selectedClient ? 'active' : ''; ?>" id="selectedClientCard">
                    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                        <div class="client-avatar-badge" id="selectedClientAvatar" style="background: #047857;">
                            <?php 
                            if ($selectedClient) {
                                $words = explode(' ', trim($selectedClient['nombre']));
                                echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                            } else {
                                echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                            }
                            ?>
                        </div>
                        <div style="min-width: 0;">
                            <div style="font-weight: 800; font-size: 14.5px; color: #065F46;" id="selectedClientName">
                                <?php echo $selectedClient ? htmlspecialchars($selectedClient['nombre']) : ''; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #047857; margin-top: 2px; flex-wrap: wrap;">
                                <span id="selectedClientPhone" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <?php if ($selectedClient && !empty($selectedClient['telefono'])): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                                        <?php echo htmlspecialchars($selectedClient['telefono']); ?>
                                    <?php endif; ?>
                                </span>
                                <span id="selectedClientEmail" style="color: #6B7280; display: inline-flex; align-items: center; gap: 4px;">
                                    <?php if ($selectedClient && !empty($selectedClient['email'])): ?>
                                        • <?php echo htmlspecialchars($selectedClient['email']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-change-client" onclick="deseleccionarCliente()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><polyline points="23 20 23 14 17 14"></polyline><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path></svg>
                        Cambiar
                    </button>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label"><span>Servicio *</span></label>
            <select name="servicio_id" class="form-select" required>
                <option value="">Seleccionar servicio</option>
                <?php foreach ($servicios as $servicio): ?>
                    <option value="<?php echo $servicio['id']; ?>" 
                        <?php echo ($isEdit && $cita['servicio_id'] == $servicio['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($servicio['nombre']); ?> (<?php echo $servicio['duracion_minutos']; ?> min)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Barbero</label>
                <select name="barbero_id" class="form-select" required>
                    <option value="">Seleccionar barbero</option>
                    <?php foreach ($barberos as $barbero): ?>
                        <option value="<?php echo $barbero['id']; ?>" 
                            <?php echo ($isEdit && $cita['barbero_id'] == $barbero['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barbero['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Sucursal</label>
                <select name="sucursal_id" class="form-select" required>
                    <option value="">Seleccionar sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['id']; ?>" 
                            <?php echo ($isEdit && $cita['sucursal_id'] == $sucursal['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Fecha</label>
                <input 
                    type="date" 
                    name="fecha" 
                    class="form-input"
                    value="<?php echo $isEdit ? date('Y-m-d', strtotime($cita['fecha_hora'])) : ''; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label class="form-label">Hora</label>
                <input 
                    type="time" 
                    name="hora" 
                    class="form-input"
                    value="<?php echo $isEdit ? date('H:i', strtotime($cita['fecha_hora'])) : ''; ?>"
                    required
                >
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" required>
                <option value="pendiente" <?php echo ($isEdit && $cita['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="confirmada" <?php echo ($isEdit && $cita['estado'] == 'confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                <option value="completada" <?php echo ($isEdit && $cita['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                <option value="cancelada" <?php echo ($isEdit && $cita['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea 
                name="notas" 
                class="form-textarea"
                placeholder="Notas adicionales..."
            ><?php echo $isEdit ? htmlspecialchars($cita['notas']) : ''; ?></textarea>
        </div>
        
        <div class="form-actions">
            <a href="citas.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<!-- MODAL CREAR NUEVO CLIENTE RÁPIDO (SETMORE PRO STYLE) -->
<div id="modalNuevoClienteRapido" class="modal-quick-client-overlay" onclick="if(event.target === this) cerrarModalNuevoCliente()">
    <div class="modal-quick-client-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:#111827; display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                Registrar Nuevo Cliente
            </h3>
            <button type="button" onclick="cerrarModalNuevoCliente()" style="background:#F3F4F6; border:none; width:32px; height:32px; border-radius:50%; font-size:1.2rem; cursor:pointer; color:#4B5563; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        <form id="formNuevoClienteRapido" onsubmit="guardarNuevoClienteAjax(event)">
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="margin-bottom:6px;">Nombre Completo *</label>
                <input type="text" id="nuevoClienteNombre" class="form-input" placeholder="Ej: Carlos Mendoza" required>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="margin-bottom:6px;">Teléfono / WhatsApp *</label>
                <input type="tel" id="nuevoClienteTelefono" class="form-input" placeholder="Ej: 0991234567" required>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="margin-bottom:6px;">Email (Opcional)</label>
                <input type="email" id="nuevoClienteEmail" class="form-input" placeholder="cliente@correo.com">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label" style="margin-bottom:6px;">Notas / Preferencias (Opcional)</label>
                <textarea id="nuevoClienteNotas" class="form-textarea" placeholder="Corte favorito, estilo, alergias, etc." style="min-height:65px;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="cerrarModalNuevoCliente()" class="btn-cancel" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" id="btnGuardarNuevoCliente" class="btn-confirm" style="background:#059669; padding:10px 22px;">Guardar y Seleccionar</button>
            </div>
        </form>
    </div>
</div>

<div id="toastCliente" class="toast-feedback"></div>

<script>
let allClients = <?php echo json_encode($clientes); ?>;
let selectedClient = <?php echo $selectedClient ? json_encode($selectedClient) : 'null'; ?>;

const searchInput = document.getElementById('clientSearchInput');
const searchClearBtn = document.getElementById('clientSearchClear');
const dropdownResults = document.getElementById('clientDropdownResults');
const resultsList = document.getElementById('clientResultsList');
const hiddenClienteId = document.getElementById('cliente_id');
const searchBox = document.getElementById('clientSearchBox');
const selectedClientCard = document.getElementById('selectedClientCard');
const selectedClientAvatar = document.getElementById('selectedClientAvatar');
const selectedClientName = document.getElementById('selectedClientName');
const selectedClientPhone = document.getElementById('selectedClientPhone');
const selectedClientEmail = document.getElementById('selectedClientEmail');
const btnDropdownCreateClient = document.getElementById('btnDropdownCreateClient');
const toastEl = document.getElementById('toastCliente');

function getInitials(name) {
    if (!name) return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].substring(0, 2).toUpperCase();
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function highlightMatch(text, query) {
    if (!query || !text) return escapeHtml(text);
    const escaped = escapeHtml(text);
    const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return escaped.replace(regex, '<span class="client-match-highlight">$1</span>');
}

function renderClientResults(clientsToRender, query = '') {
    resultsList.innerHTML = '';
    
    if (clientsToRender.length === 0) {
        resultsList.innerHTML = `
            <div class="client-no-results" style="padding: 24px 16px; text-align: center;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </div>
                <div style="font-weight:700; color:#374151;">No se encontraron clientes</div>
                <div style="font-size:12px; color:#6B7280; margin-top:2px;">
                    ${query ? `No hay coincidencias para "<strong>${escapeHtml(query)}</strong>"` : 'Escribe para buscar'}
                </div>
            </div>
        `;
        return;
    }

    clientsToRender.forEach(c => {
        const item = document.createElement('div');
        item.className = 'client-result-item';
        item.onclick = () => selectClient(c);

        const initials = getInitials(c.nombre);
        const nameHtml = highlightMatch(c.nombre, query);
        const phoneHtml = c.telefono ? `<span style="display:inline-flex; align-items:center; gap:3px;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg> ${highlightMatch(c.telefono, query)}</span>` : '';
        const emailHtml = c.email ? `<span style="display:inline-flex; align-items:center; gap:3px;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> ${highlightMatch(c.email, query)}</span>` : '';
        const ptsHtml = (c.puntos_fidelidad > 0) ? `<span style="background:#FEF3C7; color:#B45309; padding:2px 6px; border-radius:4px; font-weight:700; font-size:10.5px; display:inline-flex; align-items:center; gap:3px;"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> ${c.puntos_fidelidad} pts</span>` : '';

        item.innerHTML = `
            <div class="client-avatar-badge">${initials}</div>
            <div class="client-result-info">
                <div class="client-result-name">${nameHtml}</div>
                <div class="client-result-meta">
                    ${phoneHtml}
                    ${emailHtml}
                    ${ptsHtml}
                </div>
            </div>
        `;
        resultsList.appendChild(item);
    });
}

function filterClients(query) {
    const q = query.trim().toLowerCase();
    if (!q) {
        renderClientResults(allClients.slice(0, 25), '');
        if (btnDropdownCreateClient) {
            btnDropdownCreateClient.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> <span>+ Registrar nuevo cliente</span>';
        }
        return;
    }

    if (btnDropdownCreateClient) {
        btnDropdownCreateClient.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> <span>+ Crear nuevo cliente "<strong>${escapeHtml(query.trim())}</strong>"</span>`;
    }

    const filtered = allClients.filter(c => {
        const name = (c.nombre || '').toLowerCase();
        const phone = (c.telefono || '').toLowerCase().replace(/\s+/g, '');
        const email = (c.email || '').toLowerCase();
        const cleanQ = q.replace(/\s+/g, '');

        return name.includes(q) || phone.includes(cleanQ) || email.includes(q);
    });

    filtered.sort((a, b) => {
        const aName = (a.nombre || '').toLowerCase();
        const bName = (b.nombre || '').toLowerCase();
        const aPhone = (a.telefono || '').toLowerCase();
        const bPhone = (b.telefono || '').toLowerCase();

        if (aName.startsWith(q) && !bName.startsWith(q)) return -1;
        if (!aName.startsWith(q) && bName.startsWith(q)) return 1;
        if (aPhone.startsWith(q) && !bPhone.startsWith(q)) return -1;
        if (!aPhone.startsWith(q) && bPhone.startsWith(q)) return 1;
        return aName.localeCompare(bName);
    });

    renderClientResults(filtered.slice(0, 30), query);
}

function selectClient(client) {
    hiddenClienteId.value = client.id;
    selectedClient = client;

    selectedClientAvatar.innerHTML = getInitials(client.nombre);
    selectedClientName.textContent = client.nombre;
    selectedClientPhone.innerHTML = client.telefono ? `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg> ${escapeHtml(client.telefono)}` : '';
    selectedClientEmail.innerHTML = client.email ? `• ${escapeHtml(client.email)}` : '';

    dropdownResults.classList.remove('active');
    searchBox.style.display = 'none';
    selectedClientCard.classList.add('active');
    searchInput.value = '';
    searchClearBtn.style.display = 'none';
}

function deseleccionarCliente() {
    hiddenClienteId.value = '';
    selectedClient = null;
    selectedClientCard.classList.remove('active');
    searchBox.style.display = 'flex';
    searchInput.value = '';
    searchClearBtn.style.display = 'none';
    searchInput.focus();
    filterClients('');
    dropdownResults.classList.add('active');
}

function limpiarBusquedaCliente() {
    searchInput.value = '';
    searchClearBtn.style.display = 'none';
    filterClients('');
    searchInput.focus();
}

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value;
        searchClearBtn.style.display = val.length > 0 ? 'flex' : 'none';
        dropdownResults.classList.add('active');
        filterClients(val);
    });

    searchInput.addEventListener('focus', () => {
        dropdownResults.classList.add('active');
        filterClients(searchInput.value);
    });
}

document.addEventListener('click', (e) => {
    const wrapper = document.getElementById('clientPickerWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        dropdownResults.classList.remove('active');
    }
});

function abrirModalNuevoCliente(prefill = {}) {
    document.getElementById('formNuevoClienteRapido').reset();
    if (prefill.nombre) {
        document.getElementById('nuevoClienteNombre').value = prefill.nombre;
    }
    if (prefill.telefono) {
        document.getElementById('nuevoClienteTelefono').value = prefill.telefono;
    }
    const modal = document.getElementById('modalNuevoClienteRapido');
    modal.classList.add('active');
    dropdownResults.classList.remove('active');

    setTimeout(() => {
        if (prefill.telefono && !prefill.nombre) {
            document.getElementById('nuevoClienteNombre').focus();
        } else if (prefill.nombre) {
            document.getElementById('nuevoClienteTelefono').focus();
        } else {
            document.getElementById('nuevoClienteNombre').focus();
        }
    }, 100);
}

function abrirModalNuevoClienteConQuery() {
    const q = searchInput.value.trim();
    const isPhoneLike = /^[\d\s\+\-\(\)]{4,}$/.test(q);
    if (isPhoneLike) {
        abrirModalNuevoCliente({ telefono: q });
    } else {
        abrirModalNuevoCliente({ nombre: q });
    }
}

function cerrarModalNuevoCliente() {
    document.getElementById('modalNuevoClienteRapido').classList.remove('active');
}

function mostrarToast(msg) {
    if (!toastEl) return;
    toastEl.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>${escapeHtml(msg)}</span>`;
    toastEl.style.display = 'flex';
    setTimeout(() => {
        toastEl.style.display = 'none';
    }, 4000);
}

async function guardarNuevoClienteAjax(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarNuevoCliente');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Guardando...';

    const nombre = document.getElementById('nuevoClienteNombre').value.trim();
    const telefono = document.getElementById('nuevoClienteTelefono').value.trim();
    const email = document.getElementById('nuevoClienteEmail').value.trim();
    const notas = document.getElementById('nuevoClienteNotas').value.trim();

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('ajax', '1');
    formData.append('nombre', nombre);
    formData.append('telefono', telefono);
    formData.append('email', email);
    formData.append('notas', notas);

    try {
        const resp = await fetch('api/clientes_action.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await resp.json();
        if (data.success && data.cliente) {
            allClients.unshift(data.cliente);
            selectClient(data.cliente);
            cerrarModalNuevoCliente();
            mostrarToast(`Cliente "${data.cliente.nombre}" creado exitosamente.`);
        } else {
            alert(data.error || data.message || 'Error al crear el cliente.');
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión al registrar el cliente.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
