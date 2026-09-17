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
                         ORDER BY COALESCE(cs.orden, 999) ASC, s.categoria ASC, COALESCE(s.orden, 999) ASC, s.id ASC");
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
    <link rel="stylesheet" href="/css/pwa-native.css?v=53">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js?v=26200" defer></script>
    <style>
        .pwa-service-card {
            background: var(--pwa-card-bg, #FFFFFF);
            border: 1px solid var(--pwa-border, #EAEAEA);
            border-radius: 16px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            margin-bottom: 0.85rem;
        }
        .pwa-service-card:active {
            transform: scale(0.985);
            background: #FAFAFA;
        }
        .pwa-service-card__top {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pwa-service-card__thumb-box {
            position: relative;
            width: 68px;
            height: 68px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            background: #F3F4F6;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        .pwa-service-card__thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .pwa-service-card__main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .pwa-service-card__header-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }
        .pwa-service-card__title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--pwa-text-main, #111111);
            line-height: 1.25;
            margin: 0;
        }
        .pwa-service-card__price {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--pwa-text-main, #111111);
            white-space: nowrap;
        }
        .pwa-service-card__meta {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: #6B7280;
            font-weight: 600;
        }
        .pwa-service-card__desc {
            font-size: 0.78rem;
            color: var(--pwa-text-muted, #777777);
            line-height: 1.35;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .pwa-service-card__incluye {
            margin-top: 4px;
            padding: 8px 10px;
            background: #F9FAFB;
            border: 1px solid #F0F0F0;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .pwa-service-card__incluye-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--color-gold, #C0A062);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .pwa-service-card__incluye-list {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .pwa-service-card__incluye-item {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-size: 0.75rem;
            color: #374151;
            line-height: 1.3;
            font-weight: 500;
        }
        .pwa-check-icon {
            flex-shrink: 0;
            margin-top: 2px;
            color: #10B981;
        }
        .pwa-service-card__footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-top: 4px;
        }
        .pwa-service-card__book-cta {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-gold, #C0A062);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
    </style>
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
            <?php foreach ($lista_servicios as $s): 
                // Imagen con fallback inteligente según nombre/categoría
                $foto = !empty($s['foto_url']) ? $s['foto_url'] : (!empty($s['imagen_url']) ? $s['imagen_url'] : (!empty($s['foto']) ? $s['foto'] : ''));
                if (empty($foto)) {
                    $nom_lower = mb_strtolower(($s['nombre'] ?? '') . ' ' . ($s['categoria'] ?? ''));
                    if (strpos($nom_lower, 'barba') !== false || strpos($nom_lower, 'beard') !== false) {
                        $foto = '/assets/images/service-beard-care.jpg';
                    } elseif (strpos($nom_lower, 'afeit') !== false || strpos($nom_lower, 'shave') !== false) {
                        $foto = '/assets/images/service-traditional-shave.jpg';
                    } elseif (strpos($nom_lower, 'facial') !== false || strpos($nom_lower, 'spa') !== false || strpos($nom_lower, 'mascarilla') !== false) {
                        $foto = '/assets/images/service-facial-treatment.jpg';
                    } else {
                        $foto = '/assets/images/service-classic-cut.jpg';
                    }
                }

                // Procesamiento limpio de "que_incluye" sin caracteres corruptos ni emojis
                $incluye_items = [];
                if (!empty($s['que_incluye'])) {
                    $raw_lines = preg_split('/\r\n|\r|\n/u', trim($s['que_incluye']));
                    foreach ($raw_lines as $l) {
                        $cleaned = preg_replace('/^[\s\x{2022}\x{2023}\x{25E6}\x{2043}\x{2219}\x{2713}\x{2714}•\-\*\✓\✔\?\¿\.\:\s]+/u', '', trim($l));
                        $cleaned = trim($cleaned);
                        if (!empty($cleaned)) {
                            $incluye_items[] = $cleaned;
                        }
                    }
                }
            ?>
            <a href="reservar.php?servicio_id=<?php echo $s['id']; ?>" class="pwa-service-card">
                <div class="pwa-service-card__top">
                    <div class="pwa-service-card__thumb-box">
                        <img src="<?php echo htmlspecialchars($foto); ?>" alt="<?php echo htmlspecialchars($s['nombre']); ?>" class="pwa-service-card__thumb" onerror="this.onerror=null; this.src='/assets/images/service-classic-cut.jpg';">
                    </div>
                    <div class="pwa-service-card__main">
                        <div class="pwa-service-card__header-row">
                            <h3 class="pwa-service-card__title"><?php echo htmlspecialchars($s['nombre']); ?></h3>
                            <span class="pwa-service-card__price">$<?php echo number_format((float)$s['precio'], 2); ?></span>
                        </div>
                        <?php if (!empty($s['duracion_minutos'])): ?>
                            <div class="pwa-service-card__meta">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span><?php echo (int)$s['duracion_minutos']; ?> min</span>
                            </div>
                        <?php endif; ?>
                        <p class="pwa-service-card__desc">
                            <?php echo htmlspecialchars($s['descripcion'] ?? 'Servicio de barbería profesional.'); ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($incluye_items)): ?>
                    <div class="pwa-service-card__incluye">
                        <span class="pwa-service-card__incluye-label">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                            </svg>
                            Incluye
                        </span>
                        <div class="pwa-service-card__incluye-list">
                            <?php foreach ($incluye_items as $item): ?>
                                <div class="pwa-service-card__incluye-item">
                                    <svg class="pwa-check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span><?php echo htmlspecialchars($item); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pwa-service-card__footer">
                    <span class="pwa-service-card__book-cta">
                        Reservar
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
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
