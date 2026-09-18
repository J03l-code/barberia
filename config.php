<?php
/**
 * Configuración de Conexión a Base de Datos
 * Sistema de Gestión de Barberías
 * 
 * IMPORTANTE: Modifica estos valores con las credenciales de tu base de datos en Hostinger
 */

// Cargar variables de entorno desde .env si existe en el servidor
if (file_exists(__DIR__ . '/.env')) {
    $env_lines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($env_lines) {
        foreach ($env_lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $val) = explode('=', $line, 2) + [null, null];
            if ($key && $val !== null) {
                $k = trim($key);
                $v = trim($val);
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

// Configuración de la Base de Datos
$db_host = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? '195.35.61.92' : 'localhost';
define('DB_HOST', getenv('DB_HOST') ?: $db_host);
define('DB_NAME', getenv('DB_NAME') ?: 'u434851126_kortzen');
define('DB_USER', getenv('DB_USER') ?: 'u434851126_kortzenusr');
define('DB_PASS', getenv('DB_PASS') ?: 'Kortzen2026!');
define('DB_CHARSET', 'utf8mb4');

// Configuración de Zona Horaria (Ecuador)
date_default_timezone_set('America/Guayaquil');

// Configuración de la aplicación
define('SITE_URL', getenv('SITE_URL') ?: 'https://kortzen.com');
define('SITE_NAME', getenv('SITE_NAME') ?: 'KORTZEN Barbería');

// Configuración de Google reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6Ldm9oUtAAAAALygbin3zWA6sx15vHe7DeJ0-Rop');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6Ldm9oUtAAAAAItTxMMq49FFc2Gl76ppbDsJfBIU');

// Clave Secreta para ejecución de Tareas Programadas (Cron Jobs)
define('CRON_SECRET', getenv('CRON_SECRET') ?: 'KortzenCron2026_9b8a7c6e5f4d');

/**
 * Genera un token HMAC criptográficamente seguro para inicio de sesión PWA
 */
function generarPwaToken($clienteId, $email) {
    return hash_hmac('sha256', $clienteId . '|' . $email, DB_PASS);
}

/**
 * Valida un token HMAC para PWA
 */
function validarPwaToken($clienteId, $email, $token) {
    if (empty($clienteId) || empty($email) || empty($token)) return false;
    $expected = generarPwaToken($clienteId, $email);
    return hash_equals($expected, $token);
}

// Configuración optimizada de sesiones (Evita saturación de inodes en servidor)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 2592000); // 30 días en cookie de navegador
ini_set('session.gc_maxlifetime', 86400);    // 24 horas de inactividad para archivos en disco
ini_set('session.gc_probability', 1);        // Activar recolección de basura automática
ini_set('session.gc_divisor', 100);          // 1% de probabilidad de limpieza en cada carga

// Iniciar sesión con parámetros seguros
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 2592000,
        'path' => '/',
        'domain' => '',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Función para obtener la conexión PDO
 * @return PDO|null
 */
function getConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Asegurar zona horaria Ecuador (-05:00) en MySQL
            try {
                $pdo->exec("SET time_zone = '-05:00'");
            } catch (Exception $e_tz) {}

            // Auto-migraciones silenciosas y seguras (nunca bloquean la conexión)
            try {
                $colsStmt = $pdo->query("SHOW COLUMNS FROM usuarios");
                $colsU = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

                if (!empty($colsU)) {
                    // Intentar crear columnas faltantes de forma segura e individual
                    if (!in_array('biografia', $colsU) && !in_array('bio', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN bio TEXT NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('biografia', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN biografia TEXT NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('bio', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN bio TEXT NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('telefono', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(30) NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('foto_url', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_url VARCHAR(500) NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('especialidades', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN especialidades VARCHAR(255) NULL"); } catch (Throwable $e) {}
                    }
                    if (!in_array('comision_porcentaje', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_porcentaje DECIMAL(5,2) DEFAULT 50.00"); } catch (Throwable $e) {}
                    }
                    if (!in_array('comision_fin_semana', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_fin_semana DECIMAL(5,2) DEFAULT 50.00"); } catch (Throwable $e) {}
                    }
                    if (!in_array('comision_productos', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_productos DECIMAL(5,2) DEFAULT 10.00"); } catch (Throwable $e) {}
                    }
                    if (!in_array('almuerzo_inicio', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_inicio TIME DEFAULT '13:00:00'"); } catch (Throwable $e) {}
                    }
                    if (!in_array('almuerzo_fin', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_fin TIME DEFAULT '14:00:00'"); } catch (Throwable $e) {}
                    }
                    if (!in_array('almuerzo_activo', $colsU)) {
                        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_activo TINYINT DEFAULT 1"); } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e_u_cols) {}

            // Auto-migración para clientes (columna password para clientes sin Google)
            try {
                $colsCStmt = $pdo->query("SHOW COLUMNS FROM clientes");
                $colsC = $colsCStmt ? $colsCStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                if (!empty($colsC) && !in_array('password', $colsC)) {
                    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN password VARCHAR(255) NULL AFTER email"); } catch (Throwable $e) {}
                }
            } catch (Throwable $e_c_cols) {}

            if (empty($_SESSION['kortzen_schema_migrated'])) {
                $_SESSION['kortzen_schema_migrated'] = true;

                try {
                    $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
                } catch (Throwable $e_prop) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS servicios_sucursales (
                            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            servicio_id INT UNSIGNED NOT NULL,
                            sucursal_id INT UNSIGNED NOT NULL,
                            UNIQUE KEY uk_servicio_sucursal (servicio_id, sucursal_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Throwable $e_ss) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS inventario_barbero (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            barbero_id INT NOT NULL,
                            sucursal_id INT DEFAULT NULL,
                            producto VARCHAR(255) NOT NULL,
                            cantidad DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            unidad VARCHAR(50) DEFAULT 'unidades',
                            precio DECIMAL(10,2) DEFAULT 0.00,
                            descripcion TEXT NULL,
                            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_barbero (barbero_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Throwable $e_invb) {}

                try {
                    $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_2h_enviado TINYINT(1) DEFAULT 0");
                } catch (Throwable $e_r2h) {}

                try {
                    $pdo->exec("ALTER TABLE citas ADD COLUMN asistencia_confirmada TINYINT(1) DEFAULT 0");
                } catch (Throwable $e_asist) {}

                try {
                    $pdo->exec("ALTER TABLE servicios ADD COLUMN barbero_id INT UNSIGNED NULL DEFAULT NULL");
                } catch (Throwable $e_sbarb) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS servicios_barberos (
                            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            servicio_id INT UNSIGNED NOT NULL,
                            barbero_id INT UNSIGNED NOT NULL,
                            UNIQUE KEY uk_servicio_barbero (servicio_id, barbero_id),
                            INDEX idx_servicio (servicio_id),
                            INDEX idx_barbero (barbero_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (Throwable $e_sb) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS push_subscriptions (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            cliente_id INT NULL,
                            endpoint TEXT NOT NULL,
                            p256dh TEXT NULL,
                            auth TEXT NULL,
                            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_cliente (cliente_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Throwable $e_psub) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS usuarios_sucursales (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            usuario_id INT NOT NULL,
                            sucursal_id INT NOT NULL,
                            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uk_usuario_sucursal (usuario_id, sucursal_id),
                            INDEX idx_usuario (usuario_id),
                            INDEX idx_sucursal (sucursal_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Throwable $e_usuc) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `resenas` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `cliente_nombre` VARCHAR(100) NOT NULL DEFAULT 'Cliente',
                            `comentario` TEXT NOT NULL,
                            `calificacion` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                            `fecha` DATE DEFAULT CURRENT_DATE,
                            `visible` TINYINT(1) NOT NULL DEFAULT 1,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            INDEX idx_resena_visible (`visible`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (Throwable $e_res) {}

                try {
                    $colsRStmt = $pdo->query("SHOW COLUMNS FROM resenas");
                    $colsR = $colsRStmt ? $colsRStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                    if (!empty($colsR)) {
                        if (!in_array('cliente_nombre', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN cliente_nombre VARCHAR(100) NOT NULL DEFAULT 'Cliente'"); } catch (Throwable $e) {}
                        }
                        if (!in_array('comentario', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN comentario TEXT NULL"); } catch (Throwable $e) {}
                        }
                        if (!in_array('calificacion', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN calificacion TINYINT UNSIGNED NOT NULL DEFAULT 5"); } catch (Throwable $e) {}
                        }
                        if (!in_array('fecha', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN fecha DATE DEFAULT CURRENT_DATE"); } catch (Throwable $e) {}
                        }
                        if (!in_array('visible', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
                        }
                        if (!in_array('created_at', $colsR)) {
                            try { $pdo->exec("ALTER TABLE resenas ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
                        }
                    }
                } catch (Throwable $e_r_cols) {}

                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS referidos (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            referente_id INT UNSIGNED NULL,
                            referido_id INT UNSIGNED NULL,
                            codigo_usado VARCHAR(50) NULL,
                            cita_id INT UNSIGNED NULL,
                            descuento_aplicado DECIMAL(10,2) DEFAULT 0.00,
                            puntos_otorgados INT DEFAULT 0,
                            estado ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'completado',
                            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_referente (referente_id),
                            INDEX idx_referido (referido_id),
                            INDEX idx_cita (cita_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Throwable $e_ref) {}
            }



        } catch (PDOException $e) {
            // Log del error (en producción, usa error_log)
            error_log("Error de conexión a la base de datos: " . $e->getMessage());

            // Si es una petición API o AJAX, devolver JSON
            $reqUri = $_SERVER['REQUEST_URI'] ?? '';
            $isApiResult = (strpos($reqUri, '/api/') !== false) ||
                (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isApiResult) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error de conexión a base de datos', 'error' => $e->getMessage()]);
                exit;
            }

            // Mostrar mensaje amigable al usuario (HTML)
            die("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border: 2px solid #dc3545; border-radius: 8px; background: #f8d7da; color: #721c24;'>
                    <h2>⚠️ Error de Conexión a la Base de Datos</h2>
                    <p><strong>No se pudo conectar a la base de datos.</strong></p>
                    <p>Por favor verifica:</p>
                    <ul>
                        <li>Que el servidor MySQL esté funcionando</li>
                        <li>Que las credenciales en <code>config.php</code> sean correctas</li>
                        <li>Que la base de datos '<strong>" . DB_NAME . "</strong>' exista</li>
                        <li>Que el usuario '<strong>" . DB_USER . "</strong>' tenga permisos</li>
                    </ul>
                    <hr>
                    <small><strong>Error técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</small>
                </div>
            ");
        }
    }

    return $pdo;
}

/**
 * Función helper para ejecutar consultas SELECT
 * @param string $sql
 * @param array $params
 * @return array
 */
function query($sql, $params = [])
{
    $pdo = getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Función helper para ejecutar consultas INSERT/UPDATE/DELETE
 * @param string $sql
 * @param array $params
 * @return bool
 */
function execute($sql, $params = [])
{
    $pdo = getConnection();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Asegura la existencia y contenido inicial de la tabla categorias_servicios
 */
function asegurarTablaCategorias($pdo = null)
{
    if (!$pdo) $pdo = getConnection();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `categorias_servicios` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default categories if empty
        $count = $pdo->query("SELECT COUNT(*) FROM categorias_servicios")->fetchColumn();
        if ($count == 0) {
            $defaultCats = [
                ['nombre' => 'Corte', 'descripcion' => 'Cortes clásicos, degradados modernos y estilos de autor.', 'orden' => 1],
                ['nombre' => 'Afeitado', 'descripcion' => 'Ritual clásico de afeitado a navaja libre con toalla caliente.', 'orden' => 2],
                ['nombre' => 'Barba', 'descripcion' => 'Diseño, perfilado, recorte y tratamientos de hidratación para barba.', 'orden' => 3],
                ['nombre' => 'Spa', 'descripcion' => 'Tratamientos faciales, exfoliación y relajación profunda.', 'orden' => 4],
                ['nombre' => 'Otros', 'descripcion' => 'Servicios adicionales y paquetes especiales.', 'orden' => 5],
            ];
            $stmt = $pdo->prepare("INSERT IGNORE INTO categorias_servicios (nombre, descripcion, orden, activo) VALUES (?, ?, ?, 1)");
            foreach ($defaultCats as $dc) {
                $stmt->execute([$dc['nombre'], $dc['descripcion'], $dc['orden']]);
            }

            // Also import any existing categories from servicios table
            try {
                $existing = $pdo->query("SELECT DISTINCT categoria FROM servicios WHERE categoria IS NOT NULL AND TRIM(categoria) != ''")->fetchAll(PDO::FETCH_COLUMN);
                $orderOffset = 10;
                foreach ($existing as $exCat) {
                    $exCat = trim($exCat);
                    $stmt->execute([$exCat, 'Servicios de categoría ' . $exCat, $orderOffset++]);
                }
            } catch (Throwable $e) {}
        }

        // Auto-reparar columnas en tabla servicios si no existen
        try {
            $colsServicios = $pdo->query("SHOW COLUMNS FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('foto_url', $colsServicios)) {
                $pdo->exec("ALTER TABLE servicios ADD COLUMN foto_url VARCHAR(500) DEFAULT NULL");
            }
            if (!in_array('imagen_url', $colsServicios)) {
                $pdo->exec("ALTER TABLE servicios ADD COLUMN imagen_url VARCHAR(500) DEFAULT NULL");
            }
            if (!in_array('categoria', $colsServicios)) {
                $pdo->exec("ALTER TABLE servicios ADD COLUMN categoria VARCHAR(50) NOT NULL DEFAULT 'General'");
            }
            if (!in_array('orden', $colsServicios)) {
                $pdo->exec("ALTER TABLE servicios ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER categoria");
                try {
                    $pdo->exec("ALTER TABLE servicios ADD INDEX idx_servicio_categoria_orden (categoria, orden)");
                } catch (Throwable $eIdx) {}
            }
            if (!in_array('que_incluye', $colsServicios)) {
                $pdo->exec("ALTER TABLE servicios ADD COLUMN que_incluye TEXT DEFAULT NULL AFTER descripcion");
            }

            // Rellenar valores sugeridos para que_incluye en servicios existentes que no lo tengan
            try {
                $pdo->exec("UPDATE servicios SET que_incluye = '• Lavado capilar con shampoo premium\n• Asesoría de visagismo\n• Corte de precisión a máquina y tijera\n• Perfilado de contornos a navaja\n• Peinado final con producto de fijación mate' WHERE (que_incluye IS NULL OR TRIM(que_incluye) = '') AND (LOWER(nombre) LIKE '%corte%' OR LOWER(categoria) LIKE '%corte%')");
                $pdo->exec("UPDATE servicios SET que_incluye = '• Ritual con toalla caliente aromatizada\n• Aceites esenciales pre-afeitado\n• Afeitado a navaja libre tradicional\n• Toalla fría para cerrar poros\n• Loción aftershave hidratante y refrescante' WHERE (que_incluye IS NULL OR TRIM(que_incluye) = '') AND (LOWER(nombre) LIKE '%afeita%' OR LOWER(categoria) LIKE '%afeita%')");
                $pdo->exec("UPDATE servicios SET que_incluye = '• Lavado y exfoliación de barba\n• Recorte y diseño según morfología\n• Perfilado de líneas a navaja\n• Toalla caliente relajante\n• Aceite nutritivo y bálsamo con aroma premium' WHERE (que_incluye IS NULL OR TRIM(que_incluye) = '') AND (LOWER(nombre) LIKE '%barba%' OR LOWER(categoria) LIKE '%barba%')");
                $pdo->exec("UPDATE servicios SET que_incluye = '• Limpieza facial profunda\n• Exfoliación de impurezas y células muertas\n• Vapor ozono para apertura de poros\n• Mascarilla negra o nutritiva\n• Toalla fría y masaje facial revitalizante' WHERE (que_incluye IS NULL OR TRIM(que_incluye) = '') AND (LOWER(nombre) LIKE '%facial%' OR LOWER(nombre) LIKE '%spa%' OR LOWER(categoria) LIKE '%spa%')");
            } catch (Throwable $e_seed) {}
        } catch (Throwable $e) {}
    } catch (Throwable $e) {
        error_log("Error in asegurarTablaCategorias: " . $e->getMessage());
    }
}

/**
 * Obtener listado de categorías de servicios
 */
function getCategoriasServicios($pdo = null, $onlyActive = true)
{
    if (!$pdo) $pdo = getConnection();
    asegurarTablaCategorias($pdo);
    try {
        $sql = "SELECT cs.*, (SELECT COUNT(*) FROM servicios s WHERE s.categoria = cs.nombre) as total_servicios 
                FROM categorias_servicios cs";
        if ($onlyActive) {
            $sql .= " WHERE cs.activo = 1";
        }
        $sql .= " ORDER BY cs.orden ASC, cs.nombre ASC";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Error in getCategoriasServicios: " . $e->getMessage());
        return [
            ['id' => 1, 'nombre' => 'Corte', 'descripcion' => 'Corte & Estilo', 'orden' => 1, 'activo' => 1, 'total_servicios' => 0],
            ['id' => 2, 'nombre' => 'Afeitado', 'descripcion' => 'Afeitado Tradicional', 'orden' => 2, 'activo' => 1, 'total_servicios' => 0],
            ['id' => 3, 'nombre' => 'Barba', 'descripcion' => 'Cuidado de Barba', 'orden' => 3, 'activo' => 1, 'total_servicios' => 0],
            ['id' => 4, 'nombre' => 'Spa', 'descripcion' => 'Tratamientos Spa', 'orden' => 4, 'activo' => 1, 'total_servicios' => 0],
            ['id' => 5, 'nombre' => 'Otros', 'descripcion' => 'Otros Servicios', 'orden' => 5, 'activo' => 1, 'total_servicios' => 0],
        ];
    }
}

/**
 * Obtener configuraciones del sistema desde la base de datos
 */
function getSystemConfigs($pdo = null)
{
    if (!$pdo) $pdo = getConnection();
    $configs = [
        'puntos_por_corte' => '100',
        'puntos_por_referido' => '200',
        'descuento_referido_amigo' => '2.00',
        'descuento_referente' => '2.00',
        'puntos_nivel_plata' => '500',
        'puntos_nivel_oro' => '1500',
        'puntos_nivel_vip' => '3000',
        'politica_reserva_titulo' => 'POLÍTICA DE RESERVAS',
        'politica_reserva_texto' => "• Si no puede llegar a su cita, informar con al menos 1 hora de anticipación.\n• Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.\n• Pasados los 15 minutos de retraso, la cita podrá ser reprogramada para no afectar los turnos siguientes.\n• Cuidamos tu tiempo y el de los demás caballeros.",
        'politica_reserva_check_texto' => 'He leído y acepto la política de reserva y condiciones de puntualidad.',
        'politica_reserva_requiere_check' => '1',
        'smtp_host' => 'smtp.hostinger.com',
        'smtp_port' => '465',
        'smtp_user' => 'info@kortzen.com',
        'smtp_pass' => 'Kortzen2026!'
    ];

    try {
        $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $configs[$r['clave']] = $r['valor'];
            }
        }
    } catch (Throwable $e) {}

    return $configs;
}

/**
 * Verificar si el usuario está autenticado
 * @return bool
 */
function isLoggedIn()
{
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // Auto-restaurar sesión de Barbero/Staff desde cookie persistente (365 días)
    if (!empty($_COOKIE['kortzen_pwa_user_id'])) {
        $userId = intval($_COOKIE['kortzen_pwa_user_id']);
        if ($userId > 0) {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT id, nombre, email, rol, sucursal_id FROM usuarios WHERE id = ?");
                $stmt->execute([$userId]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['user_nombre'] = $u['nombre'];
                    $_SESSION['user_email'] = $u['email'];
                    $_SESSION['user_rol'] = $u['rol'];
                    $_SESSION['sucursal_id'] = $u['sucursal_id'];
                    return true;
                }
            } catch (Exception $e) {}
        }
    }

    return false;
}

/**
 * Redirigir si no está autenticado
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obtener el usuario actual de la sesión
 * @return array|null
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    $sql = "SELECT u.*, s.nombre as sucursal_nombre 
            FROM usuarios u 
            LEFT JOIN sucursales s ON u.sucursal_id = s.id 
            WHERE u.id = ?";

    $result = query($sql, [$_SESSION['user_id']]);
    return $result[0] ?? null;
}

/**
 * Sanitizar entrada de usuario
 * @param string $input
 * @return string
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 * @param string $email
 * @return bool
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar token CSRF
 * @return string
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Asegurar que la tabla logs_actividad exista y tenga todas sus columnas
 */
function asegurarTablaLogs()
{
    static $asegurado = false;
    if ($asegurado) return;
    $asegurado = true;

    if (!empty($_SESSION['kortzen_logs_migrated'])) return;
    $_SESSION['kortzen_logs_migrated'] = true;

    try {
        $pdo = getConnection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs_actividad (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                cliente_id INT NULL,
                accion VARCHAR(50) NOT NULL DEFAULT 'INFO',
                tabla_afectada VARCHAR(50) NOT NULL DEFAULT 'sistema',
                registro_id INT NULL,
                descripcion TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $colsStmt = $pdo->query("SHOW COLUMNS FROM logs_actividad");
        $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        if (!in_array('usuario_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN usuario_id INT NULL");
        }
        if (!in_array('cliente_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN cliente_id INT NULL");
        }
        if (!in_array('accion', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN accion VARCHAR(50) NOT NULL DEFAULT 'INFO'");
        }
        if (!in_array('tabla_afectada', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN tabla_afectada VARCHAR(50) NOT NULL DEFAULT 'sistema'");
        }
        if (!in_array('registro_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN registro_id INT NULL");
        }
        if (!in_array('descripcion', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN descripcion TEXT NOT NULL");
        }
        if (!in_array('ip_address', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN ip_address VARCHAR(45) NULL");
        }
        if (!in_array('fecha_hora', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
    } catch (Exception $e) {
        error_log("Error al asegurar tabla de logs: " . $e->getMessage());
    }
}

/**
 * Registrar actividad en logs del sistema
 */
function registrarLog($accion, $tabla, $registro_id = 0, $descripcion = '')
{
    try {
        asegurarTablaLogs();
        $pdo = getConnection();

        $userId = $_SESSION['user_id'] ?? null;
        $clienteId = $_SESSION['cliente_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sql = "INSERT INTO logs_actividad (usuario_id, cliente_id, accion, tabla_afectada, registro_id, descripcion, ip_address, fecha_hora) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId ? intval($userId) : null,
            $clienteId ? intval($clienteId) : null,
            strtoupper(trim($accion)),
            trim($tabla),
            $registro_id ? intval($registro_id) : null,
            trim($descripcion),
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}

/**
 * Rate Limiter Helper (Protección Anti-Spam y Brute-Force por IP)
 * 
 * @param string $key Identificador de la zona (ej. 'login', 'api', 'reservas')
 * @param int $maxRequests Máximo de solicitudes permitidas en la ventana de tiempo
 * @param int $periodSeconds Tamaño de la ventana de tiempo en segundos
 * @return bool true si está dentro del límite, false si excedió
 */
function checkRateLimit($key = 'global', $maxRequests = 60, $periodSeconds = 60) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'ratelimit_' . md5($key . '_' . $ip);
    $now = time();

    if (!isset($_SESSION[$rateKey]) || !is_array($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = [ $now ];
        return true;
    }

    // Filtrar solicitudes dentro de la ventana de tiempo activa
    $_SESSION[$rateKey] = array_values(array_filter($_SESSION[$rateKey], function($timestamp) use ($now, $periodSeconds) {
        return ($now - $timestamp) < $periodSeconds;
    }));

    if (count($_SESSION[$rateKey]) >= $maxRequests) {
        return false;
    }

    $_SESSION[$rateKey][] = $now;
    return true;
}

/**
 * Aplica Rate Limit estricto deteniendo la ejecución con HTTP 429 si se excede el límite
 */
function enforceRateLimit($key = 'global', $maxRequests = 60, $periodSeconds = 60, $customMessage = null) {
    if (!checkRateLimit($key, $maxRequests, $periodSeconds)) {
        $msg = $customMessage ?: 'Demasiadas solicitudes en poco tiempo. Por favor, espera un momento antes de volver a intentarlo.';
        
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $isJson = (strpos($reqUri, '/api/') !== false) ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') !== false);

        http_response_code(429);
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg, 'message' => $msg]);
            exit;
        } else {
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Límite Excedido - KORTZEN</title><style>body{font-family:sans-serif;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;box-sizing:border-box;text-align:center}.box{background:#1e1e1e;padding:30px;border-radius:14px;border:1px solid #333;max-width:420px;box-shadow:0 10px 30px rgba(0,0,0,0.5)}h2{color:#f59e0b;margin-top:0;font-size:1.4rem}p{color:#ccc;font-size:0.95rem;line-height:1.5}.btn{display:inline-block;margin-top:18px;padding:12px 24px;background:#c9a96e;color:#000;text-decoration:none;font-weight:bold;border-radius:8px}</style></head><body><div class="box"><h2>⏳ Límite de Solicitudes Alcanzado</h2><p>' . htmlspecialchars($msg) . '</p><a href="javascript:location.reload()" class="btn">Reintentar</a></div></body></html>';
            exit;
        }
    }
}

/**
 * Verificar si un cliente está autenticado (Google OAuth)
 * @return bool
 */
function isClienteLoggedIn()
{
    if (isset($_SESSION['cliente_logged_in']) && $_SESSION['cliente_logged_in'] === true) {
        return true;
    }

    // Auto-restaurar sesión persistente de Cliente PWA de forma segura (Token firmado)
    if (!empty($_COOKIE['kortzen_pwa_token'])) {
        $rawToken = $_COOKIE['kortzen_pwa_token'];
        if (strpos($rawToken, ':') !== false) {
            list($cIdStr, $tokenHash) = explode(':', $rawToken, 2);
            $clientId = intval($cIdStr);
            if ($clientId > 0 && !empty($tokenHash)) {
                try {
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("SELECT id, nombre, email, foto_perfil, google_id FROM clientes WHERE id = ?");
                    $stmt->execute([$clientId]);
                    $c = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($c && validarPwaToken($c['id'], $c['email'], $tokenHash)) {
                        $_SESSION['cliente_logged_in'] = true;
                        $_SESSION['cliente_id'] = $c['id'];
                        $_SESSION['cliente_nombre'] = $c['nombre'];
                        $_SESSION['cliente_email'] = $c['email'];
                        $_SESSION['cliente_foto'] = $c['foto_perfil'] ?? null;
                        $_SESSION['cliente_google_id'] = $c['google_id'] ?? null;
                        return true;
                    }
                } catch (Exception $e) {}
            }
        }
    }

    return false;
}

/**
 * Obtener datos del cliente actual
 * @return array|null
 */
function getCurrentCliente()
{
    if (!isClienteLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['cliente_id'] ?? null,
        'nombre' => $_SESSION['cliente_nombre'] ?? 'Cliente',
        'email' => $_SESSION['cliente_email'] ?? '',
        'foto' => $_SESSION['cliente_foto'] ?? null,
        'google_id' => $_SESSION['cliente_google_id'] ?? null
    ];
}

/**
 * Obtener el estado de autenticación para JavaScript (JSON)
 * @return string JSON con el estado de autenticación
 */
function getAuthStateJSON()
{
    $state = [
        'isLoggedIn' => isClienteLoggedIn(),
        'user' => null
    ];

    if (isClienteLoggedIn()) {
        $state['user'] = [
            'nombre' => $_SESSION['cliente_nombre'] ?? 'Cliente',
            'email' => $_SESSION['cliente_email'] ?? '',
            'foto' => $_SESSION['cliente_foto'] ?? null
        ];
    }

    return json_encode($state);
}

/**
 * Detectar si el usuario está accediendo desde un dispositivo móvil o PWA
 * @return bool
 */
function isMobileDevice()
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/(android|iphone|ipad|ipod|blackberry|windows phone|opera mini|mobile)/i', $userAgent);
}

// ============================================================
// SISTEMA DE PERMISOS POR ROL
// ============================================================

/**
 * Obtener el rol del usuario actual
 * @return string|null
 */
function getCurrentUserRole()
{
    return $_SESSION['user_rol'] ?? null;
}

/**
 * Verificar si el usuario es Admin Técnico
 * @return bool
 */
function isAdminTecnico()
{
    return getCurrentUserRole() === 'admin';
}

/**
 * Verificar si el usuario es Admin de Locales
 * @return bool
 */
function isAdminLocal()
{
    return getCurrentUserRole() === 'admin_local';
}

/**
 * Verificar si el usuario es Barbero
 * @return bool
 */
function isBarbero()
{
    return getCurrentUserRole() === 'barbero';
}

/**
 * Obtener los IDs de sucursales a las que tiene acceso el usuario actual
 * Si es admin técnico, retorna todas las sucursales
 * Si es admin_local, retorna array con los IDs de sucursales asignadas
 * Si es barbero, retorna array con su sucursal_id
 * @param int|null $userId
 * @return array<int>
 */
function getUsuarioSucursalesIds($userId = null)
{
    if ($userId === null) {
        if (!isLoggedIn()) return [];
        $userId = $_SESSION['user_id'] ?? 0;
    }

    $userId = intval($userId);
    if ($userId <= 0) return [];

    $pdo = getConnection();

    try {
        $stmtU = $pdo->prepare("SELECT id, rol, sucursal_id FROM usuarios WHERE id = ?");
        $stmtU->execute([$userId]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$u) return [];

        // Admin técnico tiene acceso global a todas las sucursales
        if ($u['rol'] === 'admin') {
            $all = $pdo->query("SELECT id FROM sucursales WHERE activo = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $all);
        }

        // Buscar en tabla usuarios_sucursales (Multi-sucursales)
        $stmtSuc = $pdo->prepare("SELECT sucursal_id FROM usuarios_sucursales WHERE usuario_id = ?");
        $stmtSuc->execute([$userId]);
        $assigned = $stmtSuc->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($assigned)) {
            return array_values(array_unique(array_map('intval', $assigned)));
        }

        // Fallback a sucursal_id directo
        if (!empty($u['sucursal_id'])) {
            return [intval($u['sucursal_id'])];
        }
    } catch (Exception $e) {}

    return [];
}

/**
 * Obtener las sucursales completas a las que tiene acceso el usuario
 * @param int|null $userId
 * @return array
 */
function getUsuarioSucursales($userId = null)
{
    $sucIds = getUsuarioSucursalesIds($userId);
    if (empty($sucIds)) return [];

    $pdo = getConnection();
    $inPlaceholders = implode(',', array_fill(0, count($sucIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM sucursales WHERE id IN ($inPlaceholders) AND activo = 1 ORDER BY nombre ASC");
    $stmt->execute($sucIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Verificar si puede gestionar usuarios (crear/editar/eliminar)
 * Solo Admin Técnico
 * @return bool
 */
function canManageUsers()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver usuarios
 * Admin Técnico y Admin Local
 * @return bool
 */
function canViewUsers()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar horarios de barberos
 * Admin Técnico y Admin Local
 * @return bool
 */
function canManageSchedules()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar inventario
 * Admin Técnico y Admin Local
 * @return bool
 */
function canManageInventory()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar sucursales (crear/eliminar)
 * Solo Admin Técnico
 * @return bool
 */
function canManageBranches()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede gestionar servicios
 * Solo Admin Técnico
 * @return bool
 */
function canManageServices()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede gestionar galería
 * Solo Admin Técnico
 * @return bool
 */
function canManageGallery()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede gestionar reseñas
 * Solo Admin Técnico
 * @return bool
 */
function canManageReviews()
{
    return isAdminTecnico() || isAdminLocal() || in_array(getCurrentUserRole(), ['admin', 'admin_local', 'superadmin', 'administrador']);
}

/**
 * Verificar si puede gestionar configuración
 * Solo Admin Técnico
 * @return bool
 */
function canManageSettings()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver logs del sistema
 * Solo Admin Técnico
 * @return bool
 */
function canViewLogs()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver reportes y estadísticas
 * Admin Técnico y Admin Local
 * @return bool
 */
function canViewReports()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Obtener nombre del rol para mostrar
 * @param string $rol
 * @return string
 */
function getRolDisplayName($rol)
{
    $nombres = [
        'admin' => 'Admin Técnico',
        'admin_local' => 'Admin Locales',
        'barbero' => 'Barbero'
    ];
    return $nombres[$rol] ?? ucfirst($rol);
}

/**
 * Requerir permiso específico, redirige si no tiene acceso
 * @param bool $hasPermission
 * @param string $redirectTo
 */
function requirePermission($hasPermission, $redirectTo = 'dashboard.php')
{
    if (!$hasPermission) {
        header('Location: ' . $redirectTo . '?error=' . urlencode('No tienes permiso para acceder a esta sección.'));
        exit;
    }
}

/**
 * Formatear número de teléfono para WhatsApp (Ecuador)
 * @param string $phone
 * @return string
 */
function formatPhoneForWhatsapp($phone)
{
    // 1. Eliminar todo lo que no sea número
    $clean = preg_replace('/[^0-9]/', '', $phone);

    // 2. Si está vacío, retornar vacío
    if (empty($clean))
        return '';

    // 3. Lógica para Ecuador (empezar con 09 -> 5939)
    // Si tiene 10 dígitos y empieza con 0, reemplazar 0 por 593
    if (strlen($clean) === 10 && substr($clean, 0, 1) === '0') {
        return '593' . substr($clean, 1);
    }

    // 4. Si ya empieza con 593 (ej: 593988...) se deja igual
    // 5. Si es de 9 dígitos (sin el 0 inicial), agregar 593
    if (strlen($clean) === 9) {
        return '593' . $clean;
    }

    return $clean;
}

/**
 * Verificación y disparo automático asíncrono de recordatorios de citas 2h antes
 * Se ejecuta de fondo máximo 1 vez cada 60 segundos por sesión.
 */
function checkCronRecordatorios2hAuto() {
    if (defined('IS_CRON_EXECUTION') || (isset($_GET['action']) && $_GET['action'] === 'cron')) return;
    
    $lastCheck = $_SESSION['last_cron_check_2h'] ?? 0;
    if (time() - $lastCheck < 60) return;
    $_SESSION['last_cron_check_2h'] = time();

    try {
        $host = $_SERVER['HTTP_HOST'] ?? 'kortzen.com';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $port = $isHttps ? 443 : 80;
        $scheme = $isHttps ? 'ssl://' : '';

        $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 1);
        if ($fp) {
            stream_set_timeout($fp, 1);
            $out = "GET /api/cron_recordatorios_2h.php HTTP/1.1\r\n";
            $out .= "Host: {$host}\r\n";
            $out .= "User-Agent: KortzenAutoCron/1.0\r\n";
            $out .= "Connection: Close\r\n\r\n";
            @fwrite($fp, $out);
            @fclose($fp);
        }
    } catch (Exception $e) {}
}

// Auto-ejecución pasiva de fondo al cargar cualquier página
if (!empty($_SERVER['HTTP_HOST'])) {
    checkCronRecordatorios2hAuto();
}
