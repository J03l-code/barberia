<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

if (isBarbero()) {
    header('Location: barber-dashboard.php');
    exit;
}

// Búsqueda inicial
$search = $_GET['search'] ?? '';

// Obtener clientes
try {
    if ($search) {
        $clientes = query("SELECT id, nombre, email, telefono, COALESCE(puntos, 0) as puntos, COALESCE(foto_perfil, '') as foto_perfil, fecha_creacion FROM clientes 
                          WHERE nombre LIKE ? OR email LIKE ? OR telefono LIKE ? 
                          ORDER BY nombre ASC",
            ["%$search%", "%$search%", "%$search%"]
        );
    } else {
        $clientes = query("SELECT id, nombre, email, telefono, COALESCE(puntos, 0) as puntos, COALESCE(foto_perfil, '') as foto_perfil, fecha_creacion FROM clientes ORDER BY fecha_creacion DESC");
    }
} catch (PDOException $e) {
    try {
        $clientes = query("SELECT id, nombre, email, telefono, COALESCE(puntos, 0) as puntos, fecha_creacion FROM clientes ORDER BY fecha_creacion DESC");
    } catch (PDOException $e2) {
        $clientes = [];
    }
}

$pageTitle = 'Clientes';
include 'includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    /* Buscador Predictivo */
    .predictive-search-container {
        position: relative;
        min-width: 320px;
        max-width: 420px;
        flex-grow: 1;
    }

    .predictive-search-input {
        width: 100%;
        padding: 10px 14px 10px 38px;
        background: #FFFFFF;
        border: 1.5px solid #E5E7EB;
        border-radius: 8px;
        color: #111827;
        font-size: 14px;
        font-weight: 600;
        outline: none;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }

    .predictive-search-input:focus {
        border-color: #111827;
        box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
    }

    .predictive-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
        font-size: 14px;
        pointer-events: none;
    }

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

    .avatar-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        background: #111827;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        border: 1.5px solid #E5E7EB;
    }

    .btn-action {
        padding: 6px 14px;
        background: transparent;
        border: 1px solid #333333;
        border-radius: 6px;
        color: #333333;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:hover {
        background: #111111;
        color: #FFFFFF;
    }

    .btn-delete {
        border-color: #EF4444;
        color: #EF4444;
    }

    .btn-delete:hover {
        background: #EF4444;
        color: #FFFFFF;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .table th {
        font-size: 11px;
        color: #6B7280;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 16px;
        background: #F9FAFB;
    }

    .table td {
        vertical-align: middle;
        padding: 12px 16px;
        border-bottom: 1px solid #F3F4F6;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 900;">Gestión de Clientes</h1>
        <p style="color: #6B7280; font-size: 0.88rem; margin-top: 4px; margin-bottom: 0;">Directorio completo de clientes, datos de contacto y fidelización</p>
    </div>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <!-- Buscador Predictivo -->
        <div class="predictive-search-container">
            <i class="fas fa-search predictive-search-icon"></i>
            <input type="text" id="predictiveClientSearch" class="predictive-search-input" placeholder="Buscar por nombre, teléfono o email..." autocomplete="off">
            <div id="predictiveClientDropdown" class="predictive-dropdown"></div>
        </div>

        <button onclick="window.location.href='clientes_crear.php'" class="btn btn-primary" style="padding: 10px 18px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-user-plus"></i>
            <span>+ AÑADIR CLIENTE</span>
        </button>
    </div>
</div>

<div class="table-container" style="background: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>CLIENTE</th>
                <th>EMAIL</th>
                <th>TELÉFONO</th>
                <th>PUNTOS KORTZEN</th>
                <th>FECHA REGISTRO</th>
                <th style="text-align: right;">ACCIONES</th>
            </tr>
        </thead>
        <tbody id="clientesTableBody">
            <?php if (count($clientes) > 0): ?>
                <?php foreach ($clientes as $cliente): 
                    $cNombre = $cliente['nombre'];
                    $cEmail = $cliente['email'] ?: '-';
                    $cTel = $cliente['telefono'] ?: '-';
                    $cFoto = $cliente['foto_perfil'] ?? '';
                    $cInitial = strtoupper(mb_substr($cNombre, 0, 1, 'UTF-8'));
                    $searchData = strtolower($cNombre . ' ' . $cEmail . ' ' . $cTel);
                ?>
                    <tr class="cliente-row" data-search="<?php echo htmlspecialchars($searchData); ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($cFoto)): ?>
                                    <img src="<?php echo htmlspecialchars($cFoto); ?>" alt="Foto" class="avatar-circle" onerror="this.onerror=null; this.outerHTML='<div class=\'avatar-circle\'><span><?php echo $cInitial; ?></span></div>';">
                                <?php else: ?>
                                    <div class="avatar-circle"><span><?php echo $cInitial; ?></span></div>
                                <?php endif; ?>
                                <div>
                                    <a href="cliente_detalle.php?id=<?php echo $cliente['id']; ?>" style="color: #111827; text-decoration: none; font-weight: 800; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($cNombre); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td style="color: #4B5563; font-size: 0.88rem;">
                            <?php echo htmlspecialchars($cEmail); ?>
                        </td>
                        <td>
                            <?php if ($cliente['telefono']): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-weight: 600; color: #374151; font-size: 0.88rem;"><?php echo htmlspecialchars($cliente['telefono']); ?></span>
                                    <?php
                                    $wa_phone = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                                    $wa_msg = urlencode("Hola " . explode(' ', $cliente['nombre'])[0] . ", te escribimos de Kortzen Barbería.");
                                    ?>
                                    <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank"
                                        title="Enviar WhatsApp"
                                        style="color: #25D366; text-decoration: none; display: flex; align-items: center; font-size: 1.1rem;">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color: #9CA3AF;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: #D97706; background: #FEF3C7; padding: 4px 10px; border-radius: 8px; font-size: 0.82rem; font-weight: 800;">
                                🏆 <?php echo number_format(intval($cliente['puntos'] ?? 0)); ?> pts
                            </strong>
                        </td>
                        <td style="color: #6B7280; font-size: 0.82rem;">
                            <i class="far fa-calendar-alt" style="margin-right: 4px;"></i>
                            <?php echo date('d/m/Y', strtotime($cliente['fecha_creacion'])); ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="actions-cell" style="justify-content: flex-end;">
                                <a href="cliente_detalle.php?id=<?php echo $cliente['id']; ?>" class="btn-action" style="background: #111827; color: #FFFFFF; border-color: #111827;">DETALLES</a>
                                <a href="clientes_editar.php?id=<?php echo $cliente['id']; ?>" class="btn-action">EDITAR</a>
                                <button
                                    onclick="confirmarEliminar(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre'])); ?>')"
                                    class="btn-action btn-delete">ELIMINAR</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="noResultsRow">
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6B7280;">
                        <i class="fas fa-users-slash" style="font-size: 2rem; color: #D1D5DB; display: block; margin-bottom: 10px;"></i>
                        No hay clientes registrados.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const allClientsData = <?php echo json_encode(array_map(function($c) {
    return [
        'id' => $c['id'],
        'nombre' => $c['nombre'],
        'email' => $c['email'] ?: '',
        'telefono' => $c['telefono'] ?: '',
        'puntos' => intval($c['puntos'] ?? 0),
        'foto' => $c['foto_perfil'] ?? ''
    ];
}, $clientes)); ?>;

const searchInput = document.getElementById('predictiveClientSearch');
const searchDropdown = document.getElementById('predictiveClientDropdown');
const rows = document.querySelectorAll('.cliente-row');

searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    
    // 1. Filtrado instantáneo de filas en la tabla
    let visibleCount = 0;
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });

    // 2. Dropdown predictivo con sugerencias visuales
    if (q.length === 0) {
        searchDropdown.style.display = 'none';
        searchDropdown.innerHTML = '';
        return;
    }

    const matches = allClientsData.filter(c => 
        c.nombre.toLowerCase().includes(q) || 
        c.telefono.toLowerCase().includes(q) || 
        c.email.toLowerCase().includes(q)
    ).slice(0, 6);

    if (matches.length > 0) {
        let html = '';
        matches.forEach(m => {
            const initial = (m.nombre || 'C').charAt(0).toUpperCase();
            const avatarHtml = m.foto 
                ? `<img src="${m.foto}" class="avatar-circle" style="width:32px;height:32px;font-size:11px;" onerror="this.onerror=null; this.outerHTML='<div class=\\'avatar-circle\\' style=\\'width:32px;height:32px;font-size:11px;\\'>${initial}</div>';">` 
                : `<div class="avatar-circle" style="width:32px;height:32px;font-size:11px;">${initial}</div>`;

            html += `
                <a href="cliente_detalle.php?id=${m.id}" class="predictive-item">
                    ${avatarHtml}
                    <div style="flex-grow: 1; min-width: 0;">
                        <div style="font-weight: 800; font-size: 0.9rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${m.nombre}</div>
                        <div style="font-size: 0.78rem; color: #6B7280;">📞 ${m.telefono || 'Sin tel'} ${m.email ? '• ' + m.email : ''}</div>
                    </div>
                    <span style="color: #D97706; font-weight: 800; font-size: 0.75rem; background: #FEF3C7; padding: 2px 6px; border-radius: 6px;">${m.puntos} pts</span>
                </a>
            `;
        });
        searchDropdown.innerHTML = html;
        searchDropdown.style.display = 'block';
    } else {
        searchDropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #9CA3AF; font-size: 0.85rem;">No se encontraron clientes coincidentes</div>';
        searchDropdown.style.display = 'block';
    }
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
        searchDropdown.style.display = 'none';
    }
});

function confirmarEliminar(id, nombre) {
    if (confirm('¿Estás seguro de eliminar el cliente "' + nombre + '"?\n\nSe eliminarán también sus citas asociadas.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/clientes_action.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
