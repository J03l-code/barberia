<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

if (isBarbero()) {
    header('Location: barber-dashboard.php');
    exit;
}

// Búsqueda
$search = $_GET['search'] ?? '';

// Obtener clientes
try {
    if ($search) {
        $clientes = query("SELECT * FROM clientes 
                          WHERE nombre LIKE ? OR email LIKE ? OR telefono LIKE ? 
                          ORDER BY nombre ASC",
            ["%$search%", "%$search%", "%$search%"]
        );
    } else {
        $clientes = query("SELECT * FROM clientes ORDER BY fecha_creacion DESC");
    }
} catch (PDOException $e) {
    error_log("Error al obtener clientes: " . $e->getMessage());
    $clientes = [];
}

$pageTitle = 'Clientes';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Gestión de Clientes</h1>
    <div style="display: flex; gap: 12px;">
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="search-input" placeholder="Buscar cliente..."
                value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-secondary">Buscar</button>
            <?php if ($search): ?>
                <a href="clientes.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
        <button onclick="window.location.href='clientes_crear.php'" class="btn btn-primary">+ AÑADIR CLIENTE</button>
    </div>
</div>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .search-input {
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 14px;
        min-width: 250px;
    }

    .search-input:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: transparent;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-secondary:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--text-primary);
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

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>NOMBRE</th>
                <th>EMAIL</th>
                <th>TELÉFONO</th>
                <th>PUNTOS KORTZEN</th>
                <th>FECHA REGISTRO</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($clientes) > 0): ?>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <a href="cliente_detalle.php?id=<?php echo $cliente['id']; ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 700;">
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo $cliente['email'] ? htmlspecialchars($cliente['email']) : '-'; ?>
                        </td>
                        <td>
                            <?php if ($cliente['telefono']): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php echo htmlspecialchars($cliente['telefono']); ?>
                                    <?php
                                    $wa_phone = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                                    $wa_msg = urlencode("Hola " . explode(' ', $cliente['nombre'])[0] . ", te escribimos de Kortzen.");
                                    ?>
                                    <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank"
                                        title="Enviar WhatsApp"
                                        style="color: #25D366; text-decoration: none; display: flex; align-items: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="currentColor">
                                            <path
                                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                        </svg>
                                    </a>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--primary-gold, #C0A062);">
                                🏆 <?php echo number_format(intval($cliente['puntos'] ?? 0)); ?> pts
                            </strong>
                        </td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($cliente['fecha_creacion'])); ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="cliente_detalle.php?id=<?php echo $cliente['id']; ?>" class="btn-action" style="background: #111; color: #FFF; border-color: #111;">VER DETALLES</a>
                                <a href="clientes_editar.php?id=<?php echo $cliente['id']; ?>" class="btn-action">EDITAR</a>
                                <button
                                    onclick="confirmarEliminar(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre']); ?>')"
                                    class="btn-action btn-delete">ELIMINAR</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <?php echo $search ? 'No se encontraron clientes' : 'No hay clientes registrados'; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
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
