<?php
require_once '../config.php';

// Validar permisos (Admin Técnico, Admin Local, o Administrador)
if (!isLoggedIn() || (!isAdminTecnico() && !canManageUsers() && !in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin']))) {
    $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
              (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') !== false);
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    } else {
        header('Location: ../usuarios.php?error=' . urlencode('Acceso no autorizado.'));
    }
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    // Helper: asegurar columnas y obtener columnas existentes en 'usuarios'
    $ensureUsuariosColumns = function() use ($pdo) {
        $neededCols = [
            'telefono' => "VARCHAR(30) DEFAULT NULL",
            'foto_url' => "VARCHAR(500) DEFAULT NULL",
            'bio' => "TEXT DEFAULT NULL",
            'biografia' => "TEXT DEFAULT NULL",
            'especialidades' => "VARCHAR(255) DEFAULT NULL",
            'comision_porcentaje' => "DECIMAL(5,2) DEFAULT 50.00",
            'comision_fin_semana' => "DECIMAL(5,2) DEFAULT 50.00",
            'comision_productos' => "DECIMAL(5,2) DEFAULT 10.00",
            'almuerzo_inicio' => "TIME DEFAULT '13:00:00'",
            'almuerzo_fin' => "TIME DEFAULT '14:00:00'",
            'almuerzo_activo' => "TINYINT DEFAULT 1",
            'sucursal_id' => "INT UNSIGNED DEFAULT NULL",
            'activo' => "TINYINT(1) NOT NULL DEFAULT 1"
        ];

        try {
            $colsStmt = $pdo->query("SHOW COLUMNS FROM usuarios");
            $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            $existingCols = [];
        }

        foreach ($neededCols as $col => $ddl) {
            // Si falta 'bio' y 'biografia', agregamos al menos uno
            if ($col === 'biografia' && in_array('bio', $existingCols)) continue;
            if ($col === 'bio' && in_array('biografia', $existingCols)) continue;

            if (!in_array($col, $existingCols)) {
                try {
                    $pdo->exec("ALTER TABLE usuarios ADD COLUMN `$col` $ddl");
                } catch (Throwable $e) {}
            }
        }

        // Re-leer columnas actualizadas
        try {
            $colsStmt = $pdo->query("SHOW COLUMNS FROM usuarios");
            return $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            return $existingCols;
        }
    };


    $columns = $ensureUsuariosColumns();

    // Procesar foto de perfil si se subió un archivo
    $foto_url = trim($_POST['foto_url'] ?? '');
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileName = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = 'barber_' . uniqid() . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../assets/images/barbers/';
            
            if (!file_exists($uploadFileDir)) {
                @mkdir($uploadFileDir, 0777, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $foto_url = '/assets/images/barbers/' . $newFileName;
            } else {
                throw new Exception('Error al guardar la foto de perfil en el servidor.');
            }
        } else {
            throw new Exception('Formato de imagen no permitido (solo JPG, JPEG, PNG, GIF, WEBP).');
        }
    }

    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'barbero';
            $sucursal_id = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;

            $sucursales_ids = [];
            if (isset($_POST['sucursales_ids'])) {
                if (is_array($_POST['sucursales_ids'])) {
                    $sucursales_ids = array_map('intval', $_POST['sucursales_ids']);
                } else {
                    $sucursales_ids = [intval($_POST['sucursales_ids'])];
                }
            }
            $sucursales_ids = array_values(array_filter($sucursales_ids, function($v) { return $v > 0; }));

            if ($rol === 'admin_local') {
                if (empty($sucursales_ids) && !empty($sucursal_id)) {
                    $sucursales_ids = [$sucursal_id];
                }
                $sucursal_id = !empty($sucursales_ids) ? $sucursales_ids[0] : null;
            } elseif ($rol === 'admin') {
                $sucursal_id = null;
                $sucursales_ids = [];
            }

            $biografia = trim($_POST['biografia'] ?? ($_POST['bio'] ?? ''));
            $especialidades = trim($_POST['especialidades'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $comision_porcentaje = floatval($_POST['comision_porcentaje'] ?? 50.00);
            $comision_fin_semana = floatval($_POST['comision_fin_semana'] ?? 50.00);
            $comision_productos = floatval($_POST['comision_productos'] ?? 10.00);
            $almuerzo_inicio = trim($_POST['almuerzo_inicio'] ?? '13:00');
            $almuerzo_fin = trim($_POST['almuerzo_fin'] ?? '14:00');
            $almuerzo_activo = isset($_POST['almuerzo_activo']) ? intval($_POST['almuerzo_activo']) : 1;

            // Validaciones
            if (empty($nombre) || empty($email) || empty($password)) {
                throw new Exception('El nombre, correo electrónico y contraseña son obligatorios.');
            }

            if (!isValidEmail($email)) {
                throw new Exception('El correo electrónico no tiene un formato válido.');
            }

            if (strlen($password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres.');
            }

            // Verificar que el email no exista
            $check = query("SELECT COUNT(*) as count FROM usuarios WHERE email = ?", [$email]);
            if (!empty($check) && $check[0]['count'] > 0) {
                throw new Exception('El correo electrónico ya se encuentra registrado en el sistema.');
            }

            // Hash de contraseña con BCRYPT
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Mapeo dinámico de datos según columnas existentes
            $dataToInsert = [
                'nombre' => $nombre,
                'email' => $email,
                'password' => $passwordHash,
                'rol' => $rol,
                'sucursal_id' => $sucursal_id
            ];

            if (in_array('telefono', $columns)) $dataToInsert['telefono'] = $telefono;
            if (in_array('foto_url', $columns)) $dataToInsert['foto_url'] = $foto_url;
            if (in_array('especialidades', $columns)) $dataToInsert['especialidades'] = $especialidades;
            if (in_array('bio', $columns)) $dataToInsert['bio'] = $biografia;
            if (in_array('biografia', $columns)) $dataToInsert['biografia'] = $biografia;

            
            if (in_array('comision_porcentaje', $columns)) $dataToInsert['comision_porcentaje'] = $comision_porcentaje;
            if (in_array('comision_fin_semana', $columns)) $dataToInsert['comision_fin_semana'] = $comision_fin_semana;
            if (in_array('comision_productos', $columns)) $dataToInsert['comision_productos'] = $comision_productos;
            if (in_array('almuerzo_inicio', $columns)) $dataToInsert['almuerzo_inicio'] = $almuerzo_inicio;
            if (in_array('almuerzo_fin', $columns)) $dataToInsert['almuerzo_fin'] = $almuerzo_fin;
            if (in_array('almuerzo_activo', $columns)) $dataToInsert['almuerzo_activo'] = $almuerzo_activo;
            if (in_array('activo', $columns)) $dataToInsert['activo'] = 1;

            $colNames = array_keys($dataToInsert);
            $colPlaceholders = array_fill(0, count($colNames), '?');
            $insertSql = "INSERT INTO usuarios (`" . implode('`, `', $colNames) . "`) VALUES (" . implode(', ', $colPlaceholders) . ")";
            
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute(array_values($dataToInsert));
            $newUserId = $pdo->lastInsertId();

            // Sincronizar multi-sucursales para admin_local
            if ($newUserId && $rol === 'admin_local' && !empty($sucursales_ids)) {
                try {
                    $stmtInsSuc = $pdo->prepare("INSERT IGNORE INTO usuarios_sucursales (usuario_id, sucursal_id) VALUES (?, ?)");
                    foreach ($sucursales_ids as $sId) {
                        $stmtInsSuc->execute([$newUserId, $sId]);
                    }
                } catch (Exception $e_us) {}
            }

            // Si es un barbero, inicializar horarios semanales (Lun a Dom de 10:00 a 20:00)
            if ($rol === 'barbero' && $newUserId) {
                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `horarios_barberos` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `barbero_id` INT UNSIGNED NOT NULL,
                            `dia_semana` TINYINT UNSIGNED NOT NULL,
                            `hora_inicio` TIME NOT NULL DEFAULT '10:00:00',
                            `hora_fin` TIME NOT NULL DEFAULT '20:00:00',
                            `activo` TINYINT(1) NOT NULL DEFAULT 1,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `uk_barbero_dia` (`barbero_id`, `dia_semana`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    $stmtH = $pdo->prepare("INSERT IGNORE INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, '10:00:00', '20:00:00', 1)");
                    for ($d = 0; $d <= 6; $d++) {
                        $stmtH->execute([$newUserId, $d]);
                    }
                } catch (Exception $e_horarios) {
                    error_log("Error inicializando horarios de barbero: " . $e_horarios->getMessage());
                }
            }

            registrarLog('CREAR', 'usuarios', $newUserId, "Usuario '$nombre' ($rol) creado exitosamente");
            header('Location: ../usuarios.php?success=' . urlencode("Usuario '$nombre' creado exitosamente"));
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'barbero';
            $sucursal_id = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;

            $sucursales_ids = [];
            if (isset($_POST['sucursales_ids'])) {
                if (is_array($_POST['sucursales_ids'])) {
                    $sucursales_ids = array_map('intval', $_POST['sucursales_ids']);
                } else {
                    $sucursales_ids = [intval($_POST['sucursales_ids'])];
                }
            }
            $sucursales_ids = array_values(array_filter($sucursales_ids, function($v) { return $v > 0; }));

            if ($rol === 'admin_local') {
                if (empty($sucursales_ids) && !empty($sucursal_id)) {
                    $sucursales_ids = [$sucursal_id];
                }
                $sucursal_id = !empty($sucursales_ids) ? $sucursales_ids[0] : null;
            } elseif ($rol === 'admin') {
                $sucursal_id = null;
                $sucursales_ids = [];
            }

            $biografia = trim($_POST['biografia'] ?? ($_POST['bio'] ?? ''));
            $especialidades = trim($_POST['especialidades'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $comision_porcentaje = floatval($_POST['comision_porcentaje'] ?? 50.00);
            $comision_fin_semana = floatval($_POST['comision_fin_semana'] ?? 50.00);
            $comision_productos = floatval($_POST['comision_productos'] ?? 10.00);
            $almuerzo_inicio = trim($_POST['almuerzo_inicio'] ?? '13:00');
            $almuerzo_fin = trim($_POST['almuerzo_fin'] ?? '14:00');
            $almuerzo_activo = isset($_POST['almuerzo_activo']) ? intval($_POST['almuerzo_activo']) : 1;

            if ($id <= 0) {
                throw new Exception('ID de usuario no válido.');
            }

            if (empty($nombre) || empty($email)) {
                throw new Exception('El nombre y correo electrónico son obligatorios.');
            }

            if (!isValidEmail($email)) {
                throw new Exception('El correo electrónico no tiene un formato válido.');
            }

            // Verificar que el email no esté en uso por otro usuario
            $check = query("SELECT COUNT(*) as count FROM usuarios WHERE email = ? AND id != ?", [$email, $id]);
            if (!empty($check) && $check[0]['count'] > 0) {
                throw new Exception('El correo electrónico ya está en uso por otro usuario.');
            }

            // Obtener datos anteriores para registrar el log
            $oldU = query("SELECT * FROM usuarios WHERE id = ?", [$id]);
            $old = !empty($oldU) ? $oldU[0] : [];

            // Mapeo dinámico de datos según columnas existentes
            $dataToUpdate = [
                'nombre' => $nombre,
                'email' => $email,
                'rol' => $rol,
                'sucursal_id' => $sucursal_id
            ];

            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception('La nueva contraseña debe tener al menos 6 caracteres.');
                }
                $dataToUpdate['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            if (in_array('telefono', $columns)) $dataToUpdate['telefono'] = $telefono;
            if (in_array('foto_url', $columns)) {
                // Solo actualizar foto_url si se subió nueva foto o si ya se envió un valor
                if (!empty($foto_url) || isset($_POST['foto_url'])) {
                    $dataToUpdate['foto_url'] = $foto_url;
                }
            }
            if (in_array('especialidades', $columns)) $dataToUpdate['especialidades'] = $especialidades;
            if (in_array('bio', $columns)) $dataToUpdate['bio'] = $biografia;
            if (in_array('biografia', $columns)) $dataToUpdate['biografia'] = $biografia;


            if (in_array('comision_porcentaje', $columns)) $dataToUpdate['comision_porcentaje'] = $comision_porcentaje;
            if (in_array('comision_fin_semana', $columns)) $dataToUpdate['comision_fin_semana'] = $comision_fin_semana;
            if (in_array('comision_productos', $columns)) $dataToUpdate['comision_productos'] = $comision_productos;
            if (in_array('almuerzo_inicio', $columns)) $dataToUpdate['almuerzo_inicio'] = $almuerzo_inicio;
            if (in_array('almuerzo_fin', $columns)) $dataToUpdate['almuerzo_fin'] = $almuerzo_fin;
            if (in_array('almuerzo_activo', $columns)) $dataToUpdate['almuerzo_activo'] = $almuerzo_activo;

            $updateSets = [];
            $updateValues = [];
            foreach ($dataToUpdate as $col => $val) {
                $updateSets[] = "`$col` = ?";
                $updateValues[] = $val;
            }
            $updateValues[] = $id;

            $updateSql = "UPDATE usuarios SET " . implode(', ', $updateSets) . " WHERE id = ?";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);

            // Sincronizar tabla usuarios_sucursales
            try {
                $pdo->prepare("DELETE FROM usuarios_sucursales WHERE usuario_id = ?")->execute([$id]);
                if ($rol === 'admin_local' && !empty($sucursales_ids)) {
                    $stmtInsSuc = $pdo->prepare("INSERT IGNORE INTO usuarios_sucursales (usuario_id, sucursal_id) VALUES (?, ?)");
                    foreach ($sucursales_ids as $sId) {
                        $stmtInsSuc->execute([$id, $sId]);
                    }
                }
            } catch (Exception $e_us) {}

            registrarLog('EDITAR', 'usuarios', $id, "Usuario '$nombre' (#$id) actualizado exitosamente");
            header('Location: ../usuarios.php?success=' . urlencode("Usuario '$nombre' actualizado exitosamente"));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de usuario no válido.');
            }

            // No permitir eliminar al usuario actual
            if ($id == $_SESSION['user_id']) {
                throw new Exception('No puedes eliminar tu propia cuenta de usuario en sesión.');
            }

            // Eliminar registros asociados en usuarios_sucursales
            try {
                $pdo->prepare("DELETE FROM usuarios_sucursales WHERE usuario_id = ?")->execute([$id]);
            } catch (Exception $e_del_us) {}

            // Obtener nombre del usuario antes de eliminarlo
            $stmtU = $pdo->prepare("SELECT nombre, rol FROM usuarios WHERE id = ?");
            $stmtU->execute([$id]);
            $uData = $stmtU->fetch(PDO::FETCH_ASSOC);
            $uNombre = $uData ? $uData['nombre'] : "ID #$id";

            // Desvincular citas del barbero
            try {
                $pdo->prepare("UPDATE citas SET barbero_id = NULL WHERE barbero_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            // Eliminar horarios del barbero
            try {
                $pdo->prepare("DELETE FROM horarios_barberos WHERE barbero_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            // Eliminar días bloqueados
            try {
                $pdo->prepare("DELETE FROM dias_bloqueados WHERE barbero_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            // Eliminar bloqueos de horas
            try {
                $pdo->prepare("DELETE FROM bloqueos_horas WHERE barbero_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            // Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'usuarios', $id, "Usuario '$uNombre' fue eliminado del sistema");
            header('Location: ../usuarios.php?success=' . urlencode("Usuario '$uNombre' eliminado exitosamente"));
            exit;

        default:
            throw new Exception('Acción no válida solicitada.');
    }

} catch (PDOException $e) {
    error_log("Error en usuarios_action.php: " . $e->getMessage());
    header('Location: ../usuarios.php?error=' . urlencode('Error en base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../usuarios.php?error=' . urlencode($e->getMessage()));
    exit;
}
