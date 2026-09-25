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
            `foto` VARCHAR(500) DEFAULT NULL,
            `foto_url` VARCHAR(500) DEFAULT NULL,
            `bio` TEXT DEFAULT NULL,
            `biografia` TEXT DEFAULT NULL,
            `descripcion` TEXT DEFAULT NULL,
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
            `imagen` VARCHAR(500) DEFAULT NULL,
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

    // 1. Sucursales
    $checkSuc = $pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
    if ($checkSuc == 0) {
        $pdo->exec("
            INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `horario_apertura`, `horario_cierre`, `estado`, `mapa_url`, `activo`) VALUES
            (1, 'KORTZEN Llano Chico', 'Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito', '+593 098 842 2770', '00:00:00', '00:00:00', 'activo', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec', 1),
            (2, 'KORTZEN Laureles', 'Laureles', '+593 098 842 2770', '10:00:00', '20:00:00', 'proximamente', NULL, 1);
        ");
        $status[] = "✓ Sucursales 'KORTZEN Llano Chico' y 'KORTZEN Laureles' creadas";
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

    // 3. Barberos del Equipo (5 Barberos Profesionales)
    $barberosData = [
        [
            'nombre' => 'Mateo Josué',
            'email' => 'mateo@kortzen.com',
            'foto' => '/assets/images/barbers/mateo.jpg',
            'bio' => 'Master Barber y fundador con pasión por el arte del corte y estilo. Dedicado a ofrecer un servicio de máxima calidad.',
            'especialidades' => 'Corte, Ondulación, Barba, Cejas',
            'comision' => 100.00
        ],
        [
            'nombre' => 'Jeffrey Herrera',
            'email' => 'jherrera@kortzen.com',
            'foto' => '/assets/images/barbers/jeffrey.jpg',
            'bio' => 'Jeffrey es un barbero profesional, dedicado a ofrecer cortes modernos y clásicos con un trato cercano y de calidad.',
            'especialidades' => 'Corte, Barba, Ondulación',
            'comision' => 60.00
        ],
        [
            'nombre' => 'Joel Pinzón',
            'email' => 'jpinzon@kortzen.com',
            'foto' => '/assets/images/barbers/joel.jpg',
            'bio' => 'Más que un barbero, soy alguien que ama su arte y busca que cada cliente salga con estilo y seguridad.',
            'especialidades' => 'Corte, Barba, Cejas',
            'comision' => 80.00
        ],
        [
            'nombre' => 'Jonathan Valverde',
            'email' => 'jvalverde@kortzen.com',
            'foto' => '/assets/images/barbers/jonathan.jpg',
            'bio' => 'Especialista en degradados, visagismo y técnicas de corte contemporáneo con un acabado impecable.',
            'especialidades' => 'Corte, Degradados, Barba',
            'comision' => 50.00
        ],
        [
            'nombre' => 'Josh Quilumba',
            'email' => 'jquilumba@kortzen.com',
            'foto' => '/assets/images/barbers/josh.jpg',
            'bio' => 'Más que un corte, te ofrezco una experiencia completa con atención al detalle y resultados de calidad.',
            'especialidades' => 'Corte, Estilo Pro, Visagismo',
            'comision' => 50.00
        ]
    ];

    $barberPassDefault = password_hash('Barbero2026!', PASSWORD_BCRYPT);
    foreach ($barberosData as $b) {
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$b['email']]);
        $existingId = $check->fetchColumn();

        if (!$existingId) {
            $stmt = $pdo->prepare("
                INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `foto`, `foto_url`, `bio`, `biografia`, `especialidades`, `comision_porcentaje`, `activo`)
                VALUES (?, ?, ?, 'barbero', 1, '+593 098 842 2770', ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $b['nombre'], $b['email'], $barberPassDefault, 
                $b['foto'], $b['foto'], $b['bio'], $b['bio'], 
                $b['especialidades'], $b['comision']
            ]);
            $bId = $pdo->lastInsertId();

            for ($d = 0; $d <= 6; $d++) {
                $pdo->prepare("INSERT IGNORE INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, '10:00:00', '20:00:00', 1)")->execute([$bId, $d]);
            }
            $status[] = "✓ Barbero '{$b['nombre']}' registrado con horarios semanales";
        }
    }

    // 4. Categorías de Servicios
    $checkCats = $pdo->query("SELECT COUNT(*) FROM categorias_servicios")->fetchColumn();
    if ($checkCats == 0) {
        $pdo->exec("
            INSERT INTO `categorias_servicios` (`id`, `nombre`, `descripcion`, `icono`, `orden`, `activo`) VALUES
            (1, 'Corte', 'Cortes clásicos, degradados modernos y estilos de autor.', 'scissors', 1, 1),
            (2, 'Afeitado', 'Ritual clásico de afeitado al ras con espuma tibia y toalla caliente.', 'razor', 2, 1),
            (3, 'Barba', 'Perfilado, hidratación y cuidado especializado de barba.', 'beard', 3, 1),
            (4, 'Spa', 'Tratamientos faciales, exfoliación y renovación personal.', 'spa', 4, 1);
        ");
        $status[] = "✓ Categorías de servicios creadas";
    }

    // 5. Catálogo Completo de Servicios
    $checkServ = $pdo->query("SELECT COUNT(*) FROM servicios")->fetchColumn();
    if ($checkServ == 0) {
        $pdo->exec("
            INSERT INTO `servicios` (`id`, `nombre`, `descripcion`, `precio`, `duracion_minutos`, `categoria`, `orden`, `foto_url`, `imagen_url`, `imagen`, `destacado`, `que_incluye`, `beneficio_destacado`, `sucursal_id`, `activo`) VALUES
            (1, 'Corte Con Mateo', 'Una experiencia personalizada pensada para quienes buscan precisión, estilo y atención a cada detalle. Mateo adapta el corte a tus facciones, tipo de cabello y preferencias para conseguir un resultado limpio, moderno y con identidad propia, manteniendo siempre el sello de calidad de Kortzen.', 10.00, 60, 'Corte', 1, '/assets/images/services/corte-con-mateo.png', '/assets/images/services/corte-con-mateo.png', '/assets/images/services/corte-con-mateo.png', 1, 'Lavado capilar con shampoo premium\nAsesoría de visagismo\nCorte de precisión a máquina y tijera\nBebida de cortesía\nPeinado final con producto de fijación mate', 'Asesoría de visagismo incluida', 1, 1),
            (2, 'Corte de cabello Pro', 'Potencia tu imagen con un acabado profesional diseñado para resaltar tu estilo. En Kortzen trabajamos cada detalle del cabello, utilizando técnicas de peinado y definición que aportan forma, textura y presencia para lograr un look impecable, moderno y personalizado.', 8.00, 60, 'Corte', 2, '/assets/images/services/corte-pro.png', '/assets/images/services/corte-pro.png', '/assets/images/services/corte-pro.png', 1, 'Lavado capilar con shampoo premium\nAsesoría de visagismo\nCorte de precisión a máquina y tijera\nBebida de cortesía\nPeinado final con producto profesional', 'Estilo pro y definición', 1, 1),
            (3, 'Corte Y Barba', 'Renueva tu imagen con un servicio completo que combina corte de cabello y arreglo de barba. En Kortzen cuidamos cada detalle para lograr un estilo limpio, definido y equilibrado, adaptado a tus rasgos y personalidad.', 12.00, 60, 'Corte', 3, '/assets/images/services/corte-y-barba.png', '/assets/images/services/corte-y-barba.png', '/assets/images/services/corte-y-barba.png', 1, 'Lavado capilar con shampoo premium\nAsesoría de visagismo\nCorte de precisión a máquina y tijera\nRitual de barba\nBebida de cortesía\nPeinado con producto profesional', 'Combo integral más solicitado', 1, 1),
            (4, 'Afeitado Tradicional', 'Más que un afeitado, es un ritual de relajación. Combinamos la técnica maestra de la navaja libre con el confort de las toallas calientes para lograr un apurado perfecto y sin irritación.', 10.00, 30, 'Afeitado', 4, '/assets/images/services/afeitado-tradicional.png', '/assets/images/services/afeitado-tradicional.png', '/assets/images/services/afeitado-tradicional.png', 0, 'Espuma caliente batida a brocha\nAfeitado a navaja clásica al ras\nToalla fría de cierre de poros', 'Experiencia clásica relajante', 1, 1),
            (5, 'Barba Premium', 'El cuidado definitivo para tu rostro. Es un ritual de diseño y nutrición que combina el recorte preciso con el confort de las toallas calientes y aceites esenciales.', 5.00, 30, 'Barba', 5, '/assets/images/services/barba-premium.png', '/assets/images/services/barba-premium.png', '/assets/images/services/barba-premium.png', 0, 'Toalla caliente aromática\nPerfilado a navaja descartable\nBálsamo calmante e hidratación', 'Definición e hidratación profunda', 1, 1),
            (6, 'Cejas', 'El marco perfecto para tu mirada. No es solo depilación; es un servicio de limpieza y simetría diseñado para potenciar tus facciones sin perder la naturalidad.', 2.00, 10, 'Spa', 6, '/assets/images/services/cejas.png', '/assets/images/services/cejas.png', '/assets/images/services/cejas.png', 0, 'Definición de contorno con precisión\nLimpieza y simetría natural', 'Simetría facial impecable', 1, 1),
            (7, 'Limpieza Facial Completa', 'El reseteo profundo que tu piel necesita. Este tratamiento elimina impurezas y células muertas, renovando por completo la vitalidad de tu rostro con exfoliación y mascarilla.', 8.00, 30, 'Spa', 7, '/assets/images/services/limpieza-facial.png', '/assets/images/services/limpieza-facial.png', '/assets/images/services/limpieza-facial.png', 0, 'Vapor de ozono\nExfoliación con microgránulos\nMascarilla peel-off de carbón activado\nMasaje facial relajante', 'Piel limpia y revitalizada', 1, 1),
            (8, 'Ritual KORTZEN', 'Nuestra experiencia más completa y exclusiva: incluye limpieza facial profunda con extracción suave, masaje relajante, perfilado de cejas y acabado con lavado y peinado.', 15.00, 85, 'Spa', 8, '/assets/images/services/ritual-kortzen.png', '/assets/images/services/ritual-kortzen.png', '/assets/images/services/ritual-kortzen.png', 1, 'Limpieza facial profunda con extracción suave\nMasaje relajante\nPerfilado de cejas y nariz\nLavado y peinado profesional\nBebida de cortesía', 'Experiencia VIP integral', 1, 1),
            (9, 'Ondulación o Semi Ondulación', 'Transforma tu estilo con movimiento y volumen natural. Aplicamos técnicas avanzadas que respetan la salud de tu fibra capilar para un look moderno y texturizado.', 50.00, 180, 'Corte', 9, '/assets/images/services/ondulacion.png', '/assets/images/services/ondulacion.png', '/assets/images/services/ondulacion.png', 0, 'Técnicas avanzadas de ondulación\nTratamiento protector capilar\nAsesoría de cuidado y mantenimiento', 'Volumen y textura duradera', 1, 1);
        ");

        $pdo->exec("
            INSERT INTO `servicios_sucursales` (`servicio_id`, `sucursal_id`) VALUES 
            (1,1), (2,1), (3,1), (4,1), (5,1), (6,1), (7,1), (8,1), (9,1),
            (1,2), (2,2), (3,2), (4,2), (5,2), (6,2), (7,2), (8,2), (9,2);
        ");
        $status[] = "✓ Catálogo completo de 9 servicios asignado a sucursales";
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
