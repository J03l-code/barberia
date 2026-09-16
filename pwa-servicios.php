<?php
/**
 * Servicios - Native App UI (Screen 2)
 * Carga dinámicamente todos los servicios reales agrupados por categoría
 */

session_start();
require_once 'config.php';

// Obtener servicios reales de la BD
$servicios_por_cat = [];
try {
    $pdo = getConnection();
    asegurarTablaCategorias($pdo);
    $stmt = $pdo->query("SELECT s.* FROM servicios s 
                         LEFT JOIN categorias_servicios cs ON s.categoria = cs.nombre 
                         WHERE s.activo = 1 
                         ORDER BY COALESCE(cs.orden, 999) ASC, s.categoria ASC, s.id ASC");
    $todos_servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($todos_servicios)) {
        foreach ($todos_servicios as $s) {
            $cat = !empty($s['categoria']) ? trim($s['categoria']) : 'Servicios';
            $servicios_por_cat[$cat][] = $s;
        }
    }
} catch (Exception $e) {
}

// Fallback por si la base de datos estuviera vacía
if (empty($servicios_por_cat)) {
    $servicios_por_cat = [
        'Corte & Estilo' => [
            ['id' => 1, 'nombre' => 'Corte de Cabello Tradicional', 'descripcion' => 'Corte clásico o moderno con acabado a navaja y peinado.', 'precio' => 10.00, 'duracion_minutos' => 35]
        ],
        'Afeitado Tradicional' => [
            ['id' => 2, 'nombre' => 'Afeitado Tradicional', 'descripcion' => 'Ritual completo con espuma caliente, toalla aromática y bálsamo.', 'precio' => 10.00, 'duracion_minutos' => 30]
        ],
        'Cuidado de Barba' => [
            ['id' => 3, 'nombre' => 'Barba Premium', 'descripcion' => 'Perfilado, corte y diseño de barba con toalla caliente e hidratación.', 'precio' => 5.00, 'duracion_minutos' => 30]
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Servicios - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=50">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
    <script src="/js/branch-selector.js?v=26000"></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>

        <!-- Native Top Bar (Screen 2) -->
        <header class="pwa-header">
            <button class="pwa-header__btn" onclick="history.back()" title="Atrás">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="pwa-header__title">Servicios</div>
            <button class="pwa-header__btn" onclick="location.href='reservar.php'" title="Reservar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </button>
        </header>

        <p class="pwa-subtitle">Servicios premium para un estilo impecable y duradero.</p>

        <!-- Services Categorized List -->
        <?php foreach ($servicios_por_cat as $categoria_nombre => $lista_servicios): ?>
        <div class="pwa-section-title" style="margin-top: 1.5rem; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.85rem; border-bottom: 1px solid var(--pwa-border); padding-bottom: 0.4rem;">
            <?php echo htmlspecialchars($categoria_nombre); ?>
        </div>

        <div class="pwa-services-list" style="margin-top: 0.75rem;">
            <?php foreach ($lista_servicios as $s): ?>
            <a href="reservar.php?servicio_id=<?php echo $s['id']; ?>" class="pwa-service-card">
                <div class="pwa-service-card__left">
                    <div class="pwa-service-card__icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="6" cy="6" r="3"></circle>
                            <circle cx="6" cy="18" r="3"></circle>
                            <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                            <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                            <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="pwa-service-card__info">
                        <div class="pwa-service-card__name"><?php echo htmlspecialchars($s['nombre']); ?></div>
                        <div class="pwa-service-card__desc">
                            <?php if (!empty($s['duracion_minutos'])): ?>
                                <span style="font-weight: 600; color: var(--pwa-text-main);"><?php echo $s['duracion_minutos']; ?> min</span> • 
                            <?php endif; ?>
                            <?php echo htmlspecialchars($s['descripcion'] ?? 'Servicio de barbería profesional.'); ?>
                        </div>
                        <?php if (!empty($s['que_incluye'])): ?>
                            <div style="margin-top: 6px; font-size: 0.76rem; color: #E5E7EB; line-height: 1.35; background: rgba(255, 255, 255, 0.05); border-left: 2.5px solid var(--color-gold, #C0A062); padding: 5px 8px; border-radius: 0 6px 6px 0;">
                                <strong style="color: var(--color-gold, #C0A062); font-size: 0.70rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Incluye:</strong>
                                <?php 
                                $incluyeFormatted = preg_replace('/^[•\-\*]\s*/m', '✓ ', trim($s['que_incluye']));
                                echo nl2br(htmlspecialchars($incluyeFormatted)); 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="pwa-service-card__right">
                    <div class="pwa-service-card__price">$<?php echo number_format($s['precio'], 2); ?></div>
                    <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div style="margin-top: 2rem; margin-bottom: 2rem;">
            <a href="reservar.php" class="pwa-btn-black">
                <span>IR A RESERVAR</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </a>
        </div>
    </div>

    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-nav-tab pwa-nav-tab--active">
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

</body>
</html>
