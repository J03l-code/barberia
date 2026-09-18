<?php
require_once '../config.php';

$isJson = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') !== false) ||
          (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

if (!isLoggedIn()) {
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

$action = $_POST['action'] ?? '';

// Validar que solo administradores puedan crear/editar/eliminar (los barberos solo pueden retirar)
if (in_array($action, ['create', 'update', 'delete'])) {
    if (!canManageInventory() && !isAdminTecnico() && !isAdminLocal() && !in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin'])) {
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No tienes permisos suficientes para gestionar el inventario.']);
            exit;
        }
        header('Location: ../inventario.php?error=' . urlencode('No tienes permisos suficientes para gestionar el inventario.'));
        exit;
    }
}

try {
    $pdo = getConnection();

    // Helper: asegurar columnas en 'inventario'
    $ensureInventarioSchema = function() use ($pdo) {
        $neededCols = [
            'cantidad' => "INT NOT NULL DEFAULT 0",
            'precio' => "DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'stock_minimo' => "INT NOT NULL DEFAULT 5",
            'sucursal_id' => "INT UNSIGNED NOT NULL DEFAULT 1",
            'unidad' => "VARCHAR(50) DEFAULT 'unidades'",
            'categoria' => "VARCHAR(100) DEFAULT 'General'",
            'descripcion' => "TEXT DEFAULT NULL"
        ];

        $colsStmt = $pdo->query("SHOW COLUMNS FROM inventario");
        $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($neededCols as $col => $ddl) {
            if (!in_array($col, $existingCols)) {
                try {
                    $pdo->exec("ALTER TABLE inventario ADD COLUMN `$col` $ddl");
                } catch (Exception $e) {}
            }
        }

        // Re-leer columnas actualizadas
        $colsStmt = $pdo->query("SHOW COLUMNS FROM inventario");
        return $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    };

    $columns = $ensureInventarioSchema();

    switch ($action) {
        case 'withdraw':
            $id = intval($_POST['id'] ?? 0);
            $cantidad = intval($_POST['cantidad'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de producto no válido.');
            }

            if ($cantidad <= 0) {
                throw new Exception('La cantidad a retirar debe ser mayor a 0.');
            }

            // Verificar stock actual
            $product = query("SELECT producto, cantidad FROM inventario WHERE id = ?", [$id]);
            if (empty($product)) {
                throw new Exception('El producto no existe en el inventario.');
            }

            $currentStock = intval($product[0]['cantidad']);
            $productName = $product[0]['producto'];

            if ($cantidad > $currentStock) {
                throw new Exception("No hay suficiente stock de '{$productName}'. Stock actual: {$currentStock}");
            }

            // Descontar inventario
            $sql = "UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cantidad, $id]);

            registrarLog('RETIRAR', 'inventario', $id, "Se retiraron $cantidad unidades de '$productName'");
            header('Location: ../inventario.php?success=' . urlencode("Se retiraron {$cantidad} unidades de '{$productName}' exitosamente"));
            exit;

        case 'create':
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = intval($_POST['cantidad'] ?? 0);
            $precio = floatval($_POST['precio'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 1);
            $unidad = trim($_POST['unidad'] ?? 'unidades');
            $categoria = trim($_POST['categoria'] ?? 'General');
            $descripcion = trim($_POST['descripcion'] ?? '');

            // Validaciones
            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }

            if ($sucursal_id <= 0) {
                $sucursal_id = 1;
            }

            if ($cantidad < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($stock_minimo < 0) {
                $stock_minimo = 0;
            }

            // Construir inserción dinámica
            $dataToInsert = [
                'producto' => $producto,
                'cantidad' => $cantidad
            ];

            if (in_array('precio', $columns)) $dataToInsert['precio'] = $precio;
            if (in_array('stock_minimo', $columns)) $dataToInsert['stock_minimo'] = $stock_minimo;
            if (in_array('sucursal_id', $columns)) $dataToInsert['sucursal_id'] = $sucursal_id;
            if (in_array('unidad', $columns)) $dataToInsert['unidad'] = $unidad;
            if (in_array('categoria', $columns)) $dataToInsert['categoria'] = $categoria;
            if (in_array('descripcion', $columns)) $dataToInsert['descripcion'] = $descripcion;

            $colNames = array_keys($dataToInsert);
            $colPlaceholders = array_fill(0, count($colNames), '?');
            $insertSql = "INSERT INTO inventario (`" . implode('`, `', $colNames) . "`) VALUES (" . implode(', ', $colPlaceholders) . ")";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute(array_values($dataToInsert));

            $newInvId = $pdo->lastInsertId();
            registrarLog('CREAR', 'inventario', $newInvId, "Producto '$producto' agregado al inventario ($cantidad unidades)");
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Producto '$producto' agregado exitosamente", 'id' => $newInvId]);
                exit;
            }
            header('Location: ../inventario.php?success=' . urlencode("Producto '$producto' agregado exitosamente"));
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = intval($_POST['cantidad'] ?? 0);
            $precio = floatval($_POST['precio'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 1);
            $unidad = trim($_POST['unidad'] ?? 'unidades');
            $categoria = trim($_POST['categoria'] ?? 'General');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($id <= 0) {
                throw new Exception('ID de producto no válido.');
            }

            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }

            if ($sucursal_id <= 0) {
                $sucursal_id = 1;
            }

            if ($cantidad < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($stock_minimo < 0) {
                $stock_minimo = 0;
            }

            // Construir actualización dinámica
            $dataToUpdate = [
                'producto' => $producto,
                'cantidad' => $cantidad
            ];

            if (in_array('precio', $columns)) $dataToUpdate['precio'] = $precio;
            if (in_array('stock_minimo', $columns)) $dataToUpdate['stock_minimo'] = $stock_minimo;
            if (in_array('sucursal_id', $columns)) $dataToUpdate['sucursal_id'] = $sucursal_id;
            if (in_array('unidad', $columns)) $dataToUpdate['unidad'] = $unidad;
            if (in_array('categoria', $columns)) $dataToUpdate['categoria'] = $categoria;
            if (in_array('descripcion', $columns)) $dataToUpdate['descripcion'] = $descripcion;

            $updateSets = [];
            $updateValues = [];
            foreach ($dataToUpdate as $col => $val) {
                $updateSets[] = "`$col` = ?";
                $updateValues[] = $val;
            }
            $updateValues[] = $id;

            $updateSql = "UPDATE inventario SET " . implode(', ', $updateSets) . " WHERE id = ?";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);

            registrarLog('EDITAR', 'inventario', $id, "Producto '$producto' (#$id) actualizado exitosamente");
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Producto '$producto' actualizado exitosamente"]);
                exit;
            }
            header('Location: ../inventario.php?success=' . urlencode("Producto '$producto' actualizado exitosamente"));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de producto no válido.');
            }

            // Verificar que el producto existe
            $check = query("SELECT producto FROM inventario WHERE id = ?", [$id]);
            if (empty($check)) {
                throw new Exception('El producto que deseas eliminar no existe.');
            }
            $prodName = $check[0]['producto'];

            $sql = "DELETE FROM inventario WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'inventario', $id, "Producto '$prodName' (#$id) eliminado del inventario");
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Producto '$prodName' eliminado exitosamente"]);
                exit;
            }
            header('Location: ../inventario.php?success=' . urlencode("Producto '$prodName' eliminado exitosamente"));
            exit;

        default:
            throw new Exception('Acción no válida solicitada.');
    }

} catch (PDOException $e) {
    error_log("Error en inventario_action.php: " . $e->getMessage());
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
        exit;
    }
    header('Location: ../inventario.php?error=' . urlencode('Error en base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header('Location: ../inventario.php?error=' . urlencode($e->getMessage()));
    exit;
}
