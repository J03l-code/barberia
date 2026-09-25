<?php
/**
 * KORTZEN / SISTEMA DE GESTIÓN - Instalador Automático de Base de Datos
 * Ejecuta este script desde el navegador para crear y poblar todas las tablas del sistema.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$status = [];
$error = null;

try {
    $pdo = getConnection();
    $status[] = "✓ Conexión establecida exitosamente con la base de datos: <strong>" . htmlspecialchars(DB_NAME) . "</strong>";

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

    // 3. USUARIOS_SUCURSALES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios_sucursales` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `usuario_id` INT UNSIGNED NOT NULL,
            `sucursal_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_usuario_sucursal` (`usuario_id`, `sucursal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `usuarios_sucursales` lista";

    // 4. CLIENTES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `clientes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nombre` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `password` VARCHAR(255) DEFAULT NULL,
            `telefono` VARCHAR(20) DEFAULT NULL,
            `fecha_nacimiento` DATE DEFAULT NULL,
            `notas` TEXT DEFAULT NULL,
            `notas_barbero` TEXT DEFAULT NULL,
            `estilo_buscado` VARCHAR(255) DEFAULT NULL,
            `ambiente_preferido` VARCHAR(255) DEFAULT NULL,
            `bebida_preferida` VARCHAR(255) DEFAULT NULL,
            `puntos` INT DEFAULT 0,
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
            INDEX `idx_clientes_telefono` (`telefono`),
            INDEX `idx_clientes_codigo_referido` (`codigo_referido`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `clientes` lista";

    // 5. CATEGORIAS_SERVICIOS
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

    // 6. SERVICIOS
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
            `que_incluye` TEXT NULL,
            `beneficio_destacado` VARCHAR(255) NULL,
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

    // 7. SERVICIOS_SUCURSALES
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

    // 8. SERVICIOS_BARBEROS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `servicios_barberos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `servicio_id` INT UNSIGNED NOT NULL,
            `barbero_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_servicio_barbero` (`servicio_id`, `barbero_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `servicios_barberos` lista";

    // 9. INVENTARIO
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

    // 10. INVENTARIO_BARBERO
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
            INDEX `idx_barbero_inv` (`barbero_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `inventario_barbero` lista";

    // 11. VENTAS_PRODUCTOS
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
            INDEX `idx_ventas_usuario` (`usuario_id`),
            INDEX `idx_ventas_fecha` (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `ventas_productos` lista";

    // 12. CITAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `citas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cliente_id` INT UNSIGNED NOT NULL,
            `servicio_id` INT UNSIGNED NOT NULL,
            `barbero_id` INT UNSIGNED DEFAULT NULL,
            `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `fecha_hora` DATETIME NOT NULL,
            `estado` ENUM('confirmada','completada','cancelada') NOT NULL DEFAULT 'confirmada',
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

    // 13. HORARIOS_BARBEROS
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

    // 14. DIAS_BLOQUEADOS
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

    // 15. BLOQUEOS_HORAS
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
            INDEX `idx_bh_barbero` (`barbero_id`),
            INDEX `idx_bh_fecha` (`fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `bloqueos_horas` lista";

    // 16. LOGS_ACTIVIDAD
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

    // 17. CONFIGURACION
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

    // 18. GALERIA_IMAGENES
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

    // 19. CODIGOS_PROMOCIONALES
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

    // 20. USOS_CODIGOS_PROMOCIONALES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usos_codigos_promocionales` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `codigo_id` INT UNSIGNED NOT NULL,
            `cliente_id` INT UNSIGNED NOT NULL,
            `cita_id` INT UNSIGNED DEFAULT NULL,
            `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `usos_codigos_promocionales` lista";

    // 21. REFERIDOS
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
            PRIMARY KEY (`id`),
            INDEX `idx_ref_referente` (`referente_id`),
            INDEX `idx_ref_cita` (`cita_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $status[] = "✓ Tabla `referidos` lista";

    // 22. NOTIFICACIONES_PWA
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

    // 23. PUSH_SUBSCRIPTIONS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `push_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cliente_id` INT NULL,
            `endpoint` TEXT NOT NULL,
            `p256dh` TEXT NULL,
            `auth` TEXT NULL,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_push_cliente` (`cliente_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $status[] = "✓ Tabla `push_subscriptions` lista";

    // 24. RESEÑAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `resenas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cliente_id` INT UNSIGNED NOT NULL,
            `barbero_id` INT UNSIGNED DEFAULT NULL,
            `servicio_id` INT UNSIGNED DEFAULT NULL,
            `calificacion` INT NOT NULL DEFAULT 5,
            `comentario` TEXT DEFAULT NULL,
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_resena_barbero` (`barbero_id`),
            INDEX `idx_resena_visible` (`visible`)
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
            INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `horario_apertura`, `horario_cierre`, `estado`, `mapa_url`, `activo`)
            VALUES (1, 'KORTZEN Llano Chico', 'Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito', '+593 098 842 2770', '10:00:00', '20:00:00', 'activo', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec', 1);
        ");
        $status[] = "✓ Sucursal 'KORTZEN Llano Chico' creada";
    }

    // 2. Administrador general
    $checkUser = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'admin@kortzen.com'")->fetchColumn();
    if ($checkUser == 0) {
        $adminPassHash = password_hash('Admin2026!', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `comision_porcentaje`, `activo`)
            VALUES ('Administrador General', 'admin@kortzen.com', ?, 'admin', 1, '+593 098 842 2770', 100.00, 1)
        ");
        $stmt->execute([$adminPassHash]);
        $status[] = "✓ Usuario administrador creado: <strong>admin@kortzen.com</strong> (Contraseña: <code>Admin2026!</code>)";
    }

    // 3. Barbero Mateo Josué
    $checkBarber = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'mateo@kortzen.com'")->fetchColumn();
    if ($checkBarber == 0) {
        $barberPass = password_hash('Barbero2026!', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `foto_url`, `bio`, `biografia`, `especialidades`, `comision_porcentaje`, `activo`)
            VALUES ('Mateo Josué', 'mateo@kortzen.com', ?, 'barbero', 1, '+593 098 842 2770', '/assets/images/barber-mateo.jpg', 'Master Barber con más de 7 años de trayectoria.', 'Master Barber enfocado en la excelencia.', 'Fade de Precisión, Visagismo, Cuidado de Barba', 50.00, 1)
        ");
        $stmt->execute([$barberPass]);
        $barberId = $pdo->lastInsertId();

        // Horarios lunes a domingo
        for ($d = 0; $d <= 6; $d++) {
            $pdo->prepare("INSERT IGNORE INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, '10:00:00', '20:00:00', 1)")->execute([$barberId, $d]);
        }
        $status[] = "✓ Barbero 'Mateo Josué' creado con sus horarios semanales (Contraseña: <code>Barbero2026!</code>)";
    }

    // 4. Categorías de Servicios
    $checkCats = $pdo->query("SELECT COUNT(*) FROM categorias_servicios")->fetchColumn();
    if ($checkCats == 0) {
        $pdo->exec("
            INSERT INTO `categorias_servicios` (`id`, `nombre`, `descripcion`, `icono`, `orden`, `activo`) VALUES
            (1, 'Corte & Estilo', 'Cortes de autor, degradados de precisión y peinado', 'scissors', 1, 1),
            (2, 'Cuidado de Barba', 'Perfilado, hidratación y toalla caliente', 'beard', 2, 1),
            (3, 'Afeitado Tradicional', 'Afeitado a navaja libre con espuma tibia', 'razor', 3, 1),
            (4, 'Tratamientos Spa', 'Exfoliación, mascarilla de carbón y vapor facial', 'spa', 4, 1);
        ");
        $status[] = "✓ Categorías de servicios creadas";
    }

    // 5. Servicios iniciales
    $checkServ = $pdo->query("SELECT COUNT(*) FROM servicios")->fetchColumn();
    if ($checkServ == 0) {
        $pdo->exec("
            INSERT INTO `servicios` (`id`, `nombre`, `descripcion`, `precio`, `duracion_minutos`, `categoria`, `orden`, `foto_url`, `imagen_url`, `destacado`, `que_incluye`, `beneficio_destacado`, `sucursal_id`, `activo`) VALUES
            (1, 'Corte Con Mateo', 'Una experiencia personalizada pensada para quienes buscan precisión, estilo y atención a cada detalle.', 10.00, 60, 'Corte & Estilo', 1, '/assets/images/service-classic-cut.jpg', '/assets/images/service-classic-cut.jpg', 1, 'Lavado capilar con shampoo premium\nAsesoría de visagismo\nCorte de precisión a máquina y tijera\nBebida de cortesía\nPeinado final con producto de fijación mate', 'Asesoría de visagismo incluida', 1, 1),
            (2, 'Corte + Barba', 'Corte completo con técnica tijera y máquina más perfilado, recorte e hidratación de barba con toalla caliente.', 15.00, 60, 'Corte & Estilo', 2, '/assets/images/service-beard-trim.jpg', '/assets/images/service-beard-trim.jpg', 1, 'Lavado capilar con shampoo premium\nCorte y diseño según morfología facial\nPerfilado y toalla caliente para barba\nAceite hidratante y bálsamo', 'Combo integral de máxima distinción', 1, 1),
            (3, 'Arreglo de Barba', 'Perfilado preciso con navaja libre, toalla caliente y aceites esenciales nutritivos.', 7.00, 30, 'Cuidado de Barba', 3, '/assets/images/service-traditional-shave.jpg', '/assets/images/service-traditional-shave.jpg', 0, 'Toalla caliente aromática\nPerfilado a navaja descartable\nBálsamo calmante e hidratación', 'Cuidado y definición para tu barba', 1, 1),
            (4, 'Afeitado Tradicional', 'Ritual clásico de afeitado al ras con espuma caliente, doble toalla y loción refrescante.', 10.00, 35, 'Afeitado Tradicional', 4, '/assets/images/service-traditional-shave.jpg', '/assets/images/service-traditional-shave.jpg', 0, 'Espuma caliente batida a brocha\nAfeitado a navaja clásica al ras\nToalla fría de cierre de poros', 'Experiencia tradicional de relajación', 1, 1),
            (5, 'Tratamiento Spa Facial', 'Limpieza profunda con vapor de ozono, exfoliación y mascarilla negra purificante.', 12.00, 45, 'Tratamientos Spa', 5, '/assets/images/service-spa-facial.jpg', '/assets/images/service-spa-facial.jpg', 0, 'Vapor de ozono\nExfoliación con microgránulos\nMascarilla peel-off de carbón activado\nMasaje facial relajante', 'Renovación y frescura para tu rostro', 1, 1);
        ");

        $pdo->exec("
            INSERT INTO `servicios_sucursales` (`servicio_id`, `sucursal_id`) VALUES (1,1), (2,1), (3,1), (4,1), (5,1);
        ");
        $status[] = "✓ Catálogo completo de servicios asignado";
    }

    // 6. Configuración de Negocio
    $checkConfig = $pdo->query("SELECT COUNT(*) FROM configuracion")->fetchColumn();
    if ($checkConfig == 0) {
        $pdo->exec("
            INSERT INTO `configuracion` (`clave`, `valor`) VALUES
            ('nombre_negocio', 'KORTZEN Barbería'),
            ('telefono_principal', '+593 098 842 2770'),
            ('email_contacto', 'contacto@kortzen.com'),
            ('direccion_principal', 'Calle 17 de septiembre, Llano Chico, Quito, Ecuador'),
            ('moneda_simbolo', '$'),
            ('intervalo_citas_minutos', '30'),
            ('cancelacion_horas_limite', '2'),
            ('puntos_por_dolar', '1'),
            ('descuento_referido_amigo', '2.00'),
            ('descuento_referido_dueno', '3.00'),
            ('comision_barbero_default', '50.00'),
            ('comision_fin_semana_default', '50.00'),
            ('comision_productos_default', '10.00'),
            ('exigir_checkbox_reserva', '0');
        ");
        $status[] = "✓ Parámetros globales de configuración guardados";
    }

    // 7. Inventario inicial
    $checkInv = $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn();
    if ($checkInv == 0) {
        $pdo->exec("
            INSERT INTO `inventario` (`id`, `producto`, `cantidad`, `precio`, `stock_minimo`, `sucursal_id`, `unidad`, `categoria`) VALUES
            (1, 'Cera Modeladora Mate 100g', 24, 12.00, 5, 1, 'unidades', 'Peinado'),
            (2, 'Aceite para Barba Esencial 30ml', 18, 14.50, 4, 1, 'frascos', 'Cuidado de Barba'),
            (3, 'Shampoo Fortificante Anticaída 250ml', 15, 16.00, 3, 1, 'botellas', 'Capilar'),
            (4, 'Bálsamo Post-Afeitado Hidratante', 20, 11.00, 5, 1, 'tubos', 'Afeitado');
        ");
        $status[] = "✓ Stock inicial de productos cargado";
    }

    // 8. Cupones de bienvenida
    $checkPromo = $pdo->query("SELECT COUNT(*) FROM codigos_promocionales")->fetchColumn();
    if ($checkPromo == 0) {
        $pdo->exec("
            INSERT INTO `codigos_promocionales` (`codigo`, `tipo`, `valor`, `usos_maximos`, `usos_actuales`, `activo`, `fecha_expiracion`) VALUES
            ('BIENVENIDO10', 'porcentaje', 10.00, 500, 0, 1, '2027-12-31'),
            ('KORTZENPREMIUM', 'fijo', 3.00, 200, 0, 1, '2027-12-31');
        ");
        $status[] = "✓ Códigos promocionales iniciales activos";
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
    <title>Instalador de Base de Datos - KORTZEN / MAUS BARBER</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0e0e0e; color: #FFFFFF; padding: 40px 20px; display: flex; justify-content: center; }
        .card { max-width: 680px; width: 100%; background: #161616; border: 1px solid #2a2a2a; border-radius: 16px; padding: 32px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        h1 { color: #D4AF37; margin-top: 0; font-size: 24px; border-bottom: 1px solid #2a2a2a; padding-bottom: 16px; }
        .log-list { list-style: none; padding: 0; margin: 20px 0; }
        .log-list li { padding: 10px 14px; margin-bottom: 8px; border-radius: 8px; background: rgba(255,255,255,0.03); border-left: 4px solid #2ECC71; font-size: 14px; }
        .error-box { background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #ff6b6b; padding: 16px; border-radius: 8px; margin: 20px 0; font-size: 14px; line-height: 1.5; }
        .success-banner { background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ecc71; padding: 18px; border-radius: 8px; font-weight: 600; text-align: center; margin-top: 24px; }
        .btn-home { display: inline-block; background: #D4AF37; color: #000; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>💈 Instalador de Base de Datos Maestro</h1>

        <?php if ($error): ?>
            <div class="error-box">
                <strong>❌ Error de instalación:</strong><br>
                <?= htmlspecialchars($error) ?><br><br>
                <em>Asegúrate de que la base de datos y usuario creados en tu nuevo hosting coincidan con las credenciales en <code>config.php</code> o tu archivo <code>.env</code>.</em>
            </div>
        <?php else: ?>
            <ul class="log-list">
                <?php foreach ($status as $msg): ?>
                    <li><?= $msg ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="success-banner">
                🎉 ¡Base de datos instalada y poblada al 100%!
                <br><br>
                <strong>Acceso Administrador:</strong> admin@kortzen.com / Admin2026!<br>
                <strong>Acceso Barbero:</strong> mateo@kortzen.com / Barbero2026!
                <br><br>
                <a href="/login.php" class="btn-home">Ir al Panel de Administración</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
