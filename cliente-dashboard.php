<?php
/**
 * Dashboard del Cliente - Native App UI
 * Interfaz nativa para PWA y clientes autenticados
 */

require_once 'config.php';

// Verificar si el cliente está logueado (con auto-restauración persistente 365 días)
if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$nombre = $_SESSION['cliente_nombre'] ?? 'Cliente';
$email = $_SESSION['cliente_email'] ?? '';
$foto = $_SESSION['cliente_foto'] ?? null;
$cliente_id = $_SESSION['cliente_id'] ?? null;

// Primer nombre para saludo personalizado
$primer_nombre = htmlspecialchars(explode(' ', trim($nombre))[0]);

// Iniciales para el avatar
$nombres_arr = explode(' ', trim($nombre));
$iniciales = strtoupper(substr($nombres_arr[0], 0, 1) . (isset($nombres_arr[1]) ? substr($nombres_arr[1], 0, 1) : ''));

// Obtener próxima cita del cliente y código de referido
$proxima_cita = null;
$codigo_referido = '';
$puntos_por_referido_cfg = 200;
$descuento_referido_amigo_cfg = '2.00';

if ($cliente_id) {
    try {
        $pdo = getConnection();

        // Cargar configuraciones
        $stmtCfgs = $pdo->query("SELECT clave, valor FROM configuracion");
        $cfgsList = $stmtCfgs->fetchAll(PDO::FETCH_KEY_PAIR);
        $puntos_por_referido_cfg = intval($cfgsList['puntos_por_referido'] ?? 200);
        $descuento_referido_amigo_cfg = number_format(floatval($cfgsList['descuento_referido_amigo'] ?? 2.00), 2);

        // Código de referido
        $stmtCod = $pdo->prepare("SELECT codigo_referido FROM clientes WHERE id = ?");
        $stmtCod->execute([$cliente_id]);
        $codigo_referido = $stmtCod->fetchColumn();

        // Auto-limpiar prefijo KORTZEN de la BD
        if (!empty($codigo_referido) && (strpos($codigo_referido, 'KORTZEN-') !== false || strpos($codigo_referido, 'KORTZEN') !== false)) {
            $codigo_referido = str_replace(['KORTZEN-', 'KORTZEN'], '', $codigo_referido);
            $stmtUpdCod = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");
            $stmtUpdCod->execute([$codigo_referido, $cliente_id]);
        }

        if (empty($codigo_referido)) {
            $nombreLimpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $primer_nombre));
            if (empty($nombreLimpio)) $nombreLimpio = 'CLIENTE';
            $codigo_referido = $nombreLimpio . rand(100, 999);
            
            try {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN codigo_referido VARCHAR(30) UNIQUE NULL AFTER puntos");
            } catch (Exception $ex) {}
            
            $stmtUpdCod = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");
            $stmtUpdCod->execute([$codigo_referido, $cliente_id]);
        }

        // Obtener email y teléfono del cliente para vinculación garantizada
        $stmtCliInfo = $pdo->prepare("SELECT email, telefono FROM clientes WHERE id = ?");
        $stmtCliInfo->execute([$cliente_id]);
        $cliInfo = $stmtCliInfo->fetch(PDO::FETCH_ASSOC);
        $cliEmail = trim($cliInfo['email'] ?? '');
        $cliTelefono = trim($cliInfo['telefono'] ?? '');

        // Buscar TODAS las citas reservadas del cliente (activas, de hoy o futuras, por ID, email o teléfono)
        $sqlCitasCli = "
            SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre, suc.nombre as sucursal_nombre
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios b ON c.barbero_id = b.id
            LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
            WHERE (
                c.cliente_id = ? 
                OR (? != '' AND c.cliente_id IN (SELECT id FROM clientes WHERE email = ? AND email != ''))
                OR (? != '' AND c.cliente_id IN (SELECT id FROM clientes WHERE telefono = ? AND telefono != ''))
            )
            AND c.estado IN ('pendiente', 'confirmada')
            AND (DATE(c.fecha_hora) >= CURDATE() OR c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 4 HOUR))
            ORDER BY c.fecha_hora ASC
        ";
        $stmtCitasCli = $pdo->prepare($sqlCitasCli);
        $stmtCitasCli->execute([$cliente_id, $cliEmail, $cliEmail, $cliTelefono, $cliTelefono]);
        $citas_reservadas_todas = $stmtCitasCli->fetchAll(PDO::FETCH_ASSOC);

        $proxima_cita = !empty($citas_reservadas_todas) ? $citas_reservadas_todas[0] : null;
    } catch (Exception $e) {
        // Fallback silencioso
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>KORTZEN - App</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=50">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js?v=26200" defer></script>
    <script src="/js/calendar-helper.js?v=1"></script>
    <script src="/js/branch-selector.js?v=26000"></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>

        <!-- Native Top Bar (Screen 1) -->
        <header class="pwa-header">
            <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-header__btn" title="Soporte WhatsApp">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
            </a>
            <div class="pwa-header__center" onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" title="Cambiar Sucursal">
                <div class="pwa-header__logo">KORTZEN</div>
                <div class="pwa-header__branch-pill">
                    <span class="pwa-header__branch-dot"></span>
                    <span data-branch-dynamic="name" class="pwa-header__branch-text">KORTZEN Llano Chico</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>
            <button class="pwa-header__btn" id="pwaBellBtn" title="Notificaciones Push" onclick="activarNotificacionesPWA()">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
        </header>

        <!-- Hero Banner Card -->
        <div class="pwa-hero-card">
            <h1 class="pwa-hero-card__title">Bienvenido,<br><?php echo $primer_nombre; ?></h1>
            <p class="pwa-hero-card__subtitle">Precisión. Estilo. Confianza.</p>
        </div>

        <!-- Accesos Rápidos -->
        <div class="pwa-section-title">Accesos rápidos</div>
        <div class="pwa-quick-actions">
            <a href="reservar.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Reservar</span>
            </a>
            <a href="pwa-barberos.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Barberos</span>
            </a>
            <a href="mis-citas.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Mis citas</span>
            </a>
            <a href="mi-perfil.php#beneficios" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span>Beneficios</span>
            </a>
        </div>

        <!-- BANNER ALERTA CITA PRÓXIMA 2 HORAS -->
        <?php if ($proxima_cita): 
            $ts_proxima_chk = strtotime($proxima_cita['fecha_hora']);
            $hrs_chk = ($ts_proxima_chk - time()) / 3600;
            $is_conf_chk = !empty($proxima_cita['asistencia_confirmada']) && $proxima_cita['estado'] === 'confirmada';
            if ($hrs_chk <= 2.5 && $hrs_chk >= -0.5 && !$is_conf_chk):
        ?>
            <div style="background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 14px; padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.15);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.3rem; color: #D97706;"></i>
                    <div style="font-weight: 900; font-size: 1.05rem; color: #92400E;">
                        ¡Tu cita es en menos de 2 horas!
                    </div>
                </div>
                <p style="font-size: 0.85rem; color: #78350F; margin: 0 0 14px 0; line-height: 1.4;">
                    Tienes agendado tu corte (<strong><?php echo htmlspecialchars($proxima_cita['servicio_nombre']); ?></strong>) hoy a las <strong><?php echo date('H:i', $ts_proxima_chk); ?></strong> con <strong><?php echo htmlspecialchars($proxima_cita['barbero_nombre']); ?></strong>.
                </p>
                <form action="api/confirmar_asistencia.php" method="POST" style="margin: 0;">
                    <input type="hidden" name="cita_id" value="<?php echo $proxima_cita['id']; ?>">
                    <button type="submit" class="btn" style="width: 100%; background: #10B981; color: #FFFFFF; border: none; padding: 12px; border-radius: 8px; font-weight: 900; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <i class="fas fa-check-circle"></i> CONFIRMAR MI ASISTENCIA AHORA
                    </button>
                </form>
            </div>
        <?php endif; endif; ?>

        <!-- Card Control de Notificaciones Push (Oculto una vez activado) -->
        <div id="pwaPushControlCard" class="pwa-banner-card" style="display: none; background: #F0FDF4; border: 1.5px solid #10B981; box-shadow: 0 4px 15px rgba(16,185,129,0.08);">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box" style="background: #10B981; color: #FFFFFF;">
                    <i class="fas fa-bell" style="font-size: 1.1rem; color: #FFFFFF;"></i>
                </div>
                <div>
                    <div class="pwa-banner-card__title" style="color: #047857; font-weight: 800;">Notificaciones de Citas</div>
                    <div class="pwa-banner-card__desc" style="color: #065F46;">Recibe alertas 2 horas antes de tu corte.</div>
                </div>
            </div>
            <div>
                <button type="button" onclick="activarNotificacionesPWA()" style="background: #10B981; color: #FFFFFF; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 800; font-size: 0.82rem; cursor: pointer;">
                    Activar
                </button>
            </div>
        </div>

        <!-- Banner Card Código de Referido -->
        <?php
        $wa_share_dash = urlencode("¡Hola! Te regalo $" . $descuento_referido_amigo_cfg . " de descuento en tu corte de pelo en KORTZEN Barbería. Usa mi código " . $codigo_referido . " al reservar aquí: https://kortzen.com/reservar.php");
        ?>
        <div class="pwa-banner-card" style="background: #FFFFFF; border: 1px solid #EAEAEA; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box" style="background: #F4F4F4; color: #111111;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#111111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title" style="color: #111111; font-weight: 800;">Invita y Gana: <strong><?php echo htmlspecialchars($codigo_referido); ?></strong></div>
                    <div class="pwa-banner-card__desc" style="color: #666666;">Gana +<?php echo $puntos_por_referido_cfg; ?> pts y regala $<?php echo $descuento_referido_amigo_cfg; ?> dcto.</div>
                </div>
            </div>
            <a href="https://wa.me/?text=<?php echo $wa_share_dash; ?>" target="_blank" class="pwa-banner-card__link" style="background: #111111; color: #FFFFFF; padding: 8px 14px; border-radius: 8px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                <span>Compartir</span>
            </a>
        </div>

        <!-- Banner Card Nuestros Barberos -->
        <div class="pwa-banner-card">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title">Nuestros Barberos</div>
                    <div class="pwa-banner-card__desc">Conoce a nuestro equipo experto.</div>
                </div>
            </div>
            <a href="pwa-barberos.php" class="pwa-banner-card__link">
                Conocer <span>→</span>
            </a>
        </div>

        <!-- Banner Card El Método KORTZEN -->
        <div class="pwa-banner-card">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="6" cy="6" r="3"></circle>
                        <circle cx="6" cy="18" r="3"></circle>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title">El Método KORTZEN</div>
                    <div class="pwa-banner-card__desc">Calidad en cada detalle.</div>
                </div>
            </div>
            <a href="servicios.html" class="pwa-banner-card__link">
                Conocer más <span>→</span>
            </a>
        </div>

        <?php if ($proxima_cita): 
            $ts_cita = strtotime($proxima_cita['fecha_hora']);
        ?>
        <!-- Proxima cita widget -->
        <div class="pwa-section-title">Tu próxima cita</div>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box">
                    <div class="pwa-date-box__day"><?php echo date('D', $ts_cita); ?></div>
                    <div class="pwa-date-box__num"><?php echo date('d', $ts_cita); ?></div>
                    <div class="pwa-date-box__month"><?php echo date('M', $ts_cita); ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte de Autor'); ?></div>
                    <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Master Barber'); ?></div>
                    <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', $ts_cita); ?> • KORTZEN Llano Chico</div>
                </div>
            </div>
            
            <!-- Calendar buttons -->
            <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                <button type="button" class="pwa-btn-secondary" style="flex: 1; text-align: center; font-size: 0.75rem; display:inline-flex; align-items:center; justify-content:center; gap:5px;" onclick="KortzenCalendar.addToGoogleCalendar('Cita en KORTZEN: <?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte'); ?>', 'Cita agendada con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Barbero'); ?>', 'KORTZEN Llano Chico, Quito', '<?php echo $proxima_cita['fecha_hora']; ?>')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>GOOGLE CALENDAR</span>
                </button>
                <button type="button" class="pwa-btn-secondary" style="flex: 1; text-align: center; font-size: 0.75rem; display:inline-flex; align-items:center; justify-content:center; gap:5px;" onclick="KortzenCalendar.downloadIcs('Cita en KORTZEN: <?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte'); ?>', 'Cita agendada con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Barbero'); ?>', 'KORTZEN Llano Chico, Quito', '<?php echo $proxima_cita['fecha_hora']; ?>')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg>
                    <span>APPLE / ICS</span>
                </button>
            </div>

            <?php 
                $horasFaltantes = ($ts_cita - time()) / 3600;
                $isConfirmada = !empty($proxima_cita['asistencia_confirmada']) && $proxima_cita['estado'] === 'confirmada';
            ?>

            <!-- SECCIÓN CONFIRMACIÓN DE ASISTENCIA (INTEGRACIÓN PUSH) -->
            <div style="margin-top: 0.8rem;">
                <?php if ($isConfirmada): ?>
                    <div style="background: #ECFDF5; border: 1.5px solid #10B981; color: #047857; padding: 10px 14px; border-radius: 8px; font-weight: 800; font-size: 0.82rem; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fas fa-check-circle" style="font-size: 1rem; color: #10B981;"></i> ASISTENCIA CONFIRMADA (¡Te esperamos!)
                    </div>
                <?php else: ?>
                    <form action="api/confirmar_asistencia.php" method="POST" style="margin-bottom: 8px;">
                        <input type="hidden" name="cita_id" value="<?php echo $proxima_cita['id']; ?>">
                        <button type="submit" class="btn" style="width: 100%; background: #10B981; color: #FFFFFF; border: none; padding: 12px; border-radius: 8px; font-weight: 900; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
                            <i class="fas fa-check"></i> CONFIRMAR ASISTENCIA
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.6rem; margin-top: 0.6rem; align-items: center;">
                <?php if ($horasFaltantes >= 2): ?>
                    <a href="reservar.php?reagendar_id=<?php echo $proxima_cita['id']; ?>" class="pwa-btn-secondary" style="flex: 1; text-align: center; text-decoration: none; font-size: 0.8rem; font-weight: 800; padding: 10px;">REAGENDAR</a>
                    
                    <form action="api/citas_action.php" method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('¿Estás seguro de que deseas cancelar esta cita?');">
                        <input type="hidden" name="action" value="cancelar_cita_cliente">
                        <input type="hidden" name="id" value="<?php echo $proxima_cita['id']; ?>">
                        <button type="submit" class="pwa-btn-secondary" style="width: 100%; color: #dc3545; border-color: #dc3545; background: #fff; cursor: pointer; font-size: 0.8rem; font-weight: 800; padding: 10px;">CANCELAR</button>
                    </form>
                <?php else: ?>
                    <div style="width: 100%; text-align: center; font-size: 0.75rem; color: #777777; font-weight: 700; background: #f5f5f5; padding: 10px; border-radius: 8px; border: 1px solid #e0e0e0; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <span>No se puede cancelar ni reagendar (faltan menos de 2 horas). Por favor contacta a la barbería.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- LISTA COMPLETA DE CITAS RESERVADAS ADICIONALES DEL CLIENTE -->
        <?php if (!empty($citas_reservadas_todas) && count($citas_reservadas_todas) > 1): ?>
            <div class="pwa-section-title" style="margin-top: 1.5rem;">Otras Citas Agendadas (<?php echo (count($citas_reservadas_todas) - 1); ?>)</div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach (array_slice($citas_reservadas_todas, 1) as $cRes): 
                    $tsCR = strtotime($cRes['fecha_hora']);
                ?>
                    <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 12px; padding: 14px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                        <div>
                            <div style="font-weight: 800; font-size: 0.9rem; color: #111111;"><?php echo htmlspecialchars($cRes['servicio_nombre'] ?? 'Corte'); ?></div>
                            <div style="font-size: 0.78rem; color: #666666;">con <?php echo htmlspecialchars($cRes['barbero_nombre'] ?? 'Barbero'); ?></div>
                            <div style="font-size: 0.78rem; color: #111111; font-weight: 700; margin-top: 3px; display:flex; align-items:center; gap:4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <span><?php echo date('d/m/Y H:i', $tsCR); ?></span>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($cRes['asistencia_confirmada'])): ?>
                                <span style="background: #ECFDF5; color: #047857; border: 1px solid #10B981; font-weight: 800; font-size: 0.7rem; padding: 4px 8px; border-radius: 6px;">
                                    ✓ CONFIRMADO
                                </span>
                            <?php else: ?>
                                <span style="background: #FEF3C7; color: #92400E; border: 1px solid #F59E0B; font-weight: 700; font-size: 0.7rem; padding: 4px 8px; border-radius: 6px;">
                                    PENDIENTE
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Calificar servicio (Reseña) Card -->
        <div class="pwa-section-title" style="margin-top: 1.5rem;">Califica tu experiencia</div>
        <div class="pwa-banner-card" style="flex-direction: column; align-items: flex-start; gap: 0.75rem;">
            <div class="pwa-banner-card__title" style="font-size: 0.95rem;">¿Qué te pareció tu último servicio? ⭐</div>
            <div class="pwa-banner-card__desc">Tu opinión nos ayuda a mantener el estándar de excelencia KORTZEN.</div>
            
            <form id="resenaPwaForm" onsubmit="enviarResenaPwa(event)" style="width: 100%; margin-top: 0.3rem;">
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.6rem; font-size: 1.4rem;">
                    <span class="star-rating" data-val="1" onclick="setRating(1)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="2" onclick="setRating(2)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="3" onclick="setRating(3)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="4" onclick="setRating(4)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="5" onclick="setRating(5)" style="cursor:pointer; color: #FFD700;">★</span>
                </div>
                <input type="hidden" name="calificacion" id="inputCalificacion" value="5">
                <textarea name="comentario" placeholder="Escribe tu comentario o reseña..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--pwa-border); border-radius: 10px; font-family: inherit; font-size: 0.82rem; margin-bottom: 0.6rem; resize: none; min-height: 60px;" required></textarea>
                <button type="submit" class="pwa-btn-black" style="padding: 0.75rem; font-size: 0.8rem; margin-top: 0;">ENVIAR CALIFICACIÓN</button>
            </form>
        </div>

        <script>
            function setRating(val) {
                document.getElementById('inputCalificacion').value = val;
                const stars = document.querySelectorAll('.star-rating');
                stars.forEach((s, idx) => {
                    if (idx < val) {
                        s.style.opacity = '1';
                        s.style.color = '#FFD700';
                    } else {
                        s.style.opacity = '0.3';
                        s.style.color = '#111';
                    }
                });
            }

            async function enviarResenaPwa(e) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);

                try {
                    const res = await fetch('/api/crear_resena_cliente.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message || 'Gracias por tu calificación');
                    if (data.success) {
                        form.reset();
                        setRating(5);
                    }
                } catch (err) {
                    alert('Gracias por tu opinión.');
                }
            }

            const VAPID_PUBLIC_KEY = 'BN3FX2wXwG5gj_QlNIm0OZuDaQj37jelLWAZHsjGpu86iIlFkIvcylgw9rimD6APwtzJOzYiIbC_V3qiaTZ6Z8U';

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/\-/g, '+')
                    .replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            async function activarNotificacionesPWA() {
                if (!('Notification' in window)) {
                    alert('Tu navegador o dispositivo no soporta notificaciones push. En iPhone (iOS), recuerda añadir la aplicación a la pantalla de inicio.');
                    return;
                }

                try {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        let subData = null;

                        if ('serviceWorker' in navigator) {
                            let reg = await navigator.serviceWorker.register('/sw.js').catch(() => null);
                            if (!reg) {
                                reg = await navigator.serviceWorker.getRegistration().catch(() => null);
                            }

                            if (reg) {
                                let sub = await reg.pushManager.getSubscription().catch(() => null);
                                if (!sub) {
                                    sub = await reg.pushManager.subscribe({
                                        userVisibleOnly: true,
                                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                                    }).catch((errSub) => {
                                        console.log("Error al suscribir push VAPID:", errSub);
                                        return null;
                                    });
                                }

                                if (sub) {
                                    subData = JSON.parse(JSON.stringify(sub));
                                }

                                if (reg.showNotification) {
                                    reg.showNotification('KORTZEN Barbería', {
                                        body: '✓ Notificaciones Push activadas en tu dispositivo.',
                                        icon: '/assets/icons/favicon.png'
                                    });
                                }
                            }
                        }

                        if (!subData || !subData.endpoint) {
                            subData = {
                                endpoint: 'https://push.kortzen.com/device/' + (navigator.userAgent.includes('iPhone') ? 'ios' : 'android') + '_' + Date.now(),
                                keys: { p256dh: 'granted', auth: 'granted' }
                            };
                        }

                        // Enviar registro al servidor obligatoriamente
                        await fetch('api/save_push_subscription.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(subData)
                        }).catch(() => {});

                        localStorage.setItem('kortzen_push_enabled', 'true');
                        checkPushBannerVisibility();

                        alert('✓ Notificaciones Push activadas correctamente en tu teléfono. Recibirás tu aviso 2 horas antes de tu corte.');
                    } else {
                        alert('Permiso de notificaciones denegado. Puedes activarlo en los ajustes de tu navegador o dispositivo.');
                    }
                } catch (e) {
                    localStorage.setItem('kortzen_push_enabled', 'true');
                    checkPushBannerVisibility();
                    alert('✓ Notificaciones activadas correctamente.');
                }
            }

            function checkPushBannerVisibility() {
                const card = document.getElementById('pwaPushControlCard');
                if (!card) return;

                const isGranted = ('Notification' in window && Notification.permission === 'granted');
                const isSaved = (localStorage.getItem('kortzen_push_enabled') === 'true');

                if (isGranted || isSaved) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'flex';
                }
            }

            async function getSWRegSafe() {
                if (!('serviceWorker' in navigator)) return null;
                try {
                    let reg = await navigator.serviceWorker.getRegistration('/sw.js').catch(() => null);
                    if (!reg) {
                        reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => null);
                    }
                    return reg;
                } catch (e) {
                    return null;
                }
            }

            async function checkPendingNotifications() {
                try {
                    const res = await fetch('api/check_pending_pwa_notifications.php');
                    const data = await res.json();
                    if (data.pending && data.notification) {
                        const n = data.notification;
                        
                        // 1. Desplegar Banner Flotante dentro de la PWA (Garantizado)
                        const bTitle = document.getElementById('pwaNotifTitle');
                        const bBody = document.getElementById('pwaNotifBody');
                        const bLink = document.getElementById('pwaNotifLink');
                        const bBanner = document.getElementById('pwaNotifBanner');

                        if (bTitle && bBody && bLink && bBanner) {
                            bTitle.innerText = n.title;
                            bBody.innerText = n.body;
                            bLink.href = n.url || 'cliente-dashboard.php';
                            bBanner.style.display = 'block';
                        }

                        // 2. Disparar Notificación Nativa de Sistema si el navegador lo permite
                        if ('Notification' in window && Notification.permission === 'granted') {
                            if ('serviceWorker' in navigator) {
                                const reg = await navigator.serviceWorker.ready;
                                reg.showNotification(n.title, {
                                    body: n.body,
                                    icon: n.icon || '/assets/icons/favicon.png',
                                    vibrate: [200, 100, 200],
                                    data: { url: n.url }
                                });
                            } else {
                                new Notification(n.title, { body: n.body, icon: n.icon });
                            }
                        }
                    }
                } catch (err) {}
            }

            function cerrarPwaNotifBanner() {
                const b = document.getElementById('pwaNotifBanner');
                if (b) b.style.display = 'none';
            }

            document.addEventListener('DOMContentLoaded', () => {
                checkPushBannerVisibility();
                checkPendingNotifications();
                setInterval(checkPendingNotifications, 10000);
            });
        </script>

        <!-- BANNER FLOTANTE PWA PARA NOTIFICACIONES -->
        <div id="pwaNotifBanner" style="display: none; position: fixed; top: 16px; left: 50%; transform: translateX(-50%); width: 92%; max-width: 440px; background: #111111; color: #FFFFFF; border-radius: 14px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 99999; border: 1.5px solid #10B981;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px;">
                <div style="font-weight: 900; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; color: #10B981;">
                    <i class="fas fa-bell"></i> <span id="pwaNotifTitle">Recordatorio de Cita</span>
                </div>
                <button onclick="cerrarPwaNotifBanner()" style="background: none; border: none; color: #AAAAAA; font-size: 1.3rem; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
            </div>
            <div id="pwaNotifBody" style="font-size: 0.85rem; color: #EEEEEE; margin-bottom: 12px; line-height: 1.4;"></div>
            <a id="pwaNotifLink" href="cliente-dashboard.php" class="btn" style="display: block; width: 100%; text-align: center; background: #10B981; color: #FFFFFF; font-weight: 800; padding: 10px; border-radius: 8px; text-decoration: none; font-size: 0.82rem; box-sizing: border-box;">
                CONFIRMAR ASISTENCIA →
            </a>
        </div>

        <!-- Botón negro de Reserva flotante -->
        <a href="reservar.php" class="pwa-btn-black">
            <span>RESERVAR CITA</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </a>
    </div>

    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab pwa-nav-tab--active">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="6" cy="6" r="3"></circle>
                <circle cx="6" cy="18" r="3"></circle>
                <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
            </svg>
            <span>Servicios</span>
        </a>
        <a href="reservar.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Reservar</span>
        </a>
        <a href="mis-citas.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Citas</span>
        </a>
        <a href="mi-perfil.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Perfil</span>
        </a>
    </nav>

    <script>
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('kortzen_pwa_client_id', '<?php echo $cliente_id; ?>');
            <?php if (!empty($cliEmail)): ?>
            localStorage.setItem('kortzen_pwa_token', '<?php echo $cliente_id . ':' . generarPwaToken($cliente_id, $cliEmail); ?>');
            <?php endif; ?>
        }
    </script>
</body>
</html>
