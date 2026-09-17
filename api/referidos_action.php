<?php
/**
 * KORTZEN - API para Validación y Aplicación de Códigos de Referido
 */
header('Content-Type: application/json');
require_once '../config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getConnection();

    // Auto-asegurar esquema de tabla referidos para compatibilidad
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `referidos` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `referente_id` INT UNSIGNED NULL,
                `referido_id` INT UNSIGNED NULL,
                `codigo_usado` VARCHAR(50) NULL,
                `cita_id` INT UNSIGNED NULL,
                `descuento_aplicado` DECIMAL(10,2) DEFAULT 0.00,
                `puntos_otorgados` INT DEFAULT 0,
                `estado` ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'pendiente',
                `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $colsRef = $pdo->query("SHOW COLUMNS FROM `referidos`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('referido_id', $colsRef)) {
            if (in_array('cliente_referido_id', $colsRef)) {
                $pdo->exec("ALTER TABLE `referidos` CHANGE COLUMN `cliente_referido_id` `referido_id` INT UNSIGNED NULL");
            } else {
                $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `referido_id` INT UNSIGNED NULL");
            }
        }

        if (!in_array('referente_id', $colsRef)) {
            if (in_array('cliente_origen_id', $colsRef)) {
                $pdo->exec("ALTER TABLE `referidos` CHANGE COLUMN `cliente_origen_id` `referente_id` INT UNSIGNED NULL");
            } else {
                $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `referente_id` INT UNSIGNED NULL");
            }
        }

        if (!in_array('codigo_usado', $colsRef)) {
            $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `codigo_usado` VARCHAR(50) NULL");
        }
        if (!in_array('cita_id', $colsRef)) {
            $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `cita_id` INT UNSIGNED NULL");
        }
        if (!in_array('descuento_aplicado', $colsRef)) {
            $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `descuento_aplicado` DECIMAL(10,2) DEFAULT 0.00");
        }
        if (!in_array('puntos_otorgados', $colsRef)) {
            $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `puntos_otorgados` INT DEFAULT 0");
        }
        if (!in_array('estado', $colsRef)) {
            $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `estado` ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'pendiente'");
        }
    } catch (Exception $exRefSchema) {}

    // 1. Validar Código de Referido
    if ($action === 'validar') {
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $codigoLimpio = str_replace('KORTZEN-', '', $codigo);

        if (empty($codigoLimpio)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa un código.']);
            exit;
        }

        // A) PRIMERO: Comprobar si es un Código Promocional / Cupón
        try {
            $stmtPromo = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE UPPER(codigo) = ?");
            $stmtPromo->execute([$codigoLimpio]);
            $promo = $stmtPromo->fetch(PDO::FETCH_ASSOC);

            if ($promo) {
                if (!$promo['activo']) {
                    echo json_encode(['success' => false, 'message' => 'Este código promocional se encuentra inactivo.']);
                    exit;
                }

                // Verificar límite de 1 uso por cliente
                if (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] > 0) {
                    $clienteIdActual = intval($_SESSION['cliente_id']);
                    $stmtCheckUso = $pdo->prepare("SELECT COUNT(*) FROM usos_codigos_promocionales WHERE codigo_id = ? AND cliente_id = ?");
                    $stmtCheckUso->execute([$promo['id'], $clienteIdActual]);
                    if ($stmtCheckUso->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Ya has utilizado este código promocional en una reserva anterior. Es válido solo 1 vez por cliente.']);
                        exit;
                    }
                }

                $porcentajeDesc = floatval($promo['descuento_porcentaje']);
                echo json_encode([
                    'success' => true,
                    'tipo' => 'promocional',
                    'promo_id' => $promo['id'],
                    'descuento_porcentaje' => $porcentajeDesc,
                    'codigo' => $promo['codigo'],
                    'message' => '¡Código Promocional ' . $promo['codigo'] . ' aplicado! Descuento del ' . number_format($porcentajeDesc, 1) . '% activado.'
                ]);
                exit;
            }
        } catch (Exception $exPromo) {}

        // Cargar configuraciones de la base de datos
        $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
        $configs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        $montoDescuento = floatval($configs['descuento_referido_amigo'] ?? 2.00);

        // Buscar cliente dueño del código
        $stmtRef = $pdo->prepare("SELECT id, nombre FROM clientes WHERE codigo_referido = ? OR codigo_referido = ?");
        $stmtRef->execute([$codigoLimpio, $codigo]);
        $referente = $stmtRef->fetch(PDO::FETCH_ASSOC);

        if (!$referente) {
            echo json_encode(['success' => false, 'message' => 'El código de referido no existe o es inválido.']);
            exit;
        }

        // Validaciones del cliente que intenta aplicar el código
        if (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] > 0) {
            $clienteIdActual = intval($_SESSION['cliente_id']);

            // 1. No puede usar su propio código de referido
            if ($clienteIdActual == $referente['id']) {
                echo json_encode(['success' => false, 'message' => 'No puedes usar tu propio código de referido.']);
                exit;
            }

            // 2. Verificar si ya ha usado un código de referido anteriormente
            $stmtCheckUsed = $pdo->prepare("SELECT COUNT(*) FROM referidos WHERE referido_id = ?");
            $stmtCheckUsed->execute([$clienteIdActual]);
            if ($stmtCheckUsed->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El código de referido solo es válido para tu primera reserva. Ya has utilizado uno anteriormente.']);
                exit;
            }

            // 3. Verificar si el cliente ya tiene citas previas registradas (solo válido para la primera visita)
            $stmtCheckCitas = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE cliente_id = ? AND estado != 'cancelada'");
            $stmtCheckCitas->execute([$clienteIdActual]);
            if ($stmtCheckCitas->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El descuento por referido es exclusivo para nuevos clientes en su primera visita.']);
                exit;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => '¡Código de ' . explode(' ', $referente['nombre'])[0] . ' aplicado! Descuento de $' . number_format($montoDescuento, 2) . ' activado.',
            'referente_id' => $referente['id'],
            'referente_nombre' => $referente['nombre'],
            'codigo' => $codigo,
            'descuento' => $montoDescuento
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
