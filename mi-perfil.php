<?php
/**
 * Mi Perfil - Native App UI (Screen 4)
 */

require_once 'config.php';

if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$nombre = $_SESSION['cliente_nombre'] ?? $cliente['nombre'] ?? 'Cliente';
$email = $_SESSION['cliente_email'] ?? $cliente['email'] ?? '';
$cliente_id = $_SESSION['cliente_id'] ?? $cliente['id'] ?? null;

// Iniciales
$nombres_arr = explode(' ', trim($nombre));
$iniciales = strtoupper(substr($nombres_arr[0], 0, 1) . (isset($nombres_arr[1]) ? substr($nombres_arr[1], 0, 1) : ''));

// Cita próxima y Puntos KORTZEN
$proxima_cita = null;
$puntos_actuales = 0;

if ($cliente_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios b ON c.barbero_id = b.id
            WHERE c.cliente_id = ? AND c.fecha_hora >= NOW() AND c.estado IN ('pendiente', 'confirmada')
            ORDER BY c.fecha_hora ASC
            LIMIT 1
        ");
        $stmt->execute([$cliente_id]);
        $proxima_cita = $stmt->fetch(PDO::FETCH_ASSOC);

        // Puntos KORTZEN
        $stmtP = $pdo->prepare("SELECT puntos FROM clientes WHERE id = ?");
        $stmtP->execute([$cliente_id]);
        $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);

        if ($rowP && isset($rowP['puntos']) && $rowP['puntos'] !== null) {
            $puntos_actuales = intval($rowP['puntos']);
        } else {
            // Si la columna no existe aún, calcular 100 pts por cita completada
            $stmtC = $pdo->prepare("SELECT COUNT(*) * 100 as total_pts FROM citas WHERE cliente_id = ? AND estado = 'completada'");
            $stmtC->execute([$cliente_id]);
            $rowC = $stmtC->fetch(PDO::FETCH_ASSOC);
            $puntos_actuales = intval($rowC['total_pts'] ?? 0);
        }
    } catch (Exception $e) {
        $puntos_actuales = 0;
    }
}

// Cargar configuraciones de nivel
$puntos_nivel_plata = 500;
$puntos_nivel_oro = 1500;
$puntos_nivel_vip = 3000;

try {
    $pdo = getConnection();
    $stmtCfgsN = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('puntos_nivel_plata', 'puntos_nivel_oro', 'puntos_nivel_vip', 'puntos_por_referido', 'descuento_referido_amigo')");
    $cfgsN = $stmtCfgsN->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($cfgsN['puntos_nivel_plata'])) $puntos_nivel_plata = intval($cfgsN['puntos_nivel_plata']);
    if (!empty($cfgsN['puntos_nivel_oro'])) $puntos_nivel_oro = intval($cfgsN['puntos_nivel_oro']);
    if (!empty($cfgsN['puntos_nivel_vip'])) $puntos_nivel_vip = intval($cfgsN['puntos_nivel_vip']);
    $puntos_por_referido_cfg = intval($cfgsN['puntos_por_referido'] ?? 200);
    $descuento_referido_amigo_cfg = number_format(floatval($cfgsN['descuento_referido_amigo'] ?? 2.00), 2);

    $stmtCod = $pdo->prepare("SELECT codigo_referido FROM clientes WHERE id = ?");
    $stmtCod->execute([$cliente_id]);
    $codigo_referido = $stmtCod->fetchColumn() ?: '';

    if (!empty($codigo_referido) && (strpos($codigo_referido, 'KORTZEN-') !== false || strpos($codigo_referido, 'KORTZEN') !== false)) {
        $codigo_referido = str_replace(['KORTZEN-', 'KORTZEN'], '', $codigo_referido);
        $stmtUpdC = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");
        $stmtUpdC->execute([$codigo_referido, $cliente_id]);
    }
} catch (Exception $exN) {}

// Calcular Nivel y Progreso Dinámicamente
if ($puntos_actuales >= $puntos_nivel_vip) {
    $tier_nombre = "Miembro KORTZEN • Nivel VIP 💎";
    $siguiente_nivel = $puntos_nivel_vip;
} elseif ($puntos_actuales >= $puntos_nivel_oro) {
    $tier_nombre = "Miembro KORTZEN • Nivel Oro 👑";
    $siguiente_nivel = $puntos_nivel_vip;
} elseif ($puntos_actuales >= $puntos_nivel_plata) {
    $tier_nombre = "Miembro KORTZEN • Nivel Plata 🥈";
    $siguiente_nivel = $puntos_nivel_oro;
} else {
    $tier_nombre = "Miembro KORTZEN • Nivel Bronce 🥉";
    $siguiente_nivel = $puntos_nivel_plata;
}

$porcentaje_progreso = min(100, round(($puntos_actuales / max(1, $siguiente_nivel)) * 100));

// Procesar cambio de contraseña
$pass_msg_success = '';
$pass_msg_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_pass') {
    $new_pass = $_POST['new_pass'] ?? '';
    $conf_pass = $_POST['conf_pass'] ?? '';
    if (empty($new_pass) || strlen($new_pass) < 4) {
        $pass_msg_error = 'La contraseña debe tener al menos 4 caracteres.';
    } elseif ($new_pass !== $conf_pass) {
        $pass_msg_error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = getConnection();
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmtUpdP = $pdo->prepare("UPDATE clientes SET password = ? WHERE id = ?");
            $stmtUpdP->execute([$hash, $cliente_id]);
            $pass_msg_success = 'Contraseña actualizada correctamente.';
        } catch (Exception $e) {
            $pass_msg_error = 'Error al actualizar la contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Mi Perfil - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=53">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js?v=26200" defer></script>
    <script src="/js/branch-selector.js?v=26000"></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>
        
        <!-- Native Top Bar (Screen 4) -->
        <header class="pwa-header">
            <button class="pwa-header__btn" onclick="history.back()" title="Atrás">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="pwa-header__center" onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" title="Cambiar Sucursal">
                <div class="pwa-header__logo">KORTZEN</div>
                <div class="pwa-header__branch-pill">
                    <span class="pwa-header__branch-dot"></span>
                    <span data-branch-dynamic="name" class="pwa-header__branch-text">KORTZEN Llano Chico</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>
            <a href="logout.php" onclick="if(window.cerrarSesionPwa){ event.preventDefault(); window.cerrarSesionPwa('/cliente-login.php'); }" class="pwa-header__btn" title="Cerrar Sesión" style="color: #dc3545;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </header>

        <!-- User Header Card -->
        <div class="pwa-profile-header">
            <div class="pwa-profile-user">
                <div class="pwa-profile-avatar-circle"><?php echo $iniciales; ?></div>
                <div>
                    <div class="pwa-profile-name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="pwa-profile-tier"><?php echo htmlspecialchars($tier_nombre); ?></div>
                </div>
            </div>
            <div style="font-size: 0.8rem; font-weight: 800; color: #111111;">ID #<?php echo $cliente_id; ?></div>
        </div>

        <!-- Sucursal Activa Card (Click to change) -->
        <div onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 14px; padding: 14px 18px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;">📍</span>
                <div>
                    <div style="font-size: 0.70rem; font-weight: 800; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">SUCURSAL SELECCIONADA</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: #111111;" data-branch-dynamic="name">KORTZEN Llano Chico</div>
                </div>
            </div>
            <span style="background: #111111; color: #FFFFFF; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 6px; text-transform: uppercase;">
                Cambiar
            </span>
        </div>

        <!-- Puntos KORTZEN Black Card -->
        <div class="pwa-points-card">
            <div class="pwa-points-header">
                <span>Puntos KORTZEN</span>
                <span>Siguiente nivel <?php echo number_format($siguiente_nivel); ?> pts</span>
            </div>
            <div class="pwa-points-value"><?php echo number_format($puntos_actuales); ?> <span style="font-size: 1rem; font-weight: 400;">pts</span></div>
            <div class="pwa-progress-bar">
                <div class="pwa-progress-fill" style="width: <?php echo $porcentaje_progreso; ?>%;"></div>
            </div>
        </div>

        <!-- Tarjeta de Programa de Referidos & Recompensas -->
        <?php
        $wa_share_msg = urlencode("¡Hola! Te regalo $" . $descuento_referido_amigo_cfg . " de descuento en tu corte de pelo en KORTZEN Barbería. Usa mi código " . $codigo_referido . " al reservar aquí: https://kortzen.com/reservar.php");
        ?>
        <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; padding: 22px; color: #111111; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="font-size: 1.05rem; font-weight: 800; color: #111111; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#111111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <span>Invita un Amigo y Gana</span>
                </h3>
                <span style="background: #F4F4F4; color: #111111; border: 1px solid #D1D1D1; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 0.78rem;">
                    +<?php echo $puntos_por_referido_cfg; ?> pts / amigo
                </span>
            </div>
            
            <p style="font-size: 0.85rem; color: #555555; margin-bottom: 14px; line-height: 1.4;">
                Comparte tu código con tus amigos. Ellos reciben <strong>$<?php echo $descuento_referido_amigo_cfg; ?> de descuento</strong> en su primera reserva y tú ganas <strong>+<?php echo $puntos_por_referido_cfg; ?> Puntos KORTZEN</strong> por cada visita.
            </p>

            <div style="background: #FAFAFA; border: 1px dashed #B0B0B0; border-radius: 10px; padding: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <div>
                    <span style="font-size: 0.75rem; color: #777777; display: block; font-weight: 600;">TU CÓDIGO PERSONAL:</span>
                    <strong id="referral-code-text" style="font-size: 1.25rem; font-weight: 900; color: #111111; letter-spacing: 0.05em;"><?php echo htmlspecialchars($codigo_referido); ?></strong>
                </div>
                <button onclick="copiarCodigoReferido()" style="background: #111111; color: #FFFFFF; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <span>Copiar</span>
                </button>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="https://wa.me/?text=<?php echo $wa_share_msg; ?>" target="_blank" style="flex: 1; background: #111111; color: #FFFFFF; text-decoration: none; padding: 11px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    <span>Compartir en WhatsApp</span>
                </a>
            </div>

            <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #EEEEEE; display: flex; justify-content: space-between; font-size: 0.82rem; color: #666666;">
                <span>Amigos invitados: <strong style="color: #111111;"><?php echo $total_referidos; ?></strong></span>
                <span>Puntos por referidos: <strong style="color: #111111;"><?php echo number_format($total_referidos * $puntos_por_referido_cfg); ?> pts</strong></span>
            </div>
        </div>

        <script>
        function copiarCodigoReferido() {
            var code = document.getElementById('referral-code-text').innerText;
            navigator.clipboard.writeText(code).then(function() {
                alert('¡Código ' + code + ' copiado al portapapeles!');
            }).catch(function() {
                prompt('Copia tu código:', code);
            });
        }
        </script>

        <?php if ($proxima_cita): 
            $ts_prof = strtotime($proxima_cita['fecha_hora']);
        ?>
        <!-- Próxima Cita -->
        <div class="pwa-section-title">Próxima cita</div>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box">
                    <div class="pwa-date-box__day"><?php echo date('D', $ts_prof); ?></div>
                    <div class="pwa-date-box__num"><?php echo date('d', $ts_prof); ?></div>
                    <div class="pwa-date-box__month"><?php echo date('M', $ts_prof); ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Estilo Pro'); ?></div>
                    <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Mateo'); ?></div>
                    <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', $ts_prof); ?> • KORTZEN Llano Chico</div>
                </div>
            </div>
            <a href="mis-citas.php" class="pwa-btn-secondary">VER TODAS MIS CITAS</a>
        </div>
        <?php endif; ?>

        <!-- Mis Beneficios Section -->
        <div class="pwa-section-title" id="beneficios">Mis beneficios</div>
        <div class="pwa-benefits-list">
            <a href="javascript:void(0)" onclick="alert('Tienes un 15% de descuento en tu próximo servicio con el código KORTZEN15')" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Promociones exclusivas</div>
                        <div class="pwa-benefit-item__desc">Ver ofertas disponibles</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="javascript:void(0)" onclick="alert('Comparte tu enlace de referido con amigos y gana 200 puntos KORTZEN por cada reserva')" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 12 20 22 4 22 4 12"></polyline>
                            <rect x="2" y="7" width="20" height="5"></rect>
                            <line x1="12" y1="22" x2="12" y2="7"></line>
                            <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                            <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Invita y gana</div>
                        <div class="pwa-benefit-item__desc">Comparte y obtén beneficios</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Atención por WhatsApp</div>
                        <div class="pwa-benefit-item__desc">Contacto directo con recepción</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="mis-citas.php" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Historial de servicios</div>
                        <div class="pwa-benefit-item__desc">Revisa tus servicios anteriores</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <!-- Opción de Cambiar Contraseña -->
            <a href="javascript:void(0)" onclick="openPassModal()" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Seguridad & Contraseña</div>
                        <div class="pwa-benefit-item__desc">Cambiar mi contraseña de acceso</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>

        <?php if ($pass_msg_success): ?>
            <div style="margin-top: 1rem; padding: 12px; background: #e6f4ea; color: #137333; border: 1px solid #ceead6; border-radius: 12px; font-size: 0.85rem; font-weight: 700; text-align: center;">
                ✓ <?php echo htmlspecialchars($pass_msg_success); ?>
            </div>
        <?php endif; ?>
        <?php if ($pass_msg_error): ?>
            <div style="margin-top: 1rem; padding: 12px; background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; border-radius: 12px; font-size: 0.85rem; font-weight: 700; text-align: center;">
                ⚠️ <?php echo htmlspecialchars($pass_msg_error); ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="logout.php" onclick="if(window.cerrarSesionPwa){ event.preventDefault(); window.cerrarSesionPwa('/cliente-login.php'); }" style="color: #dc3545; font-size: 0.85rem; text-decoration: none; font-weight: 600;">Cerrar sesión</a>
        </div>
    </div>

    <!-- Modal para cambiar contraseña -->
    <div id="passModal" style="display: none; position: fixed; inset: 0; z-index: 100000; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 1rem;" onclick="if(event.target === this) closePassModal();">
        <div style="background: #FFFFFF; color: #111111; border-radius: 18px; max-width: 400px; width: 100%; padding: 1.75rem 1.5rem; box-shadow: 0 20px 50px rgba(0,0,0,0.5); position: relative; box-sizing: border-box;">
            <button onclick="closePassModal()" style="position: absolute; top: 14px; right: 14px; background: transparent; border: none; font-size: 1.2rem; cursor: pointer; color: #888;">✕</button>
            <div style="font-size: 1.15rem; font-weight: 900; margin-bottom: 0.3rem;">Cambiar Contraseña</div>
            <p style="font-size: 0.82rem; color: #666666; margin-bottom: 1.25rem;">Define tu nueva contraseña para ingresar a KORTZEN.</p>
            
            <form method="POST" action="mi-perfil.php">
                <input type="hidden" name="action" value="cambiar_pass">
                <div style="margin-bottom: 1rem; text-align: left;">
                    <label style="display: block; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #555; margin-bottom: 4px;">Nueva Contraseña</label>
                    <input type="password" name="new_pass" required minlength="4" placeholder="Mínimo 4 caracteres" style="width: 100%; padding: 12px; border: 1px solid #DDD; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 1.25rem; text-align: left;">
                    <label style="display: block; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #555; margin-bottom: 4px;">Confirmar Contraseña</label>
                    <input type="password" name="conf_pass" required minlength="4" placeholder="Repite la contraseña" style="width: 100%; padding: 12px; border: 1px solid #DDD; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <button type="submit" style="width: 100%; padding: 13px; background: #111111; color: #FFFFFF; font-weight: 800; font-size: 0.88rem; border: none; border-radius: 8px; cursor: pointer; text-transform: uppercase;">
                    Guardar Contraseña
                </button>
            </form>
        </div>
    </div>

    <script>
        function openPassModal() {
            document.getElementById('passModal').style.display = 'flex';
        }
        function closePassModal() {
            document.getElementById('passModal').style.display = 'none';
        }
    </script>

    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab">
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
        <a href="mi-perfil.php" class="pwa-nav-tab pwa-nav-tab--active">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Perfil</span>
        </a>
    </nav>

</body>
</html>
