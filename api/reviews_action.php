<?php
require_once '../config.php';

$currentUser = getCurrentUser();
$userRol = $currentUser['rol'] ?? ($_SESSION['user_rol'] ?? '');

$canManage = isLoggedIn() && (
    in_array($userRol, ['admin', 'admin_local', 'administrador', 'superadmin']) || 
    isAdminTecnico() || 
    canManageReviews()
);

$isAjax = isset($_REQUEST['ajax']) || isset($_POST['ajax']) || 
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!$canManage) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para gestionar reseñas.']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getConnection();

    switch ($action) {
        case 'create':
        case 'crear':
            $nombre = trim($_POST['cliente_nombre'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');
            $calificacion = intval($_POST['calificacion'] ?? 5);
            $fecha = !empty($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
            $visible = isset($_POST['visible']) ? intval($_POST['visible']) : 1;

            if (empty($nombre) || empty($comentario)) {
                throw new Exception('El nombre y el comentario son obligatorios.');
            }
            if ($calificacion < 1) $calificacion = 1;
            if ($calificacion > 5) $calificacion = 5;

            $sql = "INSERT INTO resenas (cliente_nombre, comentario, calificacion, fecha, visible) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $comentario, $calificacion, $fecha, $visible]);
            $newId = $pdo->lastInsertId();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Reseña registrada exitosamente.',
                    'id' => $newId
                ]);
                exit;
            }

            header('Location: ../resenas.php?success=' . urlencode('Reseña agregada exitosamente'));
            exit;

        case 'update':
        case 'actualizar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['cliente_nombre'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');
            $calificacion = intval($_POST['calificacion'] ?? 5);
            $fecha = !empty($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
            $visible = isset($_POST['visible']) ? intval($_POST['visible']) : 1;

            if ($id <= 0) throw new Exception('ID de reseña inválido.');
            if (empty($nombre) || empty($comentario)) throw new Exception('El nombre y el comentario son obligatorios.');
            if ($calificacion < 1) $calificacion = 1;
            if ($calificacion > 5) $calificacion = 5;

            $sql = "UPDATE resenas SET cliente_nombre = ?, comentario = ?, calificacion = ?, fecha = ?, visible = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $comentario, $calificacion, $fecha, $visible, $id]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reseña actualizada exitosamente.']);
                exit;
            }

            header('Location: ../resenas.php?success=' . urlencode('Reseña actualizada exitosamente'));
            exit;

        case 'aprobar':
        case 'aprobar_resena':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID de reseña inválido.');

            $sql = "UPDATE resenas SET visible = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reseña aprobada y publicada en la web.']);
                exit;
            }

            header('Location: ../resenas.php?success=' . urlencode('Reseña aprobada y publicada exitosamente en el sitio web'));
            exit;

        case 'rechazar':
        case 'ocultar':
        case 'ocultar_resena':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID de reseña inválido.');

            $sql = "UPDATE resenas SET visible = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reseña ocultada de la web.']);
                exit;
            }

            header('Location: ../resenas.php?success=' . urlencode('Reseña ocultada de la web'));
            exit;

        case 'delete':
        case 'eliminar':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID de reseña inválido.');

            $sql = "DELETE FROM resenas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reseña eliminada permanentemente.']);
                exit;
            }

            header('Location: ../resenas.php?success=' . urlencode('Reseña eliminada exitosamente'));
            exit;

        case 'get':
        case 'get_resena':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');

            $stmt = $pdo->prepare("SELECT * FROM resenas WHERE id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res) throw new Exception('Reseña no encontrada.');

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'resena' => $res]);
            exit;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header('Location: ../resenas.php?error=' . urlencode($e->getMessage()));
    exit;
}
