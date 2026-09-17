<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isAdminTecnico() && !canManageServices()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

try {
    $pdo = getConnection();
    asegurarTablaCategorias($pdo);

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $orden = intval($_POST['orden'] ?? 0);
            $activo = isset($_POST['activo']) ? intval($_POST['activo']) : 1;
            $icono = trim($_POST['icono'] ?? 'scissors');

            if (empty($nombre)) {
                throw new Exception('El nombre de la categoría es obligatorio.');
            }

            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM categorias_servicios WHERE LOWER(nombre) = LOWER(?)");
            $stmt->execute([$nombre]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una categoría con ese nombre.');
            }

            // Auto assign order if 0
            if ($orden === 0) {
                $maxOrden = $pdo->query("SELECT COALESCE(MAX(orden), 0) FROM categorias_servicios")->fetchColumn();
                $orden = $maxOrden + 1;
            }

            $stmt = $pdo->prepare("INSERT INTO categorias_servicios (nombre, descripcion, icono, orden, activo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $icono, $orden, $activo]);
            $newId = $pdo->lastInsertId();

            if (function_exists('registrarLog')) {
                registrarLog('CREAR_CATEGORIA', 'categorias_servicios', $newId, "Nueva categoría creada: '$nombre'");
            }

            // If traditional form submit, redirect back
            if (!empty($_POST['redirect'])) {
                header('Location: ../categorias_servicios.php?success=' . urlencode("Categoría '$nombre' creada exitosamente."));
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => "Categoría '$nombre' creada exitosamente.",
                'data' => [
                    'id' => $newId,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'orden' => $orden,
                    'activo' => $activo
                ]
            ]);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $orden = intval($_POST['orden'] ?? 0);
            $activo = isset($_POST['activo']) ? intval($_POST['activo']) : 1;
            $icono = trim($_POST['icono'] ?? 'scissors');

            if ($id <= 0) {
                throw new Exception('ID de categoría no válido.');
            }
            if (empty($nombre)) {
                throw new Exception('El nombre de la categoría es obligatorio.');
            }

            // Get current category
            $stmt = $pdo->prepare("SELECT nombre FROM categorias_servicios WHERE id = ?");
            $stmt->execute([$id]);
            $oldCat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldCat) {
                throw new Exception('Categoría no encontrada.');
            }

            // Check if name is taken by another id
            $stmt = $pdo->prepare("SELECT id FROM categorias_servicios WHERE LOWER(nombre) = LOWER(?) AND id != ?");
            $stmt->execute([$nombre, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe otra categoría con ese nombre.');
            }

            $oldNombre = $oldCat['nombre'];

            // Update category
            $stmt = $pdo->prepare("UPDATE categorias_servicios SET nombre = ?, descripcion = ?, icono = ?, orden = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $icono, $orden, $activo, $id]);

            // If name changed, update all services with the old name
            if ($oldNombre !== $nombre) {
                $stmt = $pdo->prepare("UPDATE servicios SET categoria = ? WHERE categoria = ?");
                $stmt->execute([$nombre, $oldNombre]);
            }

            if (function_exists('registrarLog')) {
                registrarLog('EDITAR_CATEGORIA', 'categorias_servicios', $id, "Categoría editada de '$oldNombre' a '$nombre'");
            }

            if (!empty($_POST['redirect'])) {
                header('Location: ../categorias_servicios.php?success=' . urlencode("Categoría '$nombre' actualizada exitosamente."));
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => "Categoría '$nombre' actualizada exitosamente."
            ]);
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $reassignTo = trim($_POST['reassign_to'] ?? 'General');

            if ($id <= 0) {
                throw new Exception('ID de categoría no válido.');
            }

            $stmt = $pdo->prepare("SELECT nombre FROM categorias_servicios WHERE id = ?");
            $stmt->execute([$id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cat) {
                throw new Exception('Categoría no encontrada.');
            }

            $catNombre = $cat['nombre'];

            // Check if services exist with this category
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM servicios WHERE categoria = ?");
            $countStmt->execute([$catNombre]);
            $totalServicios = $countStmt->fetchColumn();

            if ($totalServicios > 0) {
                // Reassign services
                $updateStmt = $pdo->prepare("UPDATE servicios SET categoria = ? WHERE categoria = ?");
                $updateStmt->execute([$reassignTo, $catNombre]);
            }

            $delStmt = $pdo->prepare("DELETE FROM categorias_servicios WHERE id = ?");
            $delStmt->execute([$id]);

            if (function_exists('registrarLog')) {
                registrarLog('ELIMINAR_CATEGORIA', 'categorias_servicios', $id, "Categoría '$catNombre' eliminada ($totalServicios servicios reasignados a '$reassignTo')");
            }

            if (!empty($_POST['redirect']) || !empty($_GET['redirect'])) {
                header('Location: ../categorias_servicios.php?success=' . urlencode("Categoría '$catNombre' eliminada exitosamente."));
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => "Categoría '$catNombre' eliminada exitosamente."
            ]);
            break;

        case 'toggle_status':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');

            $stmt = $pdo->prepare("UPDATE categorias_servicios SET activo = IF(activo = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
            break;

        case 'list':
        default:
            $categorias = getCategoriasServicios($pdo, false);
            echo json_encode(['success' => true, 'data' => $categorias]);
            break;
    }

} catch (Exception $e) {
    if (!empty($_POST['redirect']) || !empty($_GET['redirect'])) {
        header('Location: ../categorias_servicios.php?error=' . urlencode($e->getMessage()));
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
