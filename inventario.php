<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Permisimos acceso a barberos, pero con vista restringida
$isBarber = ($currentUser['rol'] === 'barbero');

// Obtener inventario
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);
try {
    if (isAdminTecnico()) {
        $inventario = query("SELECT i.*, s.nombre as sucursal_nombre 
                            FROM inventario i 
                            LEFT JOIN sucursales s ON i.sucursal_id = s.id 
                            ORDER BY i.fecha_creacion DESC");
    } else {
        $inList = !empty($userSucursalesIds) ? implode(',', array_map('intval', $userSucursalesIds)) : '0';
        $inventario = query("SELECT i.*, s.nombre as sucursal_nombre 
                            FROM inventario i 
                            LEFT JOIN sucursales s ON i.sucursal_id = s.id 
                            WHERE i.sucursal_id IN ($inList) OR i.sucursal_id IS NULL
                            ORDER BY i.fecha_creacion DESC");
    }
} catch (PDOException $e) {
    error_log("Error al obtener inventario: " . $e->getMessage());
    $inventario = [];
}

$pageTitle = 'Inventario';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Inventario de Materiales</h1>
    <?php if (!$isBarber): ?>
        <button onclick="window.location.href='inventario_crear.php'" class="btn btn-primary">+ AÑADIR PRODUCTO</button>
    <?php endif; ?>
</div>

<!-- Buscador Predictivo de Inventario -->
<div style="position: relative; margin-bottom: 24px; max-width: 500px;">
    <div style="position: relative; display: flex; align-items: center;">
        <i class="fas fa-search search-icon" style="position: absolute; left: 14px; color: #9CA3AF; font-size: 14px; pointer-events: none;"></i>
        <input type="text" 
               id="predictiveInvSearch" 
               placeholder="Buscar producto por nombre, sucursal, stock, precio..." 
               class="search-input"
               autocomplete="off"
               style="width: 100%; padding: 11px 16px 11px 40px; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 10px; font-size: 14px; font-weight: 500; color: #111827; outline: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div id="predictiveInvDropdown" class="predictive-dropdown"></div>
</div>

<style>
    .predictive-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        max-height: 380px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .predictive-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid #F3F4F6;
        cursor: pointer;
        transition: background 0.15s ease;
        text-decoration: none;
        color: inherit;
    }

    .predictive-item:last-child {
        border-bottom: none;
    }

    .predictive-item:hover {
        background: #F9FAFB;
    }

    .prod-icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #F3F4F6;
        color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        border: 1px solid #E5E7EB;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-ok {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    .status-low {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
        border: 1px solid rgba(231, 76, 60, 0.3);
    }

    .btn-action {
        padding: 8px 18px;
        background: transparent;
        border: 1px solid #333333;
        border-radius: 4px;
        color: #333333;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:hover {
        background: #333333;
        color: #FFFFFF;
    }

    .btn-delete {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .btn-delete:hover {
        background: #E74C3C;
        color: #FFFFFF;
    }

    .btn-withdraw {
        border-color: var(--primary-gold);
        color: var(--primary-gold);
    }

    .btn-withdraw:hover {
        background: var(--primary-gold);
        color: #000;
    }

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        padding: 16px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .table td {
        padding: 16px;
        vertical-align: middle;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-size: 14px;
        color: var(--text-primary);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: #fff;
        margin: 15% auto;
        padding: 30px;
        border: 1px solid #888;
        width: 100%;
        max-width: 400px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        text-align: center;
    }

    .form-input {
        width: 100%;
        padding: 12px;
        margin: 15px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
    }
</style>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Sucursal</th>
                    <th>Stock Actual</th>
                    <th>Precio Unit.</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventario)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">No hay productos registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventario as $item): 
                        $minStock = $item['stock_minimo'] ?? 5;
                        $sucText = $item['sucursal_nombre'] ?? 'General';
                        $searchData = strtolower($item['producto'] . ' ' . $sucText . ' ' . $item['cantidad'] . ' ' . $item['precio'] . ' ' . ($item['cantidad'] <= $minStock ? 'bajo stock' : 'disponible'));
                    ?>
                        <tr class="inv-row" id="inv-row-<?php echo $item['id']; ?>" data-search="<?php echo htmlspecialchars($searchData); ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="prod-icon-box">🧴</div>
                                    <div style="font-weight: 700; color: #111827; font-size: 14px;">
                                        <?php echo htmlspecialchars($item['producto']); ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($sucText); ?></td>
                            <td style="font-weight: 700; font-size: 15px;"><?php echo $item['cantidad']; ?></td>
                            <td>$<?php echo number_format($item['precio'], 2); ?></td>
                            <td>
                                <?php if ($item['cantidad'] <= $minStock): ?>
                                    <span class="status-badge status-low">Bajo Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-ok">Disponible</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <?php if ($isBarber): ?>
                                    <button class="btn-action btn-withdraw"
                                        onclick="openWithdrawModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['producto']); ?>', <?php echo $item['cantidad']; ?>)">
                                        RETIRAR
                                    </button>
                                <?php else: ?>
                                    <a href="inventario_editar.php?id=<?php echo $item['id']; ?>" class="btn-action">EDITAR</a>

                                    <form action="api/inventario_action.php" method="POST"
                                        onsubmit="return confirm('¿Estás seguro de eliminar este producto?');"
                                        style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete">ELIMINAR</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Retirar -->
<div id="withdrawModal" class="modal">
    <div class="modal-content">
        <h2 style="margin-bottom: 10px; color: var(--text-primary);">Retirar Material</h2>
        <p id="modalProductName" style="color: var(--text-secondary); margin-bottom: 20px;">Producto</p>

        <form action="api/inventario_action.php" method="POST">
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" id="withdrawId" name="id" value="">

            <label
                style="display:block; text-align:left; font-size:12px; font-weight:bold; color:var(--text-secondary);">CANTIDAD
                A RETIRAR</label>
            <input type="number" name="cantidad" class="form-input" min="1" required placeholder="Ej. 1">

            <div style="display:flex; gap:10px; margin-top:10px;">
                <button type="button" onclick="closeWithdrawModal()" class="btn-action"
                    style="flex:1;">CANCELAR</button>
                <button type="submit" class="btn-action btn-withdraw"
                    style="flex:1; background:var(--primary-gold); color:black; border:none;">CONFIRMAR</button>
            </div>
        </form>
    </div>
</div>

<script>
    const allInvData = <?php echo json_encode(array_map(function($i) {
        $min = $i['stock_minimo'] ?? 5;
        return [
            'id' => $i['id'],
            'producto' => $i['producto'],
            'sucursal' => $i['sucursal_nombre'] ?? 'General',
            'cantidad' => intval($i['cantidad']),
            'precio' => number_format($i['precio'], 2),
            'bajo_stock' => (intval($i['cantidad']) <= intval($min))
        ];
    }, $inventario)); ?>;

    const invSearchInput = document.getElementById('predictiveInvSearch');
    const invSearchDropdown = document.getElementById('predictiveInvDropdown');
    const invRows = document.querySelectorAll('.inv-row');

    if (invSearchInput && invSearchDropdown) {
        invSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();

            // 1. Instant table filtering
            invRows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!q || text.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });

            // 2. Predictive Suggestions
            if (q.length === 0) {
                invSearchDropdown.style.display = 'none';
                invSearchDropdown.innerHTML = '';
                return;
            }

            const matches = allInvData.filter(i => 
                i.producto.toLowerCase().includes(q) ||
                i.sucursal.toLowerCase().includes(q) ||
                i.precio.includes(q)
            ).slice(0, 6);

            if (matches.length > 0) {
                let html = '';
                matches.forEach(m => {
                    const badgeHtml = m.bajo_stock 
                        ? `<span class="status-badge status-low" style="font-size:9px;padding:2px 6px;">Bajo Stock (${m.cantidad})</span>` 
                        : `<span class="status-badge status-ok" style="font-size:9px;padding:2px 6px;">Stock: ${m.cantidad}</span>`;

                    html += `
                        <div class="predictive-item" onclick="seleccionarInvPredictivo(${m.id})">
                            <div class="prod-icon-box">🧴</div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        ${m.producto}
                                    </div>
                                    <span style="color: #059669; font-weight: 900; font-size: 0.88rem;">$${m.precio}</span>
                                </div>
                                <div style="font-size: 0.76rem; color: #6B7280; margin-top: 2px; display: flex; align-items: center; justify-content: space-between;">
                                    <span>📍 ${m.sucursal}</span>
                                    ${badgeHtml}
                                </div>
                            </div>
                        </div>
                    `;
                });
                invSearchDropdown.innerHTML = html;
                invSearchDropdown.style.display = 'block';
            } else {
                invSearchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron productos coincidentes</div>';
                invSearchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!invSearchInput.contains(e.target) && !invSearchDropdown.contains(e.target)) {
                invSearchDropdown.style.display = 'none';
            }
        });
    }

    function seleccionarInvPredictivo(id) {
        invSearchDropdown.style.display = 'none';
        const row = document.getElementById('inv-row-' + id);
        if (row) {
            invRows.forEach(r => r.style.display = 'none');
            row.style.display = '';
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.background = '#FEF3C7';
            setTimeout(() => {
                row.style.background = '';
            }, 2500);
        }
    }

    function openWithdrawModal(id, name, maxStock) {
        document.getElementById('withdrawId').value = id;
        document.getElementById('modalProductName').textContent = name + " (Stock: " + maxStock + ")";

        // Update max input attribute to prevent withdrawing more than stock
        const input = document.querySelector('input[name="cantidad"]');
        input.max = maxStock;
        input.value = 1;

        document.getElementById('withdrawModal').style.display = "block";
    }

    function closeWithdrawModal() {
        document.getElementById('withdrawModal').style.display = "none";
    }

    // Close modal if clicking outside
    window.onclick = function (event) {
        if (event.target == document.getElementById('withdrawModal')) {
            closeWithdrawModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
