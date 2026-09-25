-- ============================================================
-- KORTZEN / MAUS BARBERSHOP - BASE DE DATOS MAESTRA INTEGRAL
-- Compatible con MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Codificación: UTF-8 (utf8mb4_unicode_ci)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '-05:00';

-- ------------------------------------------------------------
-- 1. ESTRUCTURA COMPLETA DE TABLAS
-- ------------------------------------------------------------

-- Tabla 1: sucursales
DROP TABLE IF EXISTS `sucursales`;
CREATE TABLE `sucursales` (
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

-- Tabla 2: usuarios (Administradores, Admin Local, Barberos)
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
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
    INDEX `idx_usuarios_sucursal` (`sucursal_id`),
    CONSTRAINT `fk_usuarios_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 3: usuarios_sucursales (Multi-sucursal para administradores locales)
DROP TABLE IF EXISTS `usuarios_sucursales`;
CREATE TABLE `usuarios_sucursales` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `sucursal_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuario_sucursal` (`usuario_id`, `sucursal_id`),
    CONSTRAINT `fk_us_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_us_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 4: clientes
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
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

-- Tabla 5: categorias_servicios
DROP TABLE IF EXISTS `categorias_servicios`;
CREATE TABLE `categorias_servicios` (
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

-- Tabla 6: servicios
DROP TABLE IF EXISTS `servicios`;
CREATE TABLE `servicios` (
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

-- Tabla 7: servicios_sucursales
DROP TABLE IF EXISTS `servicios_sucursales`;
CREATE TABLE `servicios_sucursales` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `servicio_id` INT UNSIGNED NOT NULL,
    `sucursal_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_servicio_sucursal` (`servicio_id`, `sucursal_id`),
    CONSTRAINT `fk_ss_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ss_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 8: servicios_barberos
DROP TABLE IF EXISTS `servicios_barberos`;
CREATE TABLE `servicios_barberos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `servicio_id` INT UNSIGNED NOT NULL,
    `barbero_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_servicio_barbero` (`servicio_id`, `barbero_id`),
    CONSTRAINT `fk_sb_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sb_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 9: inventario (Stock de Productos)
DROP TABLE IF EXISTS `inventario`;
CREATE TABLE `inventario` (
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
    INDEX `idx_inventario_sucursal` (`sucursal_id`),
    CONSTRAINT `fk_inventario_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 10: inventario_barbero (Consumo y asignación de productos por barbero)
DROP TABLE IF EXISTS `inventario_barbero`;
CREATE TABLE `inventario_barbero` (
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

-- Tabla 11: ventas_productos (Ventas registradas por barberos)
DROP TABLE IF EXISTS `ventas_productos`;
CREATE TABLE `ventas_productos` (
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

-- Tabla 12: citas (Agenda y Reservas)
DROP TABLE IF EXISTS `citas`;
CREATE TABLE `citas` (
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
    INDEX `idx_citas_sucursal` (`sucursal_id`),
    CONSTRAINT `fk_citas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_citas_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_citas_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_citas_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 13: horarios_barberos (Jornadas semanales)
DROP TABLE IF EXISTS `horarios_barberos`;
CREATE TABLE `horarios_barberos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barbero_id` INT UNSIGNED NOT NULL,
    `dia_semana` TINYINT UNSIGNED NOT NULL COMMENT '0=Domingo, 1=Lunes, ... 6=Sabado',
    `hora_inicio` TIME NOT NULL DEFAULT '10:00:00',
    `hora_fin` TIME NOT NULL DEFAULT '20:00:00',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_barbero_dia` (`barbero_id`, `dia_semana`),
    CONSTRAINT `fk_hb_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 14: dias_bloqueados (Días no laborables o descansos)
DROP TABLE IF EXISTS `dias_bloqueados`;
CREATE TABLE `dias_bloqueados` (
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
    INDEX `idx_bloqueos_fecha` (`fecha`),
    CONSTRAINT `fk_db_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 15: bloqueos_horas (Bloqueos parciales de tiempo)
DROP TABLE IF EXISTS `bloqueos_horas`;
CREATE TABLE `bloqueos_horas` (
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

-- Tabla 16: logs_actividad (Auditoría del sistema)
DROP TABLE IF EXISTS `logs_actividad`;
CREATE TABLE `logs_actividad` (
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

-- Tabla 17: configuracion (Parámetros y personalización)
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave` VARCHAR(100) NOT NULL,
    `valor` TEXT DEFAULT NULL,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 18: galeria_imagenes (Galería pública y de cortes)
DROP TABLE IF EXISTS `galeria_imagenes`;
CREATE TABLE `galeria_imagenes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `imagen_url` VARCHAR(500) NOT NULL,
    `categoria` VARCHAR(50) NOT NULL DEFAULT 'corte',
    `sucursal_id` INT UNSIGNED DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 19: codigos_promocionales (Cupones y promociones)
DROP TABLE IF EXISTS `codigos_promocionales`;
CREATE TABLE `codigos_promocionales` (
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

-- Tabla 20: usos_codigos_promocionales
DROP TABLE IF EXISTS `usos_codigos_promocionales`;
CREATE TABLE `usos_codigos_promocionales` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `cita_id` INT UNSIGNED DEFAULT NULL,
    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ucp_codigo` FOREIGN KEY (`codigo_id`) REFERENCES `codigos_promocionales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ucp_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 21: referidos (Sistema de recomendación y recompensas)
DROP TABLE IF EXISTS `referidos`;
CREATE TABLE `referidos` (
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

-- Tabla 22: notificaciones_pwa (Bandeja interna de notificaciones)
DROP TABLE IF EXISTS `notificaciones_pwa`;
CREATE TABLE `notificaciones_pwa` (
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

-- Tabla 23: push_subscriptions (Suscripciones Web Push)
DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NULL,
    `endpoint` TEXT NOT NULL,
    `p256dh` TEXT NULL,
    `auth` TEXT NULL,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_push_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla 24: resenas (Reseñas y calificaciones de clientes)
DROP TABLE IF EXISTS `resenas`;
CREATE TABLE `resenas` (
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


-- ------------------------------------------------------------
-- 2. DATOS INICIALES Y SEMILLA COMPLETA
-- ------------------------------------------------------------

-- 1. Sucursales
INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `horario_apertura`, `horario_cierre`, `estado`, `mapa_url`, `activo`) VALUES
(1, 'KORTZEN Llano Chico', 'Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito', '+593 098 842 2770', '00:00:00', '00:00:00', 'activo', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec', 1),
(2, 'KORTZEN Laureles', 'Laureles', '+593 098 842 2770', '10:00:00', '20:00:00', 'proximamente', NULL, 1);

-- 2. Categorías de Servicios
INSERT INTO `categorias_servicios` (`id`, `nombre`, `descripcion`, `icono`, `orden`, `activo`) VALUES
(1, 'Corte', 'Cortes clásicos, degradados modernos y estilos de autor.', 'scissors', 1, 1),
(2, 'Afeitado', 'Ritual clásico de afeitado al ras con espuma tibia y toalla caliente.', 'razor', 2, 1),
(3, 'Barba', 'Perfilado, hidratación y cuidado especializado de barba.', 'beard', 3, 1),
(4, 'Spa', 'Tratamientos faciales, exfoliación y renovación personal.', 'spa', 4, 1);

-- 3. Catálogo de Servicios
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

-- 4. Asignación de Servicios a Sucursales
INSERT INTO `servicios_sucursales` (`servicio_id`, `sucursal_id`) VALUES
(1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1), (9, 1),
(1, 2), (2, 2), (3, 2), (4, 2), (5, 2), (6, 2), (7, 2), (8, 2), (9, 2);

-- 5. Usuarios Administradores y Barberos (Equipo Profesional)
-- Contraseñas por defecto: Admin2026! / Barbero2026!
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `sucursal_id`, `telefono`, `foto`, `foto_url`, `bio`, `biografia`, `especialidades`, `comision_porcentaje`, `comision_fin_semana`, `comision_productos`, `almuerzo_inicio`, `almuerzo_fin`, `almuerzo_activo`, `activo`) VALUES
(1, 'Administrador General', 'admin@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'admin', 1, '+593 098 842 2770', NULL, NULL, 'Administrador principal de la plataforma.', 'Administrador del sistema.', 'Gestión, Finanzas, Operaciones', 100.00, 100.00, 100.00, '13:00:00', '14:00:00', 0, 1),
(2, 'Mateo Josué', 'mateo@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'barbero', 1, '+593 098 842 2770', '/assets/images/barbers/mateo.jpg', '/assets/images/barbers/mateo.jpg', 'Master Barber y fundador con pasión por el arte del corte y estilo.', 'Master Barber y fundador con pasión por el arte del corte y estilo. Dedicado a ofrecer un servicio de máxima calidad.', 'Corte, Ondulación, Barba, Cejas', 100.00, 50.00, 10.00, '13:00:00', '14:00:00', 1, 1),
(3, 'Jeffrey Herrera', 'jherrera@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'barbero', 1, '+593 098 842 2770', '/assets/images/barbers/jeffrey.jpg', '/assets/images/barbers/jeffrey.jpg', 'Jeffrey es un barbero profesional, dedicado a ofrecer cortes modernos y clásicos con un trato cercano y de calidad.', 'Jeffrey es un barbero profesional, dedicado a ofrecer cortes modernos y clásicos con un trato cercano y de calidad.', 'Corte, Barba, Ondulación', 60.00, 50.00, 10.00, '13:00:00', '14:00:00', 1, 1),
(4, 'Joel Pinzón', 'jpinzon@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'barbero', 1, '+593 098 842 2770', '/assets/images/barbers/joel.jpg', '/assets/images/barbers/joel.jpg', 'Más que un barbero, soy alguien que ama su arte y busca que cada cliente salga con estilo y seguridad.', 'Más que un barbero, soy alguien que ama su arte y busca que cada cliente salga con estilo y seguridad.', 'Corte, Barba, Cejas', 80.00, 50.00, 10.00, '13:00:00', '14:00:00', 1, 1),
(5, 'Jonathan Valverde', 'jvalverde@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'barbero', 1, '+593 098 842 2770', '/assets/images/barbers/jonathan.jpg', '/assets/images/barbers/jonathan.jpg', 'Especialista en degradados, visagismo y técnicas de corte contemporáneo con un acabado impecable.', 'Especialista en degradados, visagismo y técnicas de corte contemporáneo con un acabado impecable.', 'Corte, Degradados, Barba', 50.00, 50.00, 10.00, '13:00:00', '14:00:00', 1, 1),
(6, 'Josh Quilumba', 'jquilumba@kortzen.com', '$2y$10$wTlsD9yXmZ7R.f2X2kE8f.5w7R1sF3J0J09XmZ7R.f2X2kE8f.5w7', 'barbero', 1, '+593 098 842 2770', '/assets/images/barbers/josh.jpg', '/assets/images/barbers/josh.jpg', 'Más que un corte, te ofrezco una experiencia completa con atención al detalle y resultados de calidad.', 'Más que un corte, te ofrezco una experiencia completa con atención al detalle y resultados de calidad.', 'Corte, Estilo Pro, Visagismo', 50.00, 50.00, 10.00, '13:00:00', '14:00:00', 1, 1);

-- 6. Asignación de Horarios Semanales para Todos los Barberos (Lunes a Domingo 10:00 - 20:00)
INSERT INTO `horarios_barberos` (`barbero_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES
(2, 0, '10:00:00', '20:00:00', 1), (2, 1, '10:00:00', '20:00:00', 1), (2, 2, '10:00:00', '20:00:00', 1), (2, 3, '10:00:00', '20:00:00', 1), (2, 4, '10:00:00', '20:00:00', 1), (2, 5, '10:00:00', '20:00:00', 1), (2, 6, '10:00:00', '20:00:00', 1),
(3, 0, '10:00:00', '20:00:00', 1), (3, 1, '10:00:00', '20:00:00', 1), (3, 2, '10:00:00', '20:00:00', 1), (3, 3, '10:00:00', '20:00:00', 1), (3, 4, '10:00:00', '20:00:00', 1), (3, 5, '10:00:00', '20:00:00', 1), (3, 6, '10:00:00', '20:00:00', 1),
(4, 0, '10:00:00', '20:00:00', 1), (4, 1, '10:00:00', '20:00:00', 1), (4, 2, '10:00:00', '20:00:00', 1), (4, 3, '10:00:00', '20:00:00', 1), (4, 4, '10:00:00', '20:00:00', 1), (4, 5, '10:00:00', '20:00:00', 1), (4, 6, '10:00:00', '20:00:00', 1),
(5, 0, '10:00:00', '20:00:00', 1), (5, 1, '10:00:00', '20:00:00', 1), (5, 2, '10:00:00', '20:00:00', 1), (5, 3, '10:00:00', '20:00:00', 1), (5, 4, '10:00:00', '20:00:00', 1), (5, 5, '10:00:00', '20:00:00', 1), (5, 6, '10:00:00', '20:00:00', 1),
(6, 0, '10:00:00', '20:00:00', 1), (6, 1, '10:00:00', '20:00:00', 1), (6, 2, '10:00:00', '20:00:00', 1), (6, 3, '10:00:00', '20:00:00', 1), (6, 4, '10:00:00', '20:00:00', 1), (6, 5, '10:00:00', '20:00:00', 1), (6, 6, '10:00:00', '20:00:00', 1);

-- 7. Configuración Global del Negocio
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

-- 8. Inventario Inicial
INSERT INTO `inventario` (`id`, `producto`, `cantidad`, `precio`, `stock_minimo`, `sucursal_id`, `unidad`, `categoria`) VALUES
(1, 'Cera Modeladora Mate 100g', 24, 12.00, 5, 1, 'unidades', 'Peinado'),
(2, 'Aceite para Barba Esencial 30ml', 18, 14.50, 4, 1, 'frascos', 'Cuidado de Barba'),
(3, 'Shampoo Fortificante Anticaída 250ml', 15, 16.00, 3, 1, 'botellas', 'Capilar'),
(4, 'Bálsamo Post-Afeitado Hidratante', 20, 11.00, 5, 1, 'tubos', 'Afeitado');

-- 9. Códigos Promocionales de Bienvenida
INSERT INTO `codigos_promocionales` (`codigo`, `tipo`, `valor`, `usos_maximos`, `usos_actuales`, `activo`, `fecha_expiracion`) VALUES
('BIENVENIDO10', 'porcentaje', 10.00, 500, 0, 1, '2027-12-31'),
('KORTZENPREMIUM', 'fijo', 3.00, 200, 0, 1, '2027-12-31');

-- 10. Galería de Muestra
INSERT INTO `galeria_imagenes` (`titulo`, `descripcion`, `imagen_url`, `categoria`, `sucursal_id`) VALUES
('Degradado Skin Fade', 'Precisión milimétrica y transición perfecta', '/assets/images/services/corte-con-mateo.png', 'corte', 1),
('Ritual Barba Clásica', 'Perfilado con toalla caliente y navaja libre', '/assets/images/services/barba-premium.png', 'barba', 1),
('Spa Facial Masculino', 'Limpieza profunda con vapor de ozono', '/assets/images/services/limpieza-facial.png', 'spa', 1),
('Nuestras Instalaciones', 'Ambiente distinguido para el caballero actual', '/assets/images/services/ritual-kortzen.png', 'espacio', 1);

SET FOREIGN_KEY_CHECKS = 1;

