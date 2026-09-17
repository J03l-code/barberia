<?php
/**
 * KORTZEN - Instalador Automático de Base de Datos
 * Ejecuta este script desde el navegador para crear todas las tablas y datos iniciales.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$status = [];
$error = null;

try {
    $pdo = getConnection();
    $status[] = "✓ Conexión establecida exitosamente con la base de datos: <strong>" . DB_NAME . "</strong>";

    // Desactivar chequeo de foreign keys temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. SUCURSALES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sucursales` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL,
            `direccion` VARCHAR(255) DEFAULT NULL,
            `telefono` VARCHAR(20) DEFAULT NULL,
            `horario_apertura` TIME DEFAULT '10:00:00',
            `horario_cierre` TIME DEFAULT '20:00:00',
            `estado` VARCHAR(50) DEFAULT 'activo',
            `mapa_url` TEXT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_sucursales_activo` (`activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `sucursales` lista";

    // 2. USUARIOS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `rol` ENUM('admin', 'admin_local', 'barbero') NOT NULL DEFAULT 'barbero',
            `sucursal_id` INT UNSIGNED DEFAULT NULL,
            `telefono` VARCHAR(30) DEFAULT NULL,
            `foto_url` VARCHAR(500) DEFAULT NULL,
            `bio` TEXT DEFAULT NULL,
            `biografia` TEXT DEFAULT NULL,
            `especialidades` VARCHAR(255) DEFAULT NULL,
            `comision_porcentaje` DECIMAL(5,2) DEFAULT 50.00,
            `comision_fin_semana` DECIMAL(5,2) DEFAULT 50.00,
            `comision_productos` DECIMAL(5,2) DEFAULT 10.00,
            `almuerzo_inicio` TIME DEFAULT '13:00:00',
            `almuerzo_fin` TIME DEFAULT '14:00:00',
            `almuerzo_activo` TINYINT DEFAULT 1,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_usuarios_email` (`email`),
            INDEX `idx_usuarios_rol` (`rol`),
            INDEX `idx_usuarios_sucursal` (`sucursal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `usuarios` lista";

    // 3. CLIENTES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `clientes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `password` VARCHAR(255) DEFAULT NULL,
            `telefono` VARCHAR(20) DEFAULT NULL,
            `fecha_nacimiento` DATE DEFAULT NULL,
            `notas` TEXT DEFAULT NULL,
            `google_id` VARCHAR(100) DEFAULT NULL,
            `foto_perfil` VARCHAR(500) DEFAULT NULL,
            `codigo_referido` VARCHAR(20) DEFAULT NULL,
            `referido_por_id` INT UNSIGNED DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_clientes_email` (`email`),
            UNIQUE KEY `uk_clientes_google_id` (`google_id`),
            INDEX `idx_clientes_telefono` (`telefono`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `clientes` lista";

    // 4. SERVICIOS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `servicios` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL,
            `descripcion` TEXT DEFAULT NULL,
            `precio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `duracion_minutos` INT UNSIGNED NOT NULL DEFAULT 30,
            `categoria` VARCHAR(50) NOT NULL DEFAULT 'General',
            `orden` INT NOT NULL DEFAULT 0,
            `foto_url` VARCHAR(500) DEFAULT NULL,
            `imagen_url` VARCHAR(500) DEFAULT NULL,
            `destacado` TINYINT(1) DEFAULT 0,
            `sucursal_id` INT UNSIGNED DEFAULT 1,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_servicios_activo` (`activo`),
            INDEX `idx_servicio_categoria_orden` (`categoria`, `orden`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `servicios` lista";

    // 4.1 SERVICIOS_SUCURSALES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `servicios_sucursales` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `servicio_id` INT UNSIGNED NOT NULL,
            `sucursal_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_servicio_sucursal` (`servicio_id`, `sucursal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `servicios_sucursales` lista";

    // 4.2 CATEGORIAS_SERVICIOS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categorias_servicios` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL UNIQUE,
            `descripcion` TEXT DEFAULT NULL,
            `icono` VARCHAR(50) DEFAULT 'scissors',
            `orden` INT NOT NULL DEFAULT 0,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_cat_activo_orden` (`activo`, `orden`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `categorias_servicios` lista";

    // 5. INVENTARIO
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inventario` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `producto` VARCHAR(150) NOT NULL,
            `cantidad` INT NOT NULL DEFAULT 0,
            `precio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `stock_minimo` INT NOT NULL DEFAULT 5,
            `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `unidad` VARCHAR(50) DEFAULT 'unidades',
            `categoria` VARCHAR(100) DEFAULT 'General',
            `descripcion` TEXT DEFAULT NULL,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_inventario_sucursal` (`sucursal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `inventario` lista";

    // 6. CITAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `citas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cliente_id` INT UNSIGNED NOT NULL,
            `servicio_id` INT UNSIGNED NOT NULL,
            `barbero_id` INT UNSIGNED DEFAULT NULL,
            `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `fecha_hora` DATETIME NOT NULL,
            `estado` ENUM('pendiente','confirmada','completada','cancelada') NOT NULL DEFAULT 'pendiente',
            `notas` TEXT DEFAULT NULL,
            `precio_final` DECIMAL(10, 2) DEFAULT NULL,
            `propina` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `asistencia_confirmada` TINYINT(1) DEFAULT 0,
            `recordatorio_2h_enviado` TINYINT(1) DEFAULT 0,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_citas_fecha` (`fecha_hora`),
            INDEX `idx_citas_estado` (`estado`),
            INDEX `idx_citas_barbero_fecha` (`barbero_id`, `fecha_hora`),
            INDEX `idx_citas_cliente` (`cliente_id`),
            INDEX `idx_citas_sucursal` (`sucursal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `citas` lista";

    // 7. HORARIOS BARBEROS
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `horarios_barberos` lista";

    // 8. DIAS BLOQUEADOS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `dias_bloqueados` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `barbero_id` INT UNSIGNED NOT NULL,
            `fecha` DATE NOT NULL,
            `motivo` VARCHAR(255) NOT NULL DEFAULT 'Día de descanso',
            `todo_el_dia` TINYINT(1) NOT NULL DEFAULT 1,
            `hora_inicio` TIME DEFAULT NULL,
            `hora_fin` TIME DEFAULT NULL,
            `creado_por` INT UNSIGNED DEFAULT NULL,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_bloqueos_fecha` (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `dias_bloqueados` lista";

    // 9. LOGS ACTIVIDAD
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `logs_actividad` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `usuario_id` INT UNSIGNED DEFAULT NULL,
            `accion` VARCHAR(50) NOT NULL,
            `tabla_afectada` VARCHAR(50) DEFAULT NULL,
            `registro_id` INT UNSIGNED DEFAULT NULL,
            `descripcion` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_logs_fecha` (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `logs_actividad` lista";

    // 10. CONFIGURACIÓN
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `configuracion` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `clave` VARCHAR(100) NOT NULL,
            `valor` TEXT DEFAULT NULL,
            `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_config_clave` (`clave`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `configuracion` lista";

    // 11. GALERIA IMAGENES
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `galeria_imagenes` lista";

    // 12. CODIGOS PROMOCIONALES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `codigos_promocionales` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `codigo` VARCHAR(50) NOT NULL,
            `tipo` ENUM('porcentaje', 'fijo') NOT NULL DEFAULT 'porcentaje',
            `valor` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
            `usos_maximos` INT NOT NULL DEFAULT 100,
            `usos_actuales` INT NOT NULL DEFAULT 0,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_expiracion` DATE DEFAULT NULL,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_cod_promo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `codigos_promocionales` lista";

    // 13. REFERIDOS
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `referidos` lista";

    // 14. NOTIFICACIONES PWA
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notificaciones_pwa` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `usuario_id` INT UNSIGNED DEFAULT NULL,
            `cliente_id` INT UNSIGNED DEFAULT NULL,
            `titulo` VARCHAR(200) NOT NULL,
            `mensaje` TEXT NOT NULL,
            `url` VARCHAR(500) DEFAULT NULL,
            `leido` TINYINT(1) DEFAULT 0,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `notificaciones_pwa` lista";

    // 15. INVENTARIO BARBERO
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inventario_barbero` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `barbero_id` INT NOT NULL,
            `sucursal_id` INT DEFAULT NULL,
            `producto` VARCHAR(255) NOT NULL,
            `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `unidad` VARCHAR(50) DEFAULT 'unidades',
            `precio` DECIMAL(10,2) DEFAULT 0.00,
            `descripcion` TEXT NULL,
            `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_barbero (`barbero_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `inventario_barbero` lista";

    // 16. VENTAS PRODUCTOS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ventas_productos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cita_id` INT NULL,
            `producto_id` INT NOT NULL,
            `cantidad` INT NOT NULL DEFAULT 1,
            `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `sucursal_id` INT NOT NULL DEFAULT 1,
            `usuario_id` INT NOT NULL,
            INDEX idx_usuario (`usuario_id`),
            INDEX idx_fecha (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `ventas_productos` lista";

    // 17. BLOQUEOS HORAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bloqueos_horas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `barbero_id` INT NOT NULL,
            `fecha` DATE NOT NULL,
            `hora_inicio` TIME NOT NULL,
            `hora_fin` TIME NOT NULL,
            `motivo` VARCHAR(255) DEFAULT 'Bloqueo temporal',
            `creado_por` INT NULL,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_barbero (`barbero_id`),
            INDEX idx_fecha (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `bloqueos_horas` lista";

    // 18. PUSH SUBSCRIPTIONS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `push_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cliente_id` INT NULL,
            `endpoint` TEXT NOT NULL,
            `p256dh` TEXT NULL,
            `auth` TEXT NULL,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cliente (`cliente_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $status[] = "✓ Tabla `push_subscriptions` lista";

    // 19. RESEÑAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `resenas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cliente_id` INT UNSIGNED NOT NULL,
            `barbero_id` INT UNSIGNED DEFAULT NULL,
            `servicio_id` INT UNSIGNED DEFAULT NULL,
            `calificacion` INT NOT NULL DEFAULT 5,
            `comentario` TEXT DEFAULT NULL,
            `visible` TINYINT(1) NOT NULL DEFAULT 0,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX idx_resena_barbero (`barbero_id`),
            INDEX idx_resena_visible (`visible`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `resenas` lista";

    // Reestablecer foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");


    // ==========================================
    // SEMBRAR DATOS INICIALES (SI ESTÁ VACÍO)
    // ==========================================

    // 1. Sucursal principal
    $checkSuc = $pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
    if ($checkSuc == 0) {
        $pdo->exec("
            INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `horario_apertura`, `horario_cierre`, `estado`, `mapa_url`)
            VALUES (1, 'KORTZEN Llano Chico', 'Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito', '+593 098 842 2770', '10:00:00', '20:00:00', 'activo', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec');
        ");
        $status[] = "✓ Sucursal 'KORTZEN Llano Chico' creada";
    }

    // 2. Administrador general
    $checkUser = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'admin@kortzen.com'")->fetchColumn();
    if ($checkUser == 0) {
        $adminPassHash = password_hash('Admin2026!', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `comision_porcentaje`, `activo`)
            VALUES ('Administrador', 'admin@kortzen.com', ?, 'admin', 1, '+593 098 842 2770', 100.00, 1)
        ");
        $stmt->execute([$adminPassHash]);
        $status[] = "✓ Usuario administrador creado: <strong>admin@kortzen.com</strong> (Contraseña: <code>Admin2026!</code>)";
    }

    // 3. Barbero Mateo Josué
    $checkBarber = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'mateo@kortzen.com'")->fetchColumn();
    if ($checkBarber == 0) {
        $barberPass = password_hash('Barbero2026!', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `bio`, `especialidades`, `comision_porcentaje`, `activo`)
            VALUES ('Mateo Josué', 'mateo@kortzen.com', ?, 'barbero', 1, '+593 098 842 2770', 'Master Barber especializado en cortes clásicos y modernos.', 'Fade, Barba, Perfilado', 50.00, 1)
        ");
        $stmt->execute([$barberPass]);
        $barberId = $pdo->lastInsertId();

        // Horarios lunes a domingo
        for ($d = 0; $d <= 6; $d++) {
            $pdo->prepare("INSERT IGNORE INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, '10:00:00', '20:00:00', 1)")->execute([$barberId, $d]);
        }
        $status[] = "✓ Barbero 'Mateo Josué' creado con sus horarios semanales";
    }

    // 4. Servicios iniciales
    $checkServ = $pdo->query("SELECT COUNT(*) FROM servicios")->fetchColumn();
    if ($checkServ == 0) {
        $pdo->exec("
            INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_minutos`, `sucursal_id`, `activo`) VALUES
            ('Corte Clásico', 'Corte tradicional de caballero con tijera o máquina y acabado profesional', 12.00, 30, 1, 1),
            ('Corte + Barba', 'Corte completo de cabello con perfilado y arreglo de barba', 18.00, 45, 1, 1),
            ('Barba Completa', 'Perfilado tradicional con navaja, toalla caliente y aceites hidratantes', 10.00, 30, 1, 1),
            ('Corte Degradado (Fade)', 'Degradado de máxima precisión con diseño personalizado y styling', 15.00, 40, 1, 1),
            ('Afeitado Tradicional', 'Afeitado clásico al ras con espuma caliente y bálsamo calmante', 12.00, 25, 1, 1),
            ('Tratamiento Spa & Facial', 'Limpieza profunda facial, vapor de ozono y exfoliación para caballeros', 20.00, 45, 1, 1),
            ('Corte VIP Premium', 'Servicio exclusivo con lavado capilar, masaje craneal, corte y peinado', 25.00, 60, 1, 1);
        ");
        $status[] = "✓ Catálogo de 7 servicios iniciales configurado";
    }

    // 5. Galería de fotos inicial
    $checkGal = $pdo->query("SELECT COUNT(*) FROM galeria_imagenes")->fetchColumn();
    if ($checkGal == 0) {
        $pdo->exec("
            INSERT INTO `galeria_imagenes` (`titulo`, `descripcion`, `imagen_url`, `categoria`, `sucursal_id`) VALUES
            ('Corte Fade Clásico', 'Degradado perfecto', '/assets/images/gallery-1.jpg', 'corte', 1),
            ('Perfilado de Barba', 'Acabado con navaja', '/assets/images/gallery-2.jpg', 'barba', 1),
            ('Tratamiento Facial', 'Spa masculino', '/assets/images/gallery-3.jpg', 'spa', 1),
            ('Nuestras Instalaciones', 'Espacio premium', '/assets/images/gallery-4.jpg', 'espacio', 1);
        ");
        $status[] = "✓ Muestras iniciales de galería añadidas";
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador de Base de Datos - KORTZEN</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0e0e0e; color: #FFFFFF; padding: 40px 20px; display: flex; justify-content: center; }
        .card { max-width: 680px; width: 100%; background: #161616; border: 1px solid #2a2a2a; border-radius: 16px; padding: 32px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        h1 { color: #D4AF37; margin-top: 0; font-size: 24px; border-bottom: 1px solid #2a2a2a; padding-bottom: 16px; }
        .log-list { list-style: none; padding: 0; margin: 20px 0; }
        .log-list li { padding: 10px 14px; margin-bottom: 8px; border-radius: 8px; background: rgba(255,255,255,0.03); border-left: 4px solid #2ECC71; font-size: 14px; }
        .error-box { background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #ff6b6b; padding: 16px; border-radius: 8px; margin: 20px 0; font-size: 14px; }
        .success-banner { background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ecc71; padding: 18px; border-radius: 8px; font-weight: 600; text-align: center; margin-top: 24px; }
        .btn-home { display: inline-block; background: #D4AF37; color: #000; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>💈 KORTZEN - Instalador de Base de Datos</h1>

        <?php if ($error): ?>
            <div class="error-box">
                <strong>❌ Error de instalación:</strong><br>
                <?= htmlspecialchars($error) ?><br><br>
                <em>Asegúrate de haber creado la base de datos en Hostinger con el nombre y usuario correctos antes de ejecutar este instalador.</em>
            </div>
        <?php else: ?>
            <ul class="log-list">
                <?php foreach ($status as $msg): ?>
                    <li><?= $msg ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="success-banner">
                🎉 ¡Base de datos instalada y configurada al 100%!
                <br><br>
                <a href="/login.php" class="btn-home">Ir al Panel de Administración</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
