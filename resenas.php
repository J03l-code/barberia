<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageReviews());

$currentUser = getCurrentUser();
$pageTitle = 'Moderación de Reseñas';
include 'includes/header.php';

// Filtro de estado
$filter = $_GET['filtro'] ?? 'todas';

$sql = "SELECT * FROM resenas";
$params = [];

if ($filter === 'pendientes') {
    $sql .= " WHERE visible = 0";
} elseif ($filter === 'aprobadas') {
    $sql .= " WHERE visible = 1";
}

$sql .= " ORDER BY created_at DESC, id DESC";
$resenas = query($sql, $params);

// Conteo para estadísticas
$countPendientes = query("SELECT COUNT(*) as total FROM resenas WHERE visible = 0")[0]['total'] ?? 0;
$countAprobadas = query("SELECT COUNT(*) as total FROM resenas WHERE visible = 1")[0]['total'] ?? 0;
$countTotal = query("SELECT COUNT(*) as total FROM resenas")[0]['total'] ?? 0;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 900;">Moderación de Reseñas</h1>
        <p style="color: var(--text-secondary, #666); font-size: 0.88rem; margin-top: 4px;">
            Filtra, aprueba o rechaza las opiniones dejadas por tus clientes en la aplicación y página web.
        </p>
    </div>
    <a href="resenas_crear.php" class="btn btn-primary" style="padding: 10px 18px; font-weight: 800;">
        + NUEVA RESEÑA
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="background: #E8F8F0; color: #1E7E45; border: 1px solid #C2EBCF; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="background: #FDF2F2; color: #9B1C1C; border: 1px solid #F8B4B4; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
    </div>
<?php endif; ?>

<!-- Tarjetas de Moderación Rápida -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <a href="resenas.php?filtro=pendientes" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'pendientes' ? '#FFFBEB' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'pendientes' ? '#F59E0B' : '#EAEAEA'; ?>; border-radius: 12px; padding: 16px; transition: all 0.2s;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #B45309; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-clock"></i>
                <span>PENDIENTES DE MODERACIÓN</span>
            </div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #92400E;">
                <?php echo $countPendientes; ?>
            </div>
            <div style="font-size: 0.78rem; color: #B45309; margin-top: 4px;">
                <?php echo $countPendientes > 0 ? 'Requieren aprobación' : 'No hay pendientes por revisar'; ?>
            </div>
        </div>
    </a>

    <a href="resenas.php?filtro=aprobadas" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'aprobadas' ? '#ECFDF5' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'aprobadas' ? '#10B981' : '#EAEAEA'; ?>; border-radius: 12px; padding: 16px; transition: all 0.2s;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-check-double"></i>
                <span>PUBLICADAS EN WEB</span>
            </div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #065F46;">
                <?php echo $countAprobadas; ?>
            </div>
            <div style="font-size: 0.78rem; color: #047857; margin-top: 4px;">
                Visibles para tus clientes
            </div>
        </div>
    </a>

    <a href="resenas.php?filtro=todas" style="text-decoration: none;">
        <div style="background: <?php echo $filter === 'todas' ? '#F3F4F6' : '#FFFFFF'; ?>; border: 1.5px solid <?php echo $filter === 'todas' ? '#111111' : '#EAEAEA'; ?>; border-radius: 12px; padding: 16px; transition: all 0.2s;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-list-alt"></i>
                <span>TOTAL REGISTRADAS</span>
            </div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #111827;">
                <?php echo $countTotal; ?>
            </div>
            <div style="font-size: 0.78rem; color: #4B5563; margin-top: 4px;">
                Historial completo de reseñas
            </div>
        </div>
    </a>
</div>

<!-- Filtros Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid #EAEAEA; padding-bottom: 12px;">
    <a href="resenas.php?filtro=todas" class="btn" style="padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid #DDD; background: <?php echo $filter === 'todas' ? '#111111' : '#FFFFFF'; ?>; color: <?php echo $filter === 'todas' ? '#FFFFFF' : '#111111'; ?>; text-decoration: none;">
        Todas (<?php echo $countTotal; ?>)
    </a>
    <a href="resenas.php?filtro=pendientes" class="btn" style="padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid #DDD; background: <?php echo $filter === 'pendientes' ? '#111111' : '#FFFFFF'; ?>; color: <?php echo $filter === 'pendientes' ? '#FFFFFF' : '#111111'; ?>; text-decoration: none;">
        Pendientes (<?php echo $countPendientes; ?>)
    </a>
    <a href="resenas.php?filtro=aprobadas" class="btn" style="padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 800; border: 1px solid #DDD; background: <?php echo $filter === 'aprobadas' ? '#111111' : '#FFFFFF'; ?>; color: <?php echo $filter === 'aprobadas' ? '#FFFFFF' : '#111111'; ?>; text-decoration: none;">
        Publicadas (<?php echo $countAprobadas; ?>)
    </a>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Comentario</th>
                <th>Calif.</th>
                <th>Fecha</th>
                <th>Estado Moderación</th>
                <th>Acción de Moderación</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($resenas)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        No hay reseñas registradas en este filtro.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($resenas as $r): ?>
                    <tr style="<?php echo !$r['visible'] ? 'background: #FFFDF5;' : ''; ?>">
                        <td style="font-weight: 700; color: var(--text-primary);">
                            <?php echo htmlspecialchars($r['cliente_nombre']); ?>
                        </td>
                        <td style="max-width: 320px;">
                            <div style="font-size: 0.88rem; color: #333333; line-height: 1.4;">
                                "<?php echo htmlspecialchars($r['comentario']); ?>"
                            </div>
                        </td>
                        <td>
                            <div style="color: #F59E0B; font-weight: 700; font-size: 0.95rem;">
                                <?php for ($i = 0; $i < $r['calificacion']; $i++) echo '★'; ?>
                            </div>
                        </td>
                        <td style="color: #666666; font-size: 0.82rem;">
                            <?php echo date('d/m/Y', strtotime($r['fecha'])); ?>
                        </td>
                        <td>
                            <?php if ($r['visible']): ?>
                                <span style="background: #DEF7EC; color: #03543F; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-check" style="font-size: 0.68rem;"></i> PUBLICADA
                                </span>
                            <?php else: ?>
                                <span style="background: #FEF08A; color: #713F12; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-hourglass-half" style="font-size: 0.68rem;"></i> PENDIENTE
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <?php if (!$r['visible']): ?>
                                    <!-- Botón Aprobar -->
                                    <form method="POST" action="api/reviews_action.php" style="display:inline;">
                                        <input type="hidden" name="action" value="aprobar">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" style="background: #10B981; color: #FFFFFF; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Aprobar y publicar en la web">
                                            <i class="fas fa-check"></i>
                                            <span>APROBAR</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Botón Ocultar -->
                                    <form method="POST" action="api/reviews_action.php" style="display:inline;">
                                        <input type="hidden" name="action" value="rechazar">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" style="background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; padding: 6px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" title="Ocultar de la web">
                                            <span>Ocultar</span>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="resenas_editar.php?id=<?php echo $r['id']; ?>" class="btn-icon edit" title="Editar" style="padding: 6px; color: #4B5563;">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form method="POST" action="api/reviews_action.php" style="display:inline;"
                                    onsubmit="return confirm('¿Estás seguro de eliminar esta reseña permanentemente?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn-icon delete" title="Eliminar" style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 6px;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
