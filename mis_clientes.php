<?php
require_once 'config.php';
requireLogin();

// Solo para Barberos (y admins si quieren ver lo suyo, pero principalmente barberos)
$barbero_id = $_SESSION['user_id'];
$pageTitle = 'Mis Clientes';

// Procesar actualización de notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_note') {
    try {
        $cliente_id = intval($_POST['cliente_id']);
        $notas = trim($_POST['notas']);

        // Verificar que el cliente pertenece al barbero (ha tenido al menos una cita con él)
        $check = query("SELECT id FROM citas WHERE cliente_id = ? AND barbero_id = ? LIMIT 1", [$cliente_id, $barbero_id]);

        if (empty($check) && !isAdminTecnico()) {
            throw new Exception("No tienes permiso para editar este cliente.");
        }

        // Actualizar nota global (MVP)
        $sql = "UPDATE clientes SET notas = ? WHERE id = ?";
        execute($sql, [$notas, $cliente_id]);

        header('Location: mis_clientes.php?success=' . urlencode('Nota actualizada correctamente'));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener mis clientes (únicos) con estadísticas
$query = "
    SELECT 
        c.id, 
        c.nombre, 
        c.email, 
        c.telefono, 
        c.notas, 
        c.foto_perfil,
        MAX(cita.fecha_hora) as ultima_visita,
        COUNT(cita.id) as total_citas,
        SUM(cita.precio_final) as total_gastado
    FROM clientes c
    JOIN citas cita ON c.id = cita.cliente_id
    WHERE cita.barbero_id = ? 
    AND cita.estado != 'cancelada'
    GROUP BY c.id
    ORDER BY ultima_visita DESC
";

try {
    $misClientes = query($query, [$barbero_id]);
} catch (PDOException $e) {
    error_log("Error en mis_clientes.php: " . $e->getMessage());
    $misClientes = [];
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mis Clientes</h1>
        <p class="page-subtitle">Gestiona tu cartera de clientes y notas personales</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">✅
        <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<style>
    .client-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .client-card {
        background: var(--bg-sidebar);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        transition: transform 0.2s, border-color 0.2s;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .client-card:hover {
        transform: translateY(-2px);
        border-color: var(--primary-gold);
    }

    .client-header {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .client-avatar {
        width: 50px;
        height: 50px;
        background: rgba(201, 169, 110, 0.2);
        color: var(--primary-gold);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 20px;
    }

    .client-info h3 {
        margin: 0;
        font-size: 16px;
        color: var(--text-primary);
    }

    .client-stats {
        display: flex;
        gap: 15px;
        padding: 12px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .stat-mini {
        flex: 1;
        text-align: center;
    }

    .stat-mini-label {
        font-size: 10px;
        color: var(--text-muted);
        text-transform: uppercase;
        display: block;
    }

    .stat-mini-value {
        font-size: 14px;
        font-weight: bold;
        color: var(--text-secondary);
        display: block;
        margin-top: 4px;
    }

    .client-notes {
        font-size: 13px;
        color: #AAA;
        background: rgba(0, 0, 0, 0.2);
        padding: 10px;
        border-radius: 6px;
        min-height: 40px;
        white-space: pre-wrap;
    }

    .client-actions {
        display: flex;
        gap: 10px;
        margin-top: auto;
    }

    .btn-icon {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px;
        border-radius: 6px;
        font-size: 12px;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-secondary);
        background: transparent;
    }

    .btn-icon:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
    }

    .btn-whatsapp {
        color: #25D366;
        border-color: rgba(37, 211, 102, 0.2);
    }

    .btn-whatsapp:hover {
        background: rgba(37, 211, 102, 0.1);
    }
</style>

<?php if (empty($misClientes)): ?>
    <div style="text-align: center; padding: 50px; color: var(--text-muted);">
        <p>Aún no has atendido a ningún cliente.</p>
    </div>
<?php else: ?>
    <div class="client-grid">
        <?php foreach ($misClientes as $c): ?>
            <div class="client-card">
                <div class="client-header">
                    <div class="client-avatar">
                        <?php echo strtoupper(substr($c['nombre'], 0, 1)); ?>
                    </div>
                    <div class="client-info">
                        <h3>
                            <?php echo htmlspecialchars($c['nombre']); ?>
                        </h3>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Última visita:
                            <?php echo date('d M Y', strtotime($c['ultima_visita'])); ?>
                        </div>
                    </div>
                </div>

                <div class="client-stats">
                    <div class="stat-mini">
                        <span class="stat-mini-label">Citas T.</span>
                        <span class="stat-mini-value">
                            <?php echo $c['total_citas']; ?>
                        </span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-mini-label">Inversión</span>
                        <span class="stat-mini-value">$
                            <?php echo number_format($c['total_gastado'], 0); ?>
                        </span>
                    </div>
                </div>

                <div class="client-notes">
                    <?php echo $c['notas'] ? htmlspecialchars(substr($c['notas'], 0, 100)) . (strlen($c['notas']) > 100 ? '...' : '') : 'Sin notas...'; ?>
                </div>

                <div class="client-actions">
                    <!-- Información de Contacto (Solo Admins) -->
                    <?php if (!empty($c['telefono']) && (isAdminTecnico() || isAdminLocal())): 
                        $wa_phone = formatPhoneForWhatsapp($c['telefono']);
                        $wa_msg = urlencode("Hola " . explode(' ', $c['nombre'])[0] . ", habla tu barbero de Kortzen.");
                    ?>
                        <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank"
                            class="btn-icon btn-whatsapp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.991.602 1.83.896 3.003.896 3.179 0 5.764-2.585 5.763-5.766.001-3.18-2.587-5.767-5.766-5.767z" />
                            </svg>
                            WhatsApp
                        </a>
                    <?php endif; ?>

                    <!-- Botón Editar Nota -->
                    <button
                        onclick="editarNota(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(str_replace(array("\r", "\n"), ' ', $c['notas'] ?? ''), ENT_QUOTES); ?>')"
                        class="btn-icon">
                        ✏️ Notas
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL EDITAR NOTA -->
<div id="modalNotas" class="modal-overlay"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="modal-content"
        style="background: #1A1A1A; width: 500px; max-width: 90%; padding: 30px; border-radius: 12px; border: 1px solid #333;">
        <h3 id="modalNotaTitle" style="color: var(--primary-gold); margin-top: 0;">Notas de Cliente</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_note">
            <input type="hidden" name="cliente_id" id="modalNotaId">

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; color: var(--text-muted); margin-bottom: 8px;">Comentarios Privados /
                    Preferencias</label>
                <textarea name="notas" id="modalNotaText" rows="5" class="form-input"
                    style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid #333; color: #FFF; padding: 10px; border-radius: 6px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('modalNotas').style.display='none'"
                    class="btn-secondary"
                    style="padding: 10px 20px; border-radius: 6px; cursor: pointer; border: 1px solid #444; background: transparent; color: #CCC;">Cancelar</button>
                <button type="submit" class="btn-primary"
                    style="padding: 10px 20px; border-radius: 6px; cursor: pointer; border: none; background: var(--primary-gold); color: #000; font-weight: bold;">Guardar
                    Nota</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editarNota(id, nombre, notaActual) {
        document.getElementById('modalNotaId').value = id;
        document.getElementById('modalNotaTitle').textContent = 'Notas de: ' + nombre;
        document.getElementById('modalNotaText').value = notaActual;
        document.getElementById('modalNotas').style.display = 'flex';
    }

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => alert.style.display = 'none');
        // Clean URL params
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href.split('?')[0]);
        }
    }, 4000);
</script>

<?php include 'includes/footer.php'; ?>
