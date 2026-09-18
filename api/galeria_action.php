<?php
require_once '../config.php';
requireLogin();

$isJson = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') !== false) ||
          (!empty($_POST['ajax']) || !empty($_GET['ajax']));

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getConnection();
    
    // Asegurar tabla
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `galeria_imagenes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `titulo` VARCHAR(150) NOT NULL,
            `descripcion` TEXT DEFAULT NULL,
            `imagen_url` VARCHAR(500) NOT NULL,
            `categoria` VARCHAR(50) NOT NULL DEFAULT 'corte',
            `sucursal_id` INT UNSIGNED DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    switch ($action) {
        case 'upload':
            $titulo = sanitize($_POST['titulo'] ?? '');
            $descripcion = sanitize($_POST['descripcion'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? 'corte');

            if (empty($titulo)) {
                throw new Exception('El título de la foto es obligatorio.');
            }

            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Debes seleccionar una imagen para subir.');
            }

            $targetDir = __DIR__ . '/../assets/uploads/galeria/';
            if (!file_exists($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }

            $fileName = uniqid('gal_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['imagen']['name']));
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $allowTypes = ['jpg', 'png', 'jpeg', 'gif', 'webp'];
            if (!in_array($fileType, $allowTypes)) {
                throw new Exception('Formato no permitido. Usa JPG, PNG, GIF o WEBP.');
            }

            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $targetFilePath)) {
                throw new Exception('Error al guardar el archivo en el servidor.');
            }

            $url = '/assets/uploads/galeria/' . $fileName;
            $stmt = $pdo->prepare("INSERT INTO galeria_imagenes (titulo, descripcion, categoria, imagen_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $categoria, $url]);
            $newId = $pdo->lastInsertId();

            registrarLog('SUBIR_FOTO', 'galeria', $newId, "Nueva foto '$titulo' subida a la Galería");

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Foto subida correctamente a la galería', 'id' => $newId, 'url' => $url]);
                exit;
            }
            header('Location: ../galeria_admin.php?success=' . urlencode('Foto subida correctamente'));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de imagen inválido.');
            }

            $stmt = $pdo->prepare("SELECT titulo, imagen_url FROM galeria_imagenes WHERE id = ?");
            $stmt->execute([$id]);
            $img = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($img) {
                $relPath = ltrim($img['imagen_url'], '/');
                $fullPath = __DIR__ . '/../' . $relPath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }

                $stmtDel = $pdo->prepare("DELETE FROM galeria_imagenes WHERE id = ?");
                $stmtDel->execute([$id]);

                registrarLog('ELIMINAR_FOTO', 'galeria', $id, "Foto '{$img['titulo']}' eliminada de la Galería");
            }

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Foto eliminada correctamente']);
                exit;
            }
            header('Location: ../galeria_admin.php?success=' . urlencode('Foto eliminada correctamente'));
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Exception $e) {
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header('Location: ../galeria_admin.php?error=' . urlencode($e->getMessage()));
    exit;
}
