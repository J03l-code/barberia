<?php
require_once '../config.php';

// Validar permisos (Admin Técnico, Admin Local, o Administrador)
if (!isLoggedIn() || (!isAdminTecnico() && !isAdminLocal() && !in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin']))) {
    $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
              (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') !== false);
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    } else {
        header('Location: ../servicios.php?error=' . urlencode('Acceso no autorizado.'));
    }
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    // Helper: asegurar tablas servicios_sucursales, servicios_barberos y columnas en 'servicios'
    $ensureServiciosSchema = function() use ($pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `servicios_sucursales` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `servicio_id` INT UNSIGNED NOT NULL,
                    `sucursal_id` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_servicio_sucursal` (`servicio_id`, `sucursal_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {}

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `servicios_barberos` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `servicio_id` INT UNSIGNED NOT NULL,
                    `barbero_id` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_servicio_barbero` (`servicio_id`, `barbero_id`),
                    INDEX `idx_servicio` (`servicio_id`),
                    INDEX `idx_barbero` (`barbero_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (Exception $e) {}

        $neededCols = [
            'descripcion' => "TEXT DEFAULT NULL",
            'que_incluye' => "TEXT DEFAULT NULL",
            'duracion_minutos' => "INT UNSIGNED NOT NULL DEFAULT 30",
            'categoria' => "VARCHAR(50) NOT NULL DEFAULT 'General'",
            'orden' => "INT NOT NULL DEFAULT 0",
            'foto_url' => "VARCHAR(500) DEFAULT NULL",
            'imagen_url' => "VARCHAR(500) DEFAULT NULL",
            'destacado' => "TINYINT(1) DEFAULT 0",
            'barbero_id' => "INT UNSIGNED NULL DEFAULT NULL",
            'sucursal_id' => "INT UNSIGNED DEFAULT 1",
            'activo' => "TINYINT(1) NOT NULL DEFAULT 1"
        ];

        $colsStmt = $pdo->query("SHOW COLUMNS FROM servicios");
        $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($neededCols as $col => $ddl) {
            // Si tiene foto_url o imagen_url, no forzar ambas a menos que falten
            if ($col === 'imagen_url' && in_array('foto_url', $existingCols)) continue;
            if ($col === 'foto_url' && in_array('imagen_url', $existingCols)) continue;

            if (!in_array($col, $existingCols)) {
                try {
                    $pdo->exec("ALTER TABLE servicios ADD COLUMN `$col` $ddl");
                } catch (Exception $e) {}
            }
        }

        // Re-leer columnas actualizadas
        $colsStmt = $pdo->query("SHOW COLUMNS FROM servicios");
        return $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    };

    $columns = $ensureServiciosSchema();

    // Procesar subida de imagen referencial
    $uploaded_foto_url = '';
    if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_file']['tmp_name'];
        $fileName = $_FILES['foto_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'srv_' . uniqid() . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../assets/images/services/';
            
            if (!file_exists($uploadFileDir)) {
                @mkdir($uploadFileDir, 0777, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $uploaded_foto_url = '/assets/images/services/' . $newFileName;
            } else {
                throw new Exception('Error al guardar la imagen en el servidor.');
            }
        } else {
            throw new Exception('Formato de imagen no permitido (solo JPG, JPEG, PNG, GIF, WEBP).');
        }
    }

    switch ($action) {
        case 'update_order':
            $id = intval($_POST['id'] ?? 0);
            $orden = intval($_POST['orden'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de servicio no válido.');
            }
            $stmt = $pdo->prepare("UPDATE servicios SET orden = ? WHERE id = ?");
            $stmt->execute([$orden, $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Orden actualizado correctamente.', 'id' => $id, 'orden' => $orden]);
            exit;

        case 'reorder_batch':
            $items = $_POST['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                throw new Exception('No se enviaron servicios para reordenar.');
            }
            $stmt = $pdo->prepare("UPDATE servicios SET orden = ? WHERE id = ?");
            foreach ($items as $pos => $sId) {
                $stmt->execute([intval($pos) + 1, intval($sId)]);
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Servicios reordenados exitosamente.']);
            exit;

        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $que_incluye = trim($_POST['que_incluye'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $duracion_minutos = intval($_POST['duracion_minutos'] ?? 30);
            $categoria = trim($_POST['categoria'] ?? 'General');
            $orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;
            $activo = intval($_POST['activo'] ?? 1);
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            $sucursales = $_POST['sucursales'] ?? [];
            $barberos = isset($_POST['barberos']) && is_array($_POST['barberos']) ? array_map('intval', $_POST['barberos']) : [];
            
            // Si no se asignó orden explícito, calcular el siguiente dentro de su categoría
            if ($orden <= 0) {
                try {
                    $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) FROM servicios WHERE categoria = ?");
                    $maxStmt->execute([$categoria]);
                    $orden = intval($maxStmt->fetchColumn()) + 1;
                } catch (Throwable $eOrd) {
                    $orden = 1;
                }
            }

            // Si solo vino un barbero_id tradicional en POST
            if (empty($barberos) && !empty($_POST['barbero_id'])) {
                $barberos = [intval($_POST['barbero_id'])];
            }

            // Auto-asociar con Mateo si el nombre del corte lo especifica y no se eligieron otros barberos
            if (empty($barberos) && stripos($nombre, 'mateo') !== false) {
                try {
                    $mStmt = $pdo->query("SELECT id FROM usuarios WHERE (nombre LIKE '%mateo%' OR email LIKE '%mateo%') AND activo = 1 LIMIT 1");
                    $mId = $mStmt ? $mStmt->fetchColumn() : null;
                    if ($mId) $barberos = [intval($mId)];
                } catch (Exception $eMateo) {}
            }

            $barbero_id = (count($barberos) === 1) ? $barberos[0] : null;

            if (empty($nombre)) {
                throw new Exception('El nombre del servicio es obligatorio.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($duracion_minutos <= 0) {
                $duracion_minutos = 30;
            }

            $sucursal_id_default = !empty($sucursales) ? intval($sucursales[0]) : 1;

            // Construir inserción dinámica
            $dataToInsert = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'duracion_minutos' => $duracion_minutos,
                'activo' => $activo
            ];

            if (in_array('que_incluye', $columns)) $dataToInsert['que_incluye'] = $que_incluye;
            if (in_array('categoria', $columns)) $dataToInsert['categoria'] = $categoria;
            if (in_array('orden', $columns)) $dataToInsert['orden'] = $orden;
            if (in_array('destacado', $columns)) $dataToInsert['destacado'] = $destacado;
            if (in_array('barbero_id', $columns)) $dataToInsert['barbero_id'] = $barbero_id;
            if (in_array('sucursal_id', $columns)) $dataToInsert['sucursal_id'] = $sucursal_id_default;

            if (in_array('foto_url', $columns) && !empty($uploaded_foto_url)) {
                $dataToInsert['foto_url'] = $uploaded_foto_url;
            }
            if (in_array('imagen_url', $columns) && !empty($uploaded_foto_url)) {
                $dataToInsert['imagen_url'] = $uploaded_foto_url;
            }

            $colNames = array_keys($dataToInsert);
            $colPlaceholders = array_fill(0, count($colNames), '?');
            $insertSql = "INSERT INTO servicios (`" . implode('`, `', $colNames) . "`) VALUES (" . implode(', ', $colPlaceholders) . ")";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute(array_values($dataToInsert));
            $servicioId = $pdo->lastInsertId();

            // Guardar asignación a sucursales
            if (!empty($sucursales) && $servicioId) {
                try {
                    $insertSql = "INSERT IGNORE INTO servicios_sucursales (servicio_id, sucursal_id) VALUES (?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    foreach ($sucursales as $sucursalId) {
                        $insertStmt->execute([$servicioId, intval($sucursalId)]);
                    }
                } catch (Exception $e_rel) {
                    error_log("Error vinculando sucursales de servicio: " . $e_rel->getMessage());
                }
            }

            // Guardar asignación de barberos disponibles
            if ($servicioId) {
                try {
                    $pdo->prepare("DELETE FROM servicios_barberos WHERE servicio_id = ?")->execute([$servicioId]);
                    if (!empty($barberos)) {
                        $bStmt = $pdo->prepare("INSERT IGNORE INTO servicios_barberos (servicio_id, barbero_id) VALUES (?, ?)");
                        foreach ($barberos as $bId) {
                            $bStmt->execute([$servicioId, intval($bId)]);
                        }
                    }
                } catch (Exception $e_relb) {
                    error_log("Error vinculando barberos de servicio: " . $e_relb->getMessage());
                }
            }

            registrarLog('CREAR', 'servicios', $servicioId, "Servicio '$nombre' creado exitosamente (Orden: #$orden, Precio: $$precio, $duracion_minutos min)");
            header('Location: ../servicios.php?success=' . urlencode("Servicio '$nombre' creado exitosamente"));
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $que_incluye = trim($_POST['que_incluye'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $duracion_minutos = intval($_POST['duracion_minutos'] ?? 30);
            $categoria = trim($_POST['categoria'] ?? 'General');
            $orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;
            $activo = intval($_POST['activo'] ?? 1);
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            $sucursales = $_POST['sucursales'] ?? [];
            $barberos = isset($_POST['barberos']) && is_array($_POST['barberos']) ? array_map('intval', $_POST['barberos']) : [];
            
            // Si solo vino un barbero_id tradicional en POST y no vino el array
            if (empty($barberos) && isset($_POST['barbero_id']) && $_POST['barbero_id'] !== '') {
                $barberos = [intval($_POST['barbero_id'])];
            }

            $barbero_id = (count($barberos) === 1) ? $barberos[0] : null;

            if ($id <= 0) {
                throw new Exception('ID de servicio no válido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre del servicio es obligatorio.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($duracion_minutos <= 0) {
                $duracion_minutos = 30;
            }

            $sucursal_id_default = !empty($sucursales) ? intval($sucursales[0]) : 1;

            // Datos para actualizar
            $dataToUpdate = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'duracion_minutos' => $duracion_minutos,
                'activo' => $activo
            ];

            if (in_array('que_incluye', $columns)) $dataToUpdate['que_incluye'] = $que_incluye;
            if (in_array('categoria', $columns)) $dataToUpdate['categoria'] = $categoria;
            if (in_array('orden', $columns)) $dataToUpdate['orden'] = $orden;
            if (in_array('destacado', $columns)) $dataToUpdate['destacado'] = $destacado;
            if (in_array('barbero_id', $columns)) $dataToUpdate['barbero_id'] = $barbero_id;
            if (in_array('sucursal_id', $columns)) $dataToUpdate['sucursal_id'] = $sucursal_id_default;

            if (!empty($uploaded_foto_url)) {
                if (in_array('foto_url', $columns)) $dataToUpdate['foto_url'] = $uploaded_foto_url;
                if (in_array('imagen_url', $columns)) $dataToUpdate['imagen_url'] = $uploaded_foto_url;
            }

            $updateSets = [];
            $updateValues = [];
            foreach ($dataToUpdate as $col => $val) {
                $updateSets[] = "`$col` = ?";
                $updateValues[] = $val;
            }
            $updateValues[] = $id;

            $updateSql = "UPDATE servicios SET " . implode(', ', $updateSets) . " WHERE id = ?";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);

            // Actualizar asignaciones de sucursales
            try {
                $pdo->prepare("DELETE FROM servicios_sucursales WHERE servicio_id = ?")->execute([$id]);
                if (!empty($sucursales)) {
                    $insertStmt = $pdo->prepare("INSERT IGNORE INTO servicios_sucursales (servicio_id, sucursal_id) VALUES (?, ?)");
                    foreach ($sucursales as $sucursalId) {
                        $insertStmt->execute([$id, intval($sucursalId)]);
                    }
                }
            } catch (Exception $e_rel) {
                error_log("Error actualizando sucursales de servicio: " . $e_rel->getMessage());
            }

            // Actualizar asignaciones de barberos
            try {
                $pdo->prepare("DELETE FROM servicios_barberos WHERE servicio_id = ?")->execute([$id]);
                if (!empty($barberos)) {
                    $insertStmt = $pdo->prepare("INSERT IGNORE INTO servicios_barberos (servicio_id, barbero_id) VALUES (?, ?)");
                    foreach ($barberos as $bId) {
                        $insertStmt->execute([$id, intval($bId)]);
                    }
                }
            } catch (Exception $e_relb) {
                error_log("Error actualizando barberos de servicio: " . $e_relb->getMessage());
            }

            registrarLog('EDITAR', 'servicios', $id, "Servicio '$nombre' (#$id) actualizado exitosamente");
            header('Location: ../servicios.php?success=' . urlencode("Servicio '$nombre' actualizado exitosamente"));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de servicio no válido.');
            }

            // Verificar si hay citas asociadas
            try {
                $check = query("SELECT COUNT(*) as count FROM citas WHERE servicio_id = ?", [$id]);
                if (!empty($check) && $check[0]['count'] > 0) {
                    // En vez de bloquear, desvincular o impedir según seguridad
                    throw new Exception('No se puede eliminar el servicio porque tiene citas asociadas en el historial.');
                }
            } catch (PDOException $e) {}

            $sName = query("SELECT nombre FROM servicios WHERE id = ?", [$id]);
            $sNom = !empty($sName) ? $sName[0]['nombre'] : "ID #$id";

            // Eliminar relaciones de sucursal y barberos
            try {
                $pdo->prepare("DELETE FROM servicios_sucursales WHERE servicio_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM servicios_barberos WHERE servicio_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            $sql = "DELETE FROM servicios WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'servicios', $id, "Servicio '$sNom' (#$id) fue eliminado del catálogo");
            header('Location: ../servicios.php?success=' . urlencode("Servicio '$sNom' eliminado exitosamente"));
            exit;

        default:
            throw new Exception('Acción no válida solicitada.');
    }

} catch (PDOException $e) {
    error_log("Error en servicios_action.php: " . $e->getMessage());
    header('Location: ../servicios.php?error=' . urlencode('Error en base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../servicios.php?error=' . urlencode($e->getMessage()));
    exit;
}
