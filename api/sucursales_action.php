<?php
require_once '../config.php';

// Asegurar que el usuario esté logueado y tenga permisos de Administrador Técnico
$isAllowed = isLoggedIn() && (isAdminTecnico() || canManageBranches());

if (!$isAllowed) {
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de Administrador Técnico.']);
        exit;
    }
    header('Location: ../sucursales.php?error=' . urlencode('Acceso denegado. Se requieren permisos de Administrador Técnico.'));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function cleanMapUrl($url) {
    $url = trim($url);
    if (preg_match('/src=["\']([^"\']+)["\']/', $url, $m)) {
        return $m[1];
    }
    return $url;
}

function getSucursalesColumns($pdo) {
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM sucursales");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $r['Field'];
        }
    } catch (Exception $e) {}
    return $cols;
}

function ensureSucursalesSchema($pdo, &$existingCols) {
    if (!in_array('estado', $existingCols)) {
        try { $pdo->exec("ALTER TABLE sucursales ADD COLUMN estado VARCHAR(50) DEFAULT 'activo'"); $existingCols[] = 'estado'; } catch (Exception $e) {}
    }
    if (!in_array('horario_apertura', $existingCols)) {
        try { $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_apertura TIME DEFAULT '10:00:00'"); $existingCols[] = 'horario_apertura'; } catch (Exception $e) {}
    }
    if (!in_array('horario_cierre', $existingCols)) {
        try { $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_cierre TIME DEFAULT '20:00:00'"); $existingCols[] = 'horario_cierre'; } catch (Exception $e) {}
    }
    if (!in_array('mapa_url', $existingCols)) {
        try { $pdo->exec("ALTER TABLE sucursales ADD COLUMN mapa_url TEXT NULL"); $existingCols[] = 'mapa_url'; } catch (Exception $e) {}
    }
}

try {
    $pdo = getConnection();

    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $estado = in_array($_POST['estado'] ?? '', ['activo', 'proximamente', 'inactivo']) ? $_POST['estado'] : 'activo';
            
            $horario_apertura = trim($_POST['horario_apertura'] ?? '');
            if (!empty($horario_apertura)) {
                if (strlen($horario_apertura) === 5) $horario_apertura .= ':00';
            } else {
                $horario_apertura = '10:00:00';
            }

            $horario_cierre = trim($_POST['horario_cierre'] ?? '');
            if (!empty($horario_cierre)) {
                if (strlen($horario_cierre) === 5) $horario_cierre .= ':00';
            } else {
                $horario_cierre = '20:00:00';
            }

            $mapa_url = cleanMapUrl($_POST['mapa_url'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            $existingCols = getSucursalesColumns($pdo);
            ensureSucursalesSchema($pdo, $existingCols);

            $cols = ['nombre', 'direccion', 'telefono'];
            $placeholders = ['?', '?', '?'];
            $params = [$nombre, $direccion, $telefono];

            if (in_array('estado', $existingCols)) {
                $cols[] = 'estado';
                $placeholders[] = '?';
                $params[] = $estado;
            }
            if (in_array('horario_apertura', $existingCols)) {
                $cols[] = 'horario_apertura';
                $placeholders[] = '?';
                $params[] = $horario_apertura;
            }
            if (in_array('horario_cierre', $existingCols)) {
                $cols[] = 'horario_cierre';
                $placeholders[] = '?';
                $params[] = $horario_cierre;
            }
            if (in_array('mapa_url', $existingCols)) {
                $cols[] = 'mapa_url';
                $placeholders[] = '?';
                $params[] = $mapa_url;
            }

            $sql = "INSERT INTO sucursales (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $newSucId = $pdo->lastInsertId();
            registrarLog('CREAR', 'sucursales', $newSucId, "Nueva sucursal '$nombre' creada (Dirección: '$direccion', Teléfono: '$telefono', Estado: '$estado')");

            header('Location: ../sucursales.php?success=' . urlencode('Sucursal creada exitosamente'));
            exit;

        case 'change_status':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $estado = $_POST['estado'] ?? $_GET['estado'] ?? 'activo';
            if (!in_array($estado, ['activo', 'proximamente', 'inactivo'])) $estado = 'activo';

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            $existingCols = getSucursalesColumns($pdo);
            ensureSucursalesSchema($pdo, $existingCols);

            $stmt = $pdo->prepare("UPDATE sucursales SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);

            registrarLog('EDITAR', 'sucursales', $id, "Estado de sucursal #$id cambiado a '$estado'");

            if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'estado' => $estado]);
                exit;
            }

            header('Location: ../sucursales.php?success=' . urlencode('Estado de sucursal actualizado'));
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $estado = in_array($_POST['estado'] ?? '', ['activo', 'proximamente', 'inactivo']) ? $_POST['estado'] : 'activo';
            
            $horario_apertura = trim($_POST['horario_apertura'] ?? '');
            if (!empty($horario_apertura)) {
                if (strlen($horario_apertura) === 5) $horario_apertura .= ':00';
            } else {
                $horario_apertura = '10:00:00';
            }

            $horario_cierre = trim($_POST['horario_cierre'] ?? '');
            if (!empty($horario_cierre)) {
                if (strlen($horario_cierre) === 5) $horario_cierre .= ':00';
            } else {
                $horario_cierre = '20:00:00';
            }

            $mapa_url = cleanMapUrl($_POST['mapa_url'] ?? '');

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            $existingCols = getSucursalesColumns($pdo);
            ensureSucursalesSchema($pdo, $existingCols);

            $oldS = query("SELECT * FROM sucursales WHERE id = ?", [$id]);
            $old = !empty($oldS) ? $oldS[0] : [];

            $fields = ['nombre = ?', 'direccion = ?', 'telefono = ?'];
            $params = [$nombre, $direccion, $telefono];

            if (in_array('estado', $existingCols)) {
                $fields[] = 'estado = ?';
                $params[] = $estado;
            }
            if (in_array('horario_apertura', $existingCols)) {
                $fields[] = 'horario_apertura = ?';
                $params[] = $horario_apertura;
            }
            if (in_array('horario_cierre', $existingCols)) {
                $fields[] = 'horario_cierre = ?';
                $params[] = $horario_cierre;
            }
            if (in_array('mapa_url', $existingCols)) {
                $fields[] = 'mapa_url = ?';
                $params[] = $mapa_url;
            }

            $params[] = $id;
            $sql = "UPDATE sucursales SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $cambios = [];
            if (!empty($old)) {
                if (($old['nombre'] ?? '') !== $nombre) $cambios[] = "Nombre: '{$old['nombre']}' ➔ '$nombre'";
                if (($old['direccion'] ?? '') !== $direccion) $cambios[] = "Dirección: '{$old['direccion']}' ➔ '$direccion'";
                if (($old['telefono'] ?? '') !== $telefono) $cambios[] = "Teléfono: '{$old['telefono']}' ➔ '$telefono'";
                if (($old['estado'] ?? '') !== $estado) $cambios[] = "Estado: '{$old['estado']}' ➔ '$estado'";
            }
            $descCambios = !empty($cambios) ? implode('; ', $cambios) : 'Guardado sin modificaciones de texto';
            registrarLog('EDITAR', 'sucursales', $id, "Sucursal '$nombre' (#$id) actualizada. [$descCambios]");

            if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Sucursal actualizada exitosamente']);
                exit;
            }

            header('Location: ../sucursales.php?success=' . urlencode('Sucursal actualizada exitosamente'));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            $check = query("SELECT COUNT(*) as count FROM sucursales WHERE id = ?", [$id]);
            if ($check[0]['count'] == 0) {
                throw new Exception('La sucursal no existe.');
            }

            $oldS = query("SELECT nombre FROM sucursales WHERE id = ?", [$id]);
            $sucNom = !empty($oldS) ? $oldS[0]['nombre'] : "ID #$id";

            $sql = "DELETE FROM sucursales WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'sucursales', $id, "Sucursal '$sucNom' (#$id) fue eliminada del sistema");

            header('Location: ../sucursales.php?success=' . urlencode('Sucursal eliminada exitosamente (incluido su inventario)'));
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en sucursales_action.php: " . $e->getMessage());
    header('Location: ../sucursales.php?error=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../sucursales.php?error=' . urlencode($e->getMessage()));
    exit;
}
